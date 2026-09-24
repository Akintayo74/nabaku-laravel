<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Support\Ids;
use App\Support\Money;
use Illuminate\Console\Command;

class ReconcileBalances extends Command
{
    protected $signature = 'fundraiser:reconcile';

    protected $description = 'Read-only wallet/project reconciliation; discrepancies need investigation, never automatic repair';

    public function handle(): int
    {
        $rows = [];
        $problems = 0;
        foreach (Wallet::cursor() as $w) {
            $id = Ids::object((string) $w->organization);
            $donations = Transaction::where('organization', $id)->where('status', 'successful')->get()->sum(fn ($t) => Money::minor($t->amount));
            $withdrawn = WithdrawalRequest::where('organization', $id)->where('status', 'completed')->get()->sum(fn ($t) => Money::minor($t->amount));
            $reserved = WithdrawalRequest::where('organization', $id)->where('reservationApplied', true)->whereIn('status', ['pending', 'in-progress'])->get()->sum(fn ($t) => Money::minor($t->amount));
            $delta = Money::minor($w->balance) - ($donations - $withdrawn);
            $reservationDelta = Money::minor($w->reserved_balance ?? 0) - $reserved;
            if ($delta || $reservationDelta) {
                $problems++;
            }
            $rows[] = [(string) $id, Money::major($delta), Money::major($reservationDelta)];
        }
        $this->table(['Organization', 'Balance difference (NGN)', 'Reservation difference (NGN)'], $rows);
        foreach (Project::cursor() as $p) {
            $sum = Transaction::where('project', Ids::object((string) $p->getKey()))->where('status', 'successful')->get()->sum(fn ($t) => Money::minor($t->amount));
            if ($sum !== Money::minor($p->donated_amount)) {
                $this->error('Project total mismatch: '.$p->getKey());
                $problems++;
            }
        }
        $legacy = WithdrawalRequest::whereNotIn('status', ['completed', 'cancelled'])->where('reservationApplied', '!=', true)->count();
        if ($legacy) {
            $this->warn("$legacy legacy pending withdrawals require reservation review before cutover.");
            $problems++;
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
