<?php

namespace App\Services;

use App\Models\Outbox;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

final class NotificationService
{
    public function enqueue(string $email, string $subject, string $message, ?string $url = null, ?string $key = null): void
    {
        Outbox::firstOrCreate(['event_key' => $key ?? (string) Str::uuid()], ['recipient' => $email, 'subject' => $subject, 'encrypted_payload' => Crypt::encryptString(json_encode(['message' => $message, 'url' => $url], JSON_THROW_ON_ERROR)), 'availableAt' => now()]);
    }

    public function activation($account, string $token): void
    {
        $path = $account instanceof User ? 'manager_activation_path' : 'activation_path';
        $url = rtrim(config('fundraiser.frontend_url'), '/').config('fundraiser.'.$path).rawurlencode($token);
        $this->enqueue($account->email, 'Activate your account', 'Use the link below to activate your account. This link expires in 24 hours.', $url);
    }
}
