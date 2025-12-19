<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'category_id',
        'submitted_by',
        'amount',
        'description',
        'transaction_date',
        'status',
        'approved_by',
        'approved_at',
        'rejection_note',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date',
        'approved_at' => 'datetime',
    ];

    /**
     * The program this expense belongs to
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * The RAB category this expense belongs to
     */
    public function category()
    {
        return $this->belongsTo(RabCategory::class, 'category_id');
    }

    /**
     * The user who submitted this expense
     */
    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * The admin who approved/rejected this expense
     */
    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Receipts for this expense
     */
    public function receipts()
    {
        return $this->hasMany(Receipt::class);
    }

    /**
     * Check if expense is pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if expense is approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if expense is rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
