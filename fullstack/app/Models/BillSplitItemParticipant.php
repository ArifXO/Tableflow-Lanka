<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillSplitItemParticipant extends Model
{
    protected $fillable = [
        'bill_split_id',
        'participant_id',
        'order_item_id'
    ];

    public function billSplit(): BelongsTo
    {
        return $this->belongsTo(BillSplit::class);
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(BillSplitParticipant::class, 'participant_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
