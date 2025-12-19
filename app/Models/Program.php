<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Program extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'period_start',
        'period_end',
        'status',
        'created_by',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
    ];

    /**
     * User yang membuat program
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * User roles dalam program ini
     */
    public function userRoles(): HasMany
    {
        return $this->hasMany(ProgramUserRole::class);
    }

    /**
     * RAB items untuk program ini
     */
    public function rabItems(): HasMany
    {
        return $this->hasMany(RabItem::class);
    }

    /**
     * Transaksi untuk program ini
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Members of the program
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'program_user_roles')
            ->withPivot('role')
            ->withTimestamps();
    }

    /**
     * Audit logs untuk program ini
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'module_id')
            ->where('module', 'PROGRAM');
    }

    /**
     * Hitung total anggaran RAB
     */
    public function getTotalBudgetAttribute(): float
    {
        return $this->rabItems()->sum('total_budget');
    }

    /**
     * Hitung total pemasukan
     */
    public function getTotalIncomeAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'income')
            ->sum('amount');
    }

    /**
     * Hitung total pengeluaran
     */
    public function getTotalExpenseAttribute(): float
    {
        return $this->transactions()
            ->where('type', 'expense')
            ->sum('amount');
    }

    /**
     * Hitung sisa saldo
     */
    public function getBalanceAttribute(): float
    {
        return $this->total_income - $this->total_expense;
    }

    /**
     * Cek apakah program sudah aktif (boleh input transaksi)
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Cek apakah program masih draft (belum boleh input transaksi)
     */
    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    /**
     * Cek apakah program sudah punya RAB
     */
    public function hasRab(): bool
    {
        return $this->rabItems()->count() > 0;
    }

    /**
     * Validasi apakah boleh input transaksi
     */
    public function canAddTransaction(): bool
    {
        return $this->isActive() && $this->hasRab();
    }
}
