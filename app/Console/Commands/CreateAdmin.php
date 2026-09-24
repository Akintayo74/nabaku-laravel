<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Atomic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdmin extends Command
{
    protected $signature = 'fundraiser:admin {email} {--name=} {--phone=}';

    protected $description = 'Create an administrator without changing an existing account; password is entered secretly';

    public function handle(): int
    {
        $email = strtolower($this->argument('email'));
        $password = $this->secret('New administrator password');
        $phone = $this->option('phone') ?? $this->ask('Phone');
        $v = Validator::make(compact('email', 'phone', 'password'), ['email' => 'required|email', 'phone' => 'required|string|max:30', 'password' => 'required|string|min:12|max:50']);
        if ($v->fails()) {
            $this->error($v->errors()->first());

            return self::FAILURE;
        }
        if (Organization::where('email', $email)->exists() || User::where('email', $email)->exists()) {
            $this->error('Account already exists; no password or role was changed.');

            return self::FAILURE;
        }
        Atomic::run(function () use ($email, $phone, $password) {
            $o = Organization::create(['email' => $email, 'phone' => $phone, 'name' => $this->option('name') ?? 'Administrator', 'password' => Hash::make($password), 'isActive' => true, 'isAdmin' => true]);
            Wallet::create(['organization' => $o->getKey()]);
        });
        $this->info('Administrator created.');

        return self::SUCCESS;
    }
}
