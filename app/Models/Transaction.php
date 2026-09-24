<?php

namespace App\Models;

class Transaction extends Document
{
    protected $table = 'transactions';

    protected $attributes = ['status' => 'initiated', 'anonymous' => false, 'type' => 'donation'];

    protected $casts = ['amount' => 'float', 'anonymous' => 'boolean', 'created_at' => 'datetime'];
}
