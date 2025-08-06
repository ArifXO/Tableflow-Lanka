<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Reservation extends Model
{
    protected $fillable = [
        'user_id',
        'table_id',
        'reservation_date',
        'reservation_time',
        'party_size',
        'status',
        'special_requests',
        'customer_name',
        'customer_email',
        'customer_phone'
    ];

    protected $casts = [
        'reservation_date' => 'date',
    ];

    // Get the user that owns the reservation.
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Get the table for the reservation.

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    // Check if reservation date is valid (not in past, not more than 3 days in future)
    public static function isValidReservationDate($date): bool
    {
        $reservationDate = Carbon::parse($date);
        $today = Carbon::today();
        $maxDate = $today->copy()->addDays(3);

        return $reservationDate->gte($today) && $reservationDate->lte($maxDate);
    }

    // Get available time slots for a date
    public static function getAvailableTimeSlots(): array
    {
        return [
            '16:00', // 4:00 PM
            '17:00', // 5:00 PM
            '18:00', // 6:00 PM
            '19:00', // 7:00 PM
            '20:00', // 8:00 PM
            '21:00', // 9:00 PM
            '22:00', // 10:00 PM
        ];
    }
}
