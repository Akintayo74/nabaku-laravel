<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDocuments extends Command
{
    protected $signature = 'fundraiser:audit-data';

    protected $description = 'Read-only BSON type, orphan-reference and money precision audit without personal data output';

    public function handle(): int
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $failures = 0;
        $rows = [];
        $references = ['users' => ['organization' => 'organizations'], 'banks' => ['organization' => 'organizations'], 'addresses' => ['organization' => 'organizations'], 'cacs' => ['organization' => 'organizations'], 'contacts' => ['organization' => 'organizations'], 'projects' => ['organization' => 'organizations'], 'activities' => ['organization' => 'organizations'], 'transactions' => ['organization' => 'organizations', 'project' => 'projects'], 'wallets' => ['organization' => 'organizations'], config('fundraiser.withdrawal_collection') => ['organization' => 'organizations', 'project' => 'projects'], 'organizations' => ['bank' => 'banks', 'address' => 'addresses', 'cac' => 'cacs', 'contact' => 'contacts']];
        foreach ($references as $name => $fields) {
            $collection = $db->selectCollection($name);
            foreach (['_id', 'createdAt', 'updatedAt', ...array_keys($fields)] as $field) {
                foreach ($collection->aggregate([['$group' => ['_id' => ['$type' => '$'.$field], 'count' => ['$sum' => 1]]]]) as $group) {
                    $type = $group->_id;
                    $rows[] = [$name, $field, $type, $group->count];
                    $expected = in_array($field, ['createdAt', 'updatedAt']) ? 'date' : 'objectId';
                    $optional = isset($fields[$field]) && ($name === 'organizations' || ($name === config('fundraiser.withdrawal_collection') && $field === 'project'));
                    if ($type !== $expected && ! ($optional && in_array($type, ['missing', 'null']))) {
                        $failures++;
                    }
                }
            }
            foreach ($fields as $field => $target) {
                $orphans = $collection->aggregate([['$match' => [$field => ['$type' => 'objectId']]], ['$lookup' => ['from' => $target, 'localField' => $field, 'foreignField' => '_id', 'as' => '_audit_ref']], ['$match' => ['_audit_ref' => ['$size' => 0]]], ['$count' => 'n']])->toArray();
                if ($orphans) {
                    $this->error("$name.$field: {$orphans[0]->n} orphan references");
                    $failures++;
                }
            }
        }
        foreach (['projects' => ['target_amount', 'donated_amount'], 'transactions' => ['amount'], 'wallets' => ['balance', 'ledger_balance', 'reserved_balance'], config('fundraiser.withdrawal_collection') => ['amount']] as $name => $fields) {
            foreach ($fields as $field) {
                $collection = $db->selectCollection($name);
                $types = $collection->aggregate([['$group' => ['_id' => ['$type' => '$'.$field], 'count' => ['$sum' => 1]]]])->toArray();
                foreach ($types as $type) {
                    $rows[] = [$name, $field, $type->_id, $type->count];
                    if (! in_array($type->_id, ['int', 'long', 'double', 'decimal']) && ! ($field === 'reserved_balance' && $type->_id === 'missing')) {
                        $failures++;
                    }
                }
                $bad = $collection->aggregate([['$match' => [$field => ['$type' => ['int', 'long', 'double', 'decimal']]]], ['$match' => ['$expr' => ['$or' => [['$lt' => ['$'.$field, 0]], ['$gt' => ['$'.$field, 999999999999.99]], ['$ne' => ['$'.$field, ['$round' => ['$'.$field, 2]]]]]]]], ['$count' => 'n']])->toArray();
                if ($bad) {
                    $this->error("$name.$field: {$bad[0]->n} amounts need review (negative, precision or supported range)");
                    $failures++;
                }
            }
        }
        $this->table(['Collection', 'Field', 'BSON type', 'Count'], $rows);

        return $failures ? self::FAILURE : self::SUCCESS;
    }
}
