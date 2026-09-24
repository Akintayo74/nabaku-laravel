<?php

namespace App\Models;

class Wallet extends Document
{
    protected $table = 'wallets';

    protected $attributes = ['balance' => 0, 'ledger_balance' => 0, 'reserved_balance' => 0];

    protected $casts = ['balance' => 'float', 'ledger_balance' => 'float', 'reserved_balance' => 'float'];
}
