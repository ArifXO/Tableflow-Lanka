<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    protected $fillable = [
        'user_id',
        'total_amount',
        'status',
        'notes',
    'order_date',
    'loyalty_awarded'
    ];

    protected $casts = [
        'order_date' => 'datetime',
    'total_amount' => 'decimal:2',
    'loyalty_awarded' => 'boolean'
    ];

    // Get the user that owns the order.

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Get the order items for the order.

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function billSplit(): HasOne
    {
        return $this->hasOne(BillSplit::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Mark order as completed and award loyalty points
     *
     * @return bool
     */
    public function markAsCompleted(): bool
    {
        if ($this->status !== 'delivered') {
            $this->update(['status' => 'delivered']);
            $this->awardLoyaltyIfNeeded();

            return true;
        }

        return false;
    }

    /**
     * Award loyalty points if not already awarded.
     * This is triggered either on full payment confirmation or delivery.
     */
    public function awardLoyaltyIfNeeded(): void
    {
        if (!$this->loyalty_awarded) {
            $pointsEarned = $this->calculateLoyaltyPoints();
            $this->user->addLoyaltyPoints($pointsEarned);
            $this->loyalty_awarded = true;
            $this->save();
        }
    }

    /**
     * Calculate loyalty points based on order total
     * 1 point per $1 spent (you can adjust this ratio)
     *
     * @return int
     */
    public function calculateLoyaltyPoints(): int
    {
        return (int) $this->total_amount;
    }

    /**
     * Check if order is completed
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }

    /**
     * Check if order is pending
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /**
     * Check if order is cancelled
     *
     * @return bool
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
