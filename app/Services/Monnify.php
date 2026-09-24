<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

final class Monnify
{
    public function balance(): array
    {
        abort_unless(config('services.monnify.key') && config('services.monnify.secret') && config('services.monnify.wallet'), 503, 'Monnify is not configured');
        try {
            $client = Http::baseUrl(config('services.monnify.url'))->acceptJson()->connectTimeout(5)->timeout(30);
            $auth = (clone $client)->withBasicAuth(config('services.monnify.key'), config('services.monnify.secret'))->post('/api/v1/auth/login');
            abort_unless($auth->successful() && $auth->json('responseBody.accessToken'), 502, 'Monnify authentication failed');
            $result = $client->withToken($auth->json('responseBody.accessToken'))->get('/api/v2/disbursements/wallet-balance', ['accountNumber' => config('services.monnify.wallet')]);
            abort_unless($result->successful() && $result->json('requestSuccessful'), 502, 'Monnify balance request failed');

            return $result->json('responseBody');
        } catch (ConnectionException $e) {
            abort(502, 'Monnify unavailable');
        }
    }
}
