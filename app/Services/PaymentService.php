<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Support\Atomic;
use App\Support\Ids;
use App\Support\Money;
use Illuminate\Support\Str;

final class PaymentService
{
    public function __construct(private Flutterwave $flw, private NotificationService $mail) {}

    public function checkout(array $v): array
    {
        $project = Project::findOrFail((string) Ids::object($v['projectId']));
        $org = Organization::findOrFail((string) $project->organization);
        abort_unless($org->isActive && ! $org->blocked && ! $org->deletedAt && ! $project->is_deleted && ! $project->is_suspended && ! $project->closed && Money::minor($project->donated_amount) < Money::minor($project->target_amount), 400, 'Project has been closed or is unavailable');
        $amount = Money::major(Money::minor($v['amount']));
        $reference = 'CEOS-'.now()->format('Ymd').'-'.Str::uuid();
        $trx = Transaction::create(['tx_ref' => $reference, 'amount' => $amount, 'currency' => 'NGN', 'organization' => $project->organization, 'project' => $project->getKey(), 'anonymous' => (bool) $v['anonymous'], 'fullname' => $v['anonymous'] ? 'anonymous' : $v['name'], 'email' => $v['email'], 'phone' => $v['phone'] ?? null, 'checkoutState' => 'pending']);
        try {
            $result = $this->flw->checkout(['tx_ref' => $reference, 'amount' => $amount, 'currency' => 'NGN', 'payment_options' => 'card', 'redirect_url' => config('services.flutterwave.redirect'), 'customer' => ['email' => $v['email'], 'name' => $trx->fullname, 'phonenumber' => $trx->phone], 'customizations' => ['title' => 'NABAKU Fundraiser', 'description' => $project->title ?? 'Donation']]);
            abort_unless(isset($result['data']['link']), 502, 'Payment provider returned no checkout link');
            $trx->update(['auth_url' => $result['data']['link'], 'checkoutState' => 'ready']);

            return $result;
        } catch (\Throwable $e) {
            $trx->update(['checkoutState' => 'unknown']);
            // The provider may have accepted a timed-out request. Never retry creation automatically.
            abort(502, 'Payment initiation could not be confirmed. Reference: '.$reference);
        }
    }

    public function settle(array $verified): Transaction
    {
        $ref = $verified['tx_ref'] ?? $verified['txRef'] ?? null;
        abort_unless(is_string($ref) && is_string($verified['status'] ?? null), 400, 'Invalid verified payment');

        return Atomic::run(function () use ($verified, $ref) {
            $trx = Transaction::where('tx_ref', $ref)->firstOrFail();
            abort_unless(($verified['currency'] ?? null) === $trx->currency && Money::minor($verified['amount'] ?? '') === Money::minor($trx->amount), 409, 'Payment amount or currency mismatch');
            // Existing successful Node transactions are considered already credited.
            if ($trx->status === 'successful') {
                return $trx;
            }
            if ($verified['status'] !== 'successful') {
                if (in_array($verified['status'], ['failed', 'cancelled'], true)) {
                    $trx->update(['status' => 'failed']);
                }

                return $trx;
            }
            abort_unless(isset($verified['id']), 400, 'Provider transaction ID missing');
            $providerId = (string) $verified['id'];
            abort_if(Transaction::where('provider_id', $providerId)->where('tx_ref', '!=', $ref)->exists(), 409, 'Provider transaction is already assigned');
            $project = Project::findOrFail((string) $trx->project);
            $wallet = Wallet::where('organization', Ids::object((string) $trx->organization))->firstOrFail();
            $amount = Money::minor($trx->amount);
            $project->donated_amount = Money::major(Money::minor($project->donated_amount) + $amount);
            if (Money::minor($project->donated_amount) >= Money::minor($project->target_amount)) {
                $project->closed = true;
            }
            $project->save();
            $wallet->balance = Money::major(Money::minor($wallet->balance) + $amount);
            $wallet->save();
            $trx->fill(['status' => 'successful', 'provider_id' => $providerId, 'trx_id' => $providerId, 'flw_ref' => $verified['flw_ref'] ?? null, 'creditedAt' => now(), 'checkoutState' => 'settled']);
            $trx->save();
            if ($trx->email) {
                $this->mail->enqueue($trx->email, 'Donation received', 'Thank you. Your donation of NGN '.number_format($trx->amount, 2).' has been received.', null, 'donation:'.$ref);
            }

            return $trx;
        }, 5);
    }
}
