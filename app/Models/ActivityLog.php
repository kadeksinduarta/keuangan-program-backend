<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'program_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The program this log belongs to
     */
    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    /**
     * The user who performed this action
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
