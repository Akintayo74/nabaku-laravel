<?php

namespace App\Models;

class WithdrawalRequest extends Document
{
    protected $table = 'withdrawalrequests';

    protected $attributes = ['status' => 'pending'];

    protected $casts = ['amount' => 'float', 'reservationApplied' => 'boolean'];

    public function getTable()
    {
        return config('fundraiser.withdrawal_collection', 'withdrawalrequests');
    }
}
