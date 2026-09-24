<?php

namespace App\Models;

class Bank extends Document
{
    protected $table = 'banks';

    protected $attributes = ['isBankVerified' => false];

    protected $casts = ['isBankVerified' => 'boolean'];
}
