<?php

namespace Tests\Feature;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;
use Tests\MongoTestCase;

class NodeContractTest extends MongoTestCase
{
    public function test_captured_node_response_contracts_with_documented_corrections(): void
    {
        $fixtures = json_decode(file_get_contents(base_path('tests/fixtures/node-baseline.json')), true);
        $byUrl = array_column($fixtures, null, 'url');
        $documents = [
            'organizations' => $byUrl['/api/v1/authentication/login']['body']['data'],
            'projects' => $byUrl['/api/v1/project/random']['body']['data'][0],
            'activities' => $byUrl['/api/v1/activity/public']['body']['data'][0],
            'wallets' => $byUrl['/api/v1/wallet']['body']['data'],
        ];
        foreach ($documents as $collection => $doc) {
            unset($doc['id']);
            foreach (['_id', 'organization'] as $key) {
                if (isset($doc[$key])) {
                    $doc[$key] = new ObjectId($doc[$key]);
                }
            }
            foreach (['createdAt', 'updatedAt'] as $key) {
                if (isset($doc[$key])) {
                    $doc[$key] = new UTCDateTime(new \DateTimeImmutable($doc[$key]));
                }
            }
            if ($collection === 'organizations') {
                $doc['password'] = Hash::make('password123');
            }
            DB::connection()->getMongoDB()->selectCollection($collection)->insertOne($doc);
        }
        $org = Organization::findOrFail('507f1f77bcf86cd799439012');
        foreach ($fixtures as $fixture) {
            $url = $fixture['url'];
            $expected = $fixture['body'];
            $body = $fixture['method'] === 'POST' ? ['id' => 'baseline@example.test', 'password' => 'password123'] : [];
            $actual = $this->json($fixture['method'], $url, $body, $this->token($org))->assertStatus($fixture['status'])->json();
            if (isset($actual['authorization'])) {
                $actual['authorization']['token'] = '<dynamic-token>';
            }
            if ($url === '/api/v1/authentication/get-profile') {
                foreach (['bank', 'address', 'cac', 'contact'] as $field) {
                    $expected['data'][$field] = null;
                }
            }
            if ($url === '/api/v1/activity/public') {
                $expected['pagination'] = ['prevPage' => null, 'currentPage' => 1, 'nextPage' => null, 'pageTotal' => 1, 'pageSize' => 1, 'total' => 1];
            }
            if ($url === '/api/v1/wallet') {
                $expected['data']['id'] = $expected['data']['_id'];
                $expected['data']['reserved_balance'] = 0;
                $expected['data']['available_balance'] = 0;
            }
            if ($url === '/api/v1/global/search') {
                $public = array_intersect_key($expected['organizations']['data'][0], array_flip(['_id', 'id', 'name', 'description', 'profile_image', 'cover_image', 'type', 'createdAt', 'updatedAt']));
                $public['contact'] = null;
                $public['address'] = null;
                $expected['organizations']['data'] = [$public];
            }
            $this->assertEquals($expected, $actual, $url);
        }
    }
}
