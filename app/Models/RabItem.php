<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RabItem extends Model
{
    protected $fillable = [
        'program_id',
        'name',
        'category',
        'volume',
        'unit',
        'unit_price',
        'total_budget',
        'status',
        'notes',
    ];

    protected $casts = [
        'volume' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_budget' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function transactionAllocations(): HasMany
    {
        return $this->hasMany(TransactionRabItem::class);
    }

    /**
     * Hitung total realisasi dari semua transaksi
     */
    public function getRealizedAmountAttribute(): float
    {
        return $this->transactionAllocations()->sum('amount');
    }

    /**
     * Hitung sisa anggaran
     */
    public function getRemainingBudgetAttribute(): float
    {
        return $this->total_budget - $this->realized_amount;
    }

    /**
     * Update status berdasarkan realisasi dan nota
     */
    public function updateStatus(): void
    {
        $realized = $this->realized_amount;
        
        // Cek apakah semua transaksi pengeluaran terkait punya nota
        $transactions = $this->transactionAllocations()
            ->with('transaction.receipts')
            ->get()
            ->pluck('transaction')
            ->unique('id')
            ->filter(fn($t) => $t->type === 'expense');

        $allTransactionsHaveReceipts = $transactions->every(fn($t) => $t->receipts->count() > 0);

        if ($realized <= 0) {
            $this->status = 'belum_terpenuhi';
        } elseif ($realized < $this->total_budget) {
            $this->status = 'sebagian_terpenuhi';
        } elseif ($realized == $this->total_budget && $allTransactionsHaveReceipts) {
            $this->status = 'terpenuhi';
        } else {
            $this->status = 'sebagian_terpenuhi';
        }

        $this->save();
    }
}

