<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplit extends Model
{
    protected $fillable = [
        'order_id', 'participants', 'total_before_tip', 'tip_amount', 'total_after_tip'
    ];

    protected $casts = [
        'participants' => 'array',
        'total_before_tip' => 'decimal:2',
        'tip_amount' => 'decimal:2',
        'total_after_tip' => 'decimal:2'
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
