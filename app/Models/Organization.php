<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;

class Organization extends Document implements \Illuminate\Contracts\Auth\Authenticatable
{
    protected $table = 'organizations';

    protected $attributes = ['blocked' => false, 'isActive' => false, 'isAdmin' => false, 'description' => '', 'type' => 'faith-based', 'emails' => [], 'profile_step' => 1];

    protected $casts = ['blocked' => 'boolean', 'isActive' => 'boolean', 'isAdmin' => 'boolean', 'profile_step' => 'integer', 'resetPasswordExpires' => 'datetime', 'activationExpires' => 'datetime'];

    use Authenticatable;

    public function getAuthPasswordName()
    {
        return 'password';
    }
}
