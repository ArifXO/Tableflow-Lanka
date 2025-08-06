<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'dish_id',
        'quantity',
        'price'
    ];

    protected $casts = [
        'price' => 'decimal:2'
    ];

    //Get the order that owns the order item.
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // Get the dish that belongs to the order item.
    public function dish(): BelongsTo
    {
        return $this->belongsTo(Dish::class);
    }
}
