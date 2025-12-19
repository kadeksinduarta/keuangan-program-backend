<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RabCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'name',
        'allocated_budget',
        'spent_amount',
        'description',
    ];

    protected $casts = [
        'allocated_budget' => 'decimal:2',
        'spent_amount' => 'decimal:2',
    ];

    /**
     * The program this RAB category belongs to
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * Expenses in this category
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'category_id');
    }

    /**
     * Get remaining budget for this category
     */
    public function getRemainingBudgetAttribute()
    {
        return $this->allocated_budget - $this->spent_amount;
    }

    /**
     * Get percentage spent
     */
    public function getPercentageSpentAttribute()
    {
        if ($this->allocated_budget == 0) {
            return 0;
        }
        return ($this->spent_amount / $this->allocated_budget) * 100;
    }

    /**
     * Check if budget is exceeded
     */
    public function isOverBudget(): bool
    {
        return $this->spent_amount > $this->allocated_budget;
    }
}
