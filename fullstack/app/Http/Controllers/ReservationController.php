<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Table;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ReservationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        return Inertia::render('Reservation/ReservIndex', [
            'tables' => Table::where('is_active', true)->get(),
            'timeSlots' => Reservation::getAvailableTimeSlots(),
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }

    /**
     * Get table availability for a specific date and time
     */
    public function getTableAvailability(Request $request)
    {
        $request->validate([
            'date' => 'required|date',
            'time' => 'required|string',
        ]);

        $date = $request->date;
        $time = $request->time;

        // Check if the date is valid (not in past, not more than 3 days in future)
        if (!Reservation::isValidReservationDate($date)) {
            return response()->json([
                'error' => 'Invalid reservation date. You can only book tables up to 3 days in advance and not in the past.'
            ], 400);
        }

        $tables = Table::where('is_active', true)->get()->map(function ($table) use ($date, $time) {
            $isAvailable = $table->isAvailableAt($date, $time);

            return [
                'id' => $table->id,
                'number' => $table->number,
                'seats' => $table->seats,
                'description' => $table->description,
                'isAvailable' => $isAvailable,
            ];
        });

        return response()->json([
            'tables' => $tables,
            'date' => $date,
            'time' => $time,
        ]);
    }

    //Store a new reservation
    public function store(Request $request)
    {
        $request->validate([
            'table_id' => 'required|exists:tables,id',
            'reservation_date' => 'required|date',
            'reservation_time' => 'required|string',
            'party_size' => 'required|integer|min:1|max:20',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:20',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        // Check if the date is valid
        if (!Reservation::isValidReservationDate($request->reservation_date)) {
            throw ValidationException::withMessages([
                'reservation_date' => 'You can only book tables up to 3 days in advance and not in the past.'
            ]);
        }

        // Check if the table is available
        $table = Table::findOrFail($request->table_id);
        if (!$table->isAvailableAt($request->reservation_date, $request->reservation_time)) {
            throw ValidationException::withMessages([
                'table_id' => 'This table is not available at the selected date and time.'
            ]);
        }

        // Check if party size fits the table
        if ($request->party_size > $table->seats) {
            throw ValidationException::withMessages([
                'party_size' => "This table can only accommodate {$table->seats} guests. Your party size is {$request->party_size}."
            ]);
        }

        // Create the reservation
        $reservation = Reservation::create([
            'user_id' => Auth::id() ?? 1, // Use authenticated user or default guest user
            'table_id' => $request->table_id,
            'reservation_date' => $request->reservation_date,
            'reservation_time' => $request->reservation_time,
            'party_size' => $request->party_size,
            'customer_name' => $request->customer_name,
            'customer_email' => $request->customer_email,
            'customer_phone' => $request->customer_phone,
            'special_requests' => $request->special_requests,
            'status' => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Reservation created successfully!',
            'reservation' => $reservation->load('table'),
        ], 201);
    }

    // Cancel a reservation
    public function cancel(Request $request, $id)
    {
        $userId = Auth::id();

        $reservation = Reservation::where('id', $id)
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->firstOrFail();

        // Check if reservation is in the future
        $reservationDateTime = Carbon::parse($reservation->reservation_date->format('Y-m-d') . ' ' . $reservation->reservation_time);
        if ($reservationDateTime->isPast()) {
            throw ValidationException::withMessages([
                'reservation' => 'Cannot cancel past reservations.'
            ]);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Reservation cancelled successfully!',
        ]);
    }
}
