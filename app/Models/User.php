<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'full_name',
        'phone',
        'avatar',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Programs where this user has a role
     */
    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_user_roles')
            ->withTimestamps()
            ->withPivot('role', 'status');
    }

    /**
     * Check if user belongs to a program
     */
    public function belongsToProgram($programId): bool
    {
        if (!is_numeric($programId)) return false;
        return $this->programs()->where('program_id', $programId)->where('program_user_roles.status', 'approved')->exists();
    }

    /**
     * Expenses submitted by this user
     */
    public function expenses()
    {
        return $this->hasMany(Expense::class, 'submitted_by');
    }

    /**
     * Expenses approved by this user
     */
    public function approvedExpenses()
    {
        return $this->hasMany(Expense::class, 'approved_by');
    }

    /**
     * Activity logs for this user
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * Notifications for this user
     */
    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Check if user is admin
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a member
     */
    public function isAnggota(): bool
    {
        return $this->role === 'anggota';
    }
}
