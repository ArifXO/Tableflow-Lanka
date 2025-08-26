<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillSplit extends Model
{
    protected $fillable = [
        'order_id',
        'total_before_tip',
        'tip_amount',
        'total_after_tip',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BillSplitParticipant::class);
    }

    public function itemParticipants(): HasMany
    {
        return $this->hasMany(BillSplitItemParticipant::class);
    }
}
