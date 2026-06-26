<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TradeMatch extends Model
{
    protected $guarded = [];

    protected $casts = [
        'sell_date' => 'datetime',
        'quantity' => 'integer',
        'buy_unit_cost' => 'decimal:2',
        'sell_unit_price' => 'decimal:2',
        'gross_profit' => 'decimal:2',
        'sales_tax_alloc' => 'decimal:2',
        'broker_fee_alloc' => 'decimal:2',
        'net_profit' => 'decimal:2',
        'unmatched' => 'boolean',
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
