<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\Flutterwave;
use App\Services\PaymentService;
use Illuminate\Console\Command;

class ReconcilePayments extends Command
{
    protected $signature = 'fundraiser:payment {reference} {--apply : Settle the verified provider result}';

    protected $description = 'Verify a stored payment reference; optionally apply idempotent settlement';

    public function handle(Flutterwave $flw, PaymentService $payments): int
    {
        $ref = $this->argument('reference');
        $trx = Transaction::where('tx_ref', $ref)->firstOrFail();
        $result = $flw->verifyReference($ref);
        if (($result['tx_ref'] ?? null) !== $trx->tx_ref) {
            $this->error('Reference mismatch');

            return self::FAILURE;
        }
        $this->line(json_encode(array_intersect_key($result, array_flip(['id', 'tx_ref', 'status', 'amount', 'currency'])), JSON_PRETTY_PRINT));
        if ($this->option('apply')) {
            $payments->settle($result);
            $this->info('Verified result applied.');
        }

        return self::SUCCESS;
    }
}
