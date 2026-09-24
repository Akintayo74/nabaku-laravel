<?php

namespace App\Console\Commands;

use App\Services\DatabaseSetup;
use Illuminate\Console\Command;

class EnsureIndexes extends Command
{
    protected $signature = 'fundraiser:indexes {--apply : Apply additive indexes after a backup and preflight}';

    protected $description = 'Preview or apply MongoDB indexes; never deletes documents or existing indexes';

    public function handle(DatabaseSetup $setup): int
    {
        if (! $this->option('apply')) {
            foreach ($setup->definitions() as $collection => $indexes) {
                foreach ($indexes as [$keys,$options]) {
                    $this->line($collection.' '.json_encode([$keys, $options]));
                }
            }

            return self::SUCCESS;
        }
        try {
            $setup->apply();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Indexes applied. No data was rewritten.');

        return self::SUCCESS;
    }
}
