<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transaction extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'program_id',
        'expense_id',
        'type',
        'date',
        'amount',
        'description',
        'created_by',
    ];

    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
    }

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function rabAllocations(): HasMany
    {
        return $this->hasMany(TransactionRabItem::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Cek apakah transaksi ini adalah pengeluaran
     */
    public function isExpense(): bool
    {
        return $this->type === 'expense';
    }

    /**
     * Cek apakah transaksi ini adalah pemasukan
     */
    public function isIncome(): bool
    {
        return $this->type === 'income';
    }

    /**
     * Cek apakah nota sudah lengkap (untuk pengeluaran)
     */
    public function hasCompleteReceipts(): bool
    {
        if (!$this->isExpense()) {
            return true; // Pemasukan tidak perlu nota
        }
        
        return $this->receipts()->count() > 0;
    }
}

