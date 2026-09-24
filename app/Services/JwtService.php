<?php

namespace App\Services;

use App\Models\Document;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

final class JwtService
{
    private function secret(): string
    {
        $secret = config('fundraiser.jwt_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 32, 503, 'JWT signing is not configured');

        return $secret;
    }

    public function issue(Document $user): string
    {
        return JWT::encode(['id' => (string) $user->getKey(), 'email' => $user->email, 'name' => $user->name, 'iat' => time(), 'exp' => time() + config('fundraiser.jwt_ttl')], $this->secret(), 'HS256');
    }

    public function decode(string $token): object
    {
        return JWT::decode($token, new Key($this->secret(), 'HS256'));
    }
}
