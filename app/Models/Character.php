<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Character extends Model
{
    protected $primaryKey = 'character_id';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $guarded = [];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'wallet_as_of' => 'datetime',
        'wallet_expires_at' => 'datetime',
        'wallet_next_sync_at' => 'datetime',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'character_id', 'character_id');
    }

    public function journalEntries(): HasMany
    {
        return $this->hasMany(JournalEntry::class, 'character_id', 'character_id');
    }

    public function tradeMatches(): HasMany
    {
        return $this->hasMany(TradeMatch::class, 'character_id', 'character_id');
    }

    public function tokenIsExpiring(int $skewSeconds = 60): bool
    {
        return $this->token_expires_at === null
            || $this->token_expires_at->subSeconds($skewSeconds)->isPast();
    }
}
