<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'order_id', 'amount', 'tip_amount', 'method', 'status', 'meta'
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
        'tip_amount' => 'decimal:2'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
