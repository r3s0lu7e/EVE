<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $primaryKey = 'transaction_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'date' => 'datetime',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'is_buy' => 'boolean',
    ];

    public function type(): BelongsTo
    {
        return $this->belongsTo(EveType::class, 'type_id', 'type_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id', 'location_id');
    }
}
