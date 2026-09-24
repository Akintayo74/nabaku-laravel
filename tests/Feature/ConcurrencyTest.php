<?php

namespace Tests\Feature;

use App\Models\Outbox;
use App\Models\WithdrawalRequest;
use Symfony\Component\Process\Process;
use Tests\MongoTestCase;

class ConcurrencyTest extends MongoTestCase
{
    private function runPair(array $args): array
    {
        $command = [PHP_BINARY];
        if ($extension = getenv('TEST_PHP_EXTENSION')) {
            array_push($command, '-d', 'extension='.$extension);
        }
        array_push($command, base_path('tests/fixtures/concurrent-worker.php'), ...$args);
        $env = ['APP_ENV' => 'testing', 'MONGODB_DATABASE' => config('database.connections.mongodb.database'), 'MONGODB_URI' => config('database.connections.mongodb.dsn'), 'APP_KEY' => config('app.key'), 'JWT_SECRET' => config('fundraiser.jwt_secret'), 'LOG_CHANNEL' => 'null'];
        $a = new Process($command, base_path(), $env);
        $b = new Process($command, base_path(), $env);
        $a->start();
        $b->start();
        $a->wait();
        $b->wait();
        $this->assertTrue($a->isSuccessful(), $a->getErrorOutput());
        $this->assertTrue($b->isSuccessful(), $b->getErrorOutput());

        return [$a->getOutput(), $b->getOutput()];
    }

    public function test_concurrent_duplicate_payments_credit_once(): void
    {
        $o = $this->organization();
        $p = $this->project($o);
        $t = $this->transaction($o, $p);
        $this->assertSame(['ok', 'ok'], $this->runPair(['settle', $t->tx_ref]));
        $this->assertSame(1000.0, $this->wallet($o)->balance);
        $this->assertSame(1000.0, $p->fresh()->donated_amount);
        $this->assertSame(1, Outbox::count());
    }

    public function test_concurrent_withdrawals_reserve_only_once(): void
    {
        $o = $this->organization();
        $b = $this->bank($o);
        $this->wallet($o)->update(['balance' => 1500]);
        $results = $this->runPair(['withdraw', (string) $o->getKey(), (string) $b->getKey()]);
        sort($results);
        $this->assertSame(['http:409', 'ok'], $results);
        $this->assertSame(1, WithdrawalRequest::count());
        $this->assertSame(1000.0, $this->wallet($o)->reserved_balance);
    }
}
