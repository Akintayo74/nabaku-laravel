<?php

namespace Tests;

use App\Models\Bank;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\DatabaseSetup;
use App\Services\JwtService;
use App\Support\Ids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

abstract class MongoTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $database = DB::connection('mongodb')->getMongoDB();
        if (! app()->environment('testing') || ! str_ends_with($database->getDatabaseName(), '_test')) {
            throw new \RuntimeException('Refusing to clear a non-test database');
        }
        foreach ($database->listCollections() as $collection) {
            $database->selectCollection($collection->getName())->deleteMany([]);
        }
        app(DatabaseSetup::class)->apply();
        Http::preventStrayRequests();
    }

    protected function organization(array $data = []): Organization
    {
        static $n = 0;
        $n++;
        $o = Organization::create([...['email' => "org$n@example.test", 'phone' => '080'.str_pad((string) $n, 8, '0', STR_PAD_LEFT), 'name' => 'Test Organization '.$n, 'password' => Hash::make('password123'), 'isActive' => true], ...$data]);
        Wallet::create(['organization' => $o->getKey()]);

        return $o;
    }

    protected function token($user): array
    {
        return ['Authorization' => 'Bearer '.app(JwtService::class)->issue($user)];
    }

    protected function project(Organization $org, array $data = []): Project
    {
        return Project::create(['organization' => $org->getKey(), 'title' => 'School project', 'description' => 'Build classrooms', 'target_amount' => 10000, ...$data]);
    }

    protected function wallet(Organization $org): Wallet
    {
        return Wallet::where('organization', Ids::object((string) $org->getKey()))->firstOrFail();
    }

    protected function transaction(Organization $org, Project $project, array $data = []): Transaction
    {
        return Transaction::create(['tx_ref' => 'CEOS-'.uniqid(), 'amount' => 1000, 'currency' => 'NGN', 'organization' => $org->getKey(), 'project' => $project->getKey(), 'fullname' => 'Donor', 'email' => 'donor@example.test', ...$data]);
    }

    protected function bank(Organization $org): Bank
    {
        return Bank::create(['organization' => $org->getKey(), 'bankCode' => '044', 'bankName' => 'Test Bank', 'accountNumber' => '0123456789', 'accountName' => 'Test Organization', 'isBankVerified' => true]);
    }
}
