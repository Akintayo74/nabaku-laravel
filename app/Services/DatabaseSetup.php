<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class DatabaseSetup
{
    public function definitions(): array
    {
        return [
            'organizations' => [
                [['email' => 1], ['unique' => true, 'name' => 'email_1']], [['phone' => 1], ['unique' => true, 'name' => 'phone_1']],
                [['isActive' => 1, 'blocked' => 1], ['name' => 'public_organizations']],
            ],
            'users' => [[['email' => 1], ['unique' => true, 'name' => 'email_1']], [['organization' => 1], ['name' => 'organization_1']]],
            'banks' => [[['organization' => 1], ['unique' => true, 'name' => 'organization_1']]],
            'addresses' => [[['organization' => 1], ['unique' => true, 'name' => 'organization_1']]],
            'contacts' => [[['organization' => 1], ['unique' => true, 'name' => 'organization_1']]],
            'cacs' => [[['organization' => 1], ['unique' => true, 'name' => 'organization_1']], [['registration_number' => 1], ['unique' => true, 'name' => 'registration_number_1']], [['registration_certificate' => 1], ['unique' => true, 'name' => 'registration_certificate_1']]],
            'projects' => [[['organization' => 1, 'is_deleted' => 1, 'createdAt' => -1], ['name' => 'organization_projects']]],
            'activities' => [[['organization' => 1, 'createdAt' => -1], ['name' => 'organization_activities']]],
            'transactions' => [
                [['tx_ref' => 1], ['unique' => true, 'name' => 'tx_ref_1']],
                [['provider_id' => 1], ['unique' => true, 'name' => 'provider_payment', 'partialFilterExpression' => ['provider_id' => ['$type' => 'string']]]],
                [['organization' => 1, 'status' => 1, 'createdAt' => -1], ['name' => 'organization_donations']],
                [['project' => 1, 'status' => 1, 'createdAt' => -1], ['name' => 'project_donations']],
            ],
            'wallets' => [[['organization' => 1], ['unique' => true, 'name' => 'organization_1']]],
            config('fundraiser.withdrawal_collection') => [
                [['organization' => 1, 'status' => 1, 'createdAt' => -1], ['name' => 'organization_withdrawals']],
                [['reference' => 1], ['unique' => true, 'name' => 'withdrawal_reference', 'partialFilterExpression' => ['reference' => ['$type' => 'string']]]],
            ],
            'outbox' => [[['event_key' => 1], ['unique' => true, 'name' => 'outbox_event']], [['status' => 1, 'availableAt' => 1], ['name' => 'outbox_pending']]],
        ];
    }

    public function problems(): array
    {
        $db = DB::connection('mongodb')->getMongoDB();
        $problems = [];
        foreach ($this->definitions() as $name => $indexes) {
            foreach ($indexes as [$keys,$options]) {
                if (! ($options['unique'] ?? false)) {
                    continue;
                }
                $field = array_key_first($keys);
                $pipeline = [];
                if (isset($options['partialFilterExpression'])) {
                    $pipeline[] = ['$match' => $options['partialFilterExpression']];
                }
                $pipeline[] = ['$group' => ['_id' => ['$ifNull' => ['$'.$field, null]], 'n' => ['$sum' => 1]]];
                $pipeline[] = ['$match' => ['n' => ['$gt' => 1]]];
                $pipeline[] = ['$limit' => 1];
                if (iterator_count($db->selectCollection($name)->aggregate($pipeline))) {
                    $problems[] = "$name: duplicate or missing values prevent index {$options['name']}";
                }
            }
        }

        return $problems;
    }

    public function apply(): void
    {
        $problems = $this->problems();
        if ($problems) {
            throw new \RuntimeException(implode('; ', $problems));
        }
        $db = DB::connection('mongodb')->getMongoDB();
        foreach ($this->definitions() as $name => $indexes) {
            foreach ($indexes as [$keys,$options]) {
                $db->selectCollection($name)->createIndex($keys, $options);
            }
        }
    }
}
