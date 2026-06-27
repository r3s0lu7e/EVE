<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssetSnapshot extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'total' => 'float',
        'assets_value' => 'float',
        'wallet' => 'float',
        'captured_at' => 'datetime',
    ];
}
