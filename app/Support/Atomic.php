<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use MongoDB\Driver\Exception\RuntimeException;

final class Atomic
{
    /** Retry only transactions the server explicitly identifies as aborted/transient. */
    public static function run(Closure $callback, int $attempts = 5): mixed
    {
        for ($attempt = 0; ; $attempt++) {
            try {
                return DB::connection('mongodb')->transaction($callback, 1);
            } catch (RuntimeException $e) {
                if (! $e->hasErrorLabel('TransientTransactionError') || $attempt >= $attempts - 1) {
                    throw $e;
                }
                DB::connection('mongodb')->rollBack();
                usleep((25000 * (2 ** $attempt)) + random_int(0, 25000));
            }
        }
    }
}
