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



    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }



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


    public function markAsCompleted(): bool
    {
        if ($this->status !== 'delivered') {
            $this->update(['status' => 'delivered']);
            $this->awardLoyaltyIfNeeded();

            return true;
        }

        return false;
    }


    public function awardLoyaltyIfNeeded(): void
    {
        if (!$this->loyalty_awarded) {
            $pointsEarned = $this->calculateLoyaltyPoints();
            $this->user->addLoyaltyPoints($pointsEarned);
            $this->loyalty_awarded = true;
            $this->save();
        }
    }


    public function calculateLoyaltyPoints(): int
    {
        return (int) $this->total_amount;
    }


    public function isCompleted(): bool
    {
        return $this->status === 'delivered';
    }


    public function isPending(): bool
    {
        return $this->status === 'pending';
    }


    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
