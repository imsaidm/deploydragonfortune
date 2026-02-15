<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Builder;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

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
     * Role checking helpers
     */
    public function isSuperAdmin(): bool
    {
        return $this->role === 'superAdmin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superAdmin']);
    }

    public function isCreator(): bool
    {
        return in_array($this->role, ['creator', 'admin', 'superAdmin']);
    }

    public function isInvestor(): bool
    {
        return $this->role === 'investor';
    }

    /**
     * Scope for User Management
     */
    public function scopeVisibleTo(Builder $query, User $authenticatedUser): Builder
    {
        if ($authenticatedUser->isSuperAdmin()) {
            return $query;
        }

        return $query->where('role', '!=', 'superAdmin');
    }
}
