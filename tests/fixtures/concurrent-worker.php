<?php

use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\PaymentService;
use App\Services\WithdrawalService;
use Illuminate\Contracts\Console\Kernel;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || ! str_ends_with(config('database.connections.mongodb.database'), '_test')) {
    exit(2);
}
// Increase overlap so a missing transactional guard is exposed reliably.
Wallet::saving(function () {
    usleep(150000);
});
try {
    if ($argv[1] === 'settle') {
        $t = Transaction::where('tx_ref', $argv[2])->firstOrFail();
        app(PaymentService::class)->settle(['id' => 98765, 'tx_ref' => $t->tx_ref, 'amount' => $t->amount, 'currency' => $t->currency, 'status' => 'successful']);
    } else {
        app(WithdrawalService::class)->create($argv[2], ['amount' => 1000, 'bankId' => $argv[3]]);
    }
    echo 'ok';
} catch (HttpException $e) {
    echo 'http:'.$e->getStatusCode();
} catch (Throwable $e) {
    fwrite(STDERR, $e::class.': '.$e->getMessage());
    exit(1);
}
