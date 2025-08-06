<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Table extends Model
{
    protected $fillable = [
        'number',
        'seats',
        'is_active',
        'description'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // Get the reservations for the table.

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    // Check if table is available for a specific date and time
    public function isAvailableAt($date, $time): bool
    {
        // Check if there's an existing reservation
        $hasReservation = $this->reservations()
            ->whereDate('reservation_date', $date)
            ->where('reservation_time', $time)
            ->where('status', 'confirmed')
            ->exists();

        return !$hasReservation;
    }
}
