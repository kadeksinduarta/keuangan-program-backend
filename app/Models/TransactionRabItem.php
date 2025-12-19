<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRabItem extends Model
{
    protected $fillable = [
        'transaction_id',
        'rab_item_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function rabItem(): BelongsTo
    {
        return $this->belongsTo(RabItem::class);
    }
}

