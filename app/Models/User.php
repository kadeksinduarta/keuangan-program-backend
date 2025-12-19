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
     * Programs where this user is the admin
     */
    public function adminPrograms()
    {
        return $this->hasMany(Program::class, 'admin_id');
    }

    /**
     * Programs where this user is a member
     */
    public function programs()
    {
        return $this->belongsToMany(Program::class, 'program_members')
            ->withTimestamps()
            ->withPivot('joined_at');
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
