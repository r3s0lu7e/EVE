<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JournalEntry extends Model
{
    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
        'amount' => 'decimal:2',
        'balance' => 'decimal:2',
    ];
}
