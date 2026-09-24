<?php

namespace App\Console\Commands;

use App\Services\DatabaseSetup;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DatabasePreflight extends Command
{
    protected $signature = 'fundraiser:preflight';

    protected $description = 'Read-only MongoDB topology, counts, index and duplicate audit';

    public function handle(DatabaseSetup $setup): int
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $hello = $db->command(['hello' => 1])->toArray()[0];
        $transactionCapable = isset($hello->setName) || ($hello->msg ?? null) === 'isdbgrid';
        $this->line('Database: '.$db->getDatabaseName());
        $this->line('Transaction topology: '.($transactionCapable ? 'available' : 'MISSING'));
        $rows = [];
        foreach ($db->listCollections() as $c) {
            $collection = $db->selectCollection($c->getName());
            $rows[] = [$c->getName(), $collection->countDocuments(), iterator_count($collection->listIndexes())];
        }
        $this->table(['Collection', 'Documents', 'Indexes'], $rows);
        $problems = $setup->problems();
        foreach ($problems as $problem) {
            $this->error($problem);
        }

        return $transactionCapable && ! $problems ? self::SUCCESS : self::FAILURE;
    }
}
