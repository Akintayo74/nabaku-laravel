<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;

class User extends Document implements \Illuminate\Contracts\Auth\Authenticatable
{
    protected $table = 'users';

    protected $attributes = ['blocked' => false, 'isActive' => false];

    protected $casts = ['blocked' => 'boolean', 'isActive' => 'boolean', 'resetPasswordExpires' => 'datetime', 'activationExpires' => 'datetime'];

    use Authenticatable;

    public function getAuthPasswordName()
    {
        return 'password';
    }
}
