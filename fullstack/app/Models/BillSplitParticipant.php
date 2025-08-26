<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class BillSplitParticipant extends Model
{
    protected $fillable = [
        'bill_split_id',
        'name',
        'share_before_tip',
        'share_tip',
        'share_total',
        'paid'
    ];

    protected $casts = [
        'paid' => 'boolean'
    ];

    public function billSplit(): BelongsTo
    {
        return $this->belongsTo(BillSplit::class);
    }

    public function orderItems(): BelongsToMany
    {
        return $this->belongsToMany(OrderItem::class, 'bill_split_item_participant', 'participant_id', 'order_item_id')->withTimestamps();
    }
}
