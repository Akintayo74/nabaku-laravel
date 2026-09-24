<?php

namespace App\Models;

class Outbox extends Document
{
    protected $table = 'outbox';

    protected $attributes = ['status' => 'pending', 'attempts' => 0];

    protected $casts = ['availableAt' => 'datetime', 'lockedAt' => 'datetime', 'sentAt' => 'datetime'];
}
