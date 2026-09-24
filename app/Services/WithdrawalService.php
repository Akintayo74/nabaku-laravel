<?php

namespace App\Services;

use App\Models\Bank;
use App\Models\Organization;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Support\Atomic;
use App\Support\Ids;
use App\Support\Money;
use Illuminate\Support\Str;

final class WithdrawalService
{
    public function create(string $organization, array $v): WithdrawalRequest
    {
        $amount = Money::minor($v['amount']);
        abort_unless($amount >= 1000, 400, 'Minimum withdrawal is NGN 10');

        return Atomic::run(function () use ($organization, $v, $amount) {
            $org = Organization::findOrFail($organization);
            $bank = Bank::findOrFail((string) Ids::object($v['bankId']));
            abort_unless((string) $bank->organization === $organization && $bank->isBankVerified, 403, 'Select a verified bank belonging to your organization');
            abort_if(WithdrawalRequest::where('organization', Ids::object($organization))->whereNotIn('status', ['completed', 'cancelled'])->exists(), 409, 'Withdrawal is currently ongoing.');
            $wallet = Wallet::where('organization', Ids::object($organization))->firstOrFail();
            $reserved = Money::minor($wallet->reserved_balance ?? 0);
            abort_unless(Money::minor($wallet->balance) - $reserved >= $amount, 409, 'Insufficient available balance');
            $wallet->reserved_balance = Money::major($reserved + $amount);
            $wallet->save();

            return WithdrawalRequest::create(['organization' => $organization, 'email' => $org->email, 'amount' => Money::major($amount), 'reference' => 'CEOS-W-'.Str::uuid(), 'bank' => $bank->getKey(), 'bank_snapshot' => ['bankCode' => $bank->bankCode, 'bankName' => $bank->bankName, 'accountNumber' => $bank->accountNumber, 'accountName' => $bank->accountName], 'reservationApplied' => true]);
        }, 5);
    }

    public function transition(string $id, string $status): WithdrawalRequest
    {
        return Atomic::run(function () use ($id, $status) {
            $request = WithdrawalRequest::findOrFail((string) Ids::object($id));
            if ($request->status === $status) {
                return $request;
            }
            abort_if(in_array($request->status, ['completed', 'cancelled'], true), 409, 'Withdrawal is already finalized');
            $allowed = ['pending' => ['in-progress', 'completed', 'cancelled'], 'in-progress' => ['completed', 'cancelled']];
            abort_unless(in_array($status, $allowed[$request->status] ?? [], true), 409, 'Invalid withdrawal transition');
            $wallet = Wallet::where('organization', Ids::object((string) $request->organization))->firstOrFail();
            $amount = Money::minor($request->amount);
            $balance = Money::minor($wallet->balance);
            $reserved = Money::minor($wallet->reserved_balance ?? 0);
            $wasReserved = (bool) $request->reservationApplied;
            // Adopt pre-cutover pending requests only with sufficient available funds.
            if (! $wasReserved && $status !== 'cancelled') {
                abort_unless($balance - $reserved >= $amount, 409, 'Insufficient available balance');
                $reserved += $amount;
                $wasReserved = true;
            }
            if ($status === 'completed') {
                abort_unless($balance >= $amount && $reserved >= $amount, 409, 'Wallet reconciliation required');
                $balance -= $amount;
                $reserved -= $amount;
            } elseif ($status === 'cancelled' && $wasReserved) {
                abort_unless($reserved >= $amount, 409, 'Wallet reconciliation required');
                $reserved -= $amount;
            }
            $wallet->update(['balance' => Money::major($balance), 'reserved_balance' => Money::major($reserved)]);
            $request->update(['status' => $status, 'reservationApplied' => $status === 'in-progress' && $wasReserved, 'completedAt' => $status === 'completed' ? now() : null]);
            if ($request->email) {
                app(NotificationService::class)->enqueue($request->email, 'Withdrawal '.$status, 'Your withdrawal '.$request->reference.' is '.$status.'.', null, 'withdrawal:'.$request->reference.':'.$status);
            }

            return $request;
        }, 5);
    }
}
