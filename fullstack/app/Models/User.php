<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

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
        'loyalty_points',
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
     * Get the reservations for the user.
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * Get the orders for the user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Add loyalty points to the user
     *
     * @param int $points
     * @return void
     */
    public function addLoyaltyPoints(int $points): void
    {
        $this->increment('loyalty_points', $points);
    }

    /**
     * Deduct loyalty points from the user
     *
     * @param int $points
     * @return bool
     */
    public function deductLoyaltyPoints(int $points): bool
    {
        if ($this->loyalty_points >= $points) {
            $this->decrement('loyalty_points', $points);
            return true;
        }
        return false;
    }

    /**
     * Get the user's current loyalty points
     *
     * @return int
     */
    public function getLoyaltyPoints(): int
    {
        return $this->loyalty_points ?? 0;
    }

    /**
     * Check if user has enough loyalty points
     *
     * @param int $requiredPoints
     * @return bool
     */
    public function hasEnoughLoyaltyPoints(int $requiredPoints): bool
    {
        return $this->getLoyaltyPoints() >= $requiredPoints;
    }

    /**
     * Role helpers
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isKitchen(): bool
    {
        return $this->role === 'kitchen';
    }

    public function isDiner(): bool
    {
        return $this->role === 'diner';
    }
}
