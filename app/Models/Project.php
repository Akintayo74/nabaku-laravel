<?php

namespace App\Models;

class Project extends Document
{
    protected $table = 'projects';

    protected $attributes = ['donated_amount' => 0, 'is_deleted' => false, 'is_suspended' => false, 'closed' => false, 'media' => []];

    protected $casts = ['target_amount' => 'float', 'donated_amount' => 'float', 'is_deleted' => 'boolean', 'is_suspended' => 'boolean', 'closed' => 'boolean'];
}
