<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BookingController extends Controller
{
    /**
     * GET /api/bookings
     * - user  → own bookings only
     * - admin → all bookings
     */
    public function index(Request $request): JsonResponse
    {
        $query = Booking::with('hotel', 'user');

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        }

        $bookings = $query->latest()->paginate(10);

        return response()->json($bookings);
    }

    /**
     * POST /api/bookings  (user & admin)
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hotel_id'         => 'required|exists:hotels,id',
            'check_in'         => 'required|date|after_or_equal:today',
            'check_out'        => 'required|date|after:check_in',
            'guests'           => 'required|integer|min:1|max:10',
            'room_type'        => 'required|in:standard,deluxe,suite,presidential',
            'special_requests' => 'nullable|string|max:1000',
        ]);

        $hotel      = Hotel::findOrFail($validated['hotel_id']);
        $checkIn    = Carbon::parse($validated['check_in']);
        $checkOut   = Carbon::parse($validated['check_out']);
        $nights     = $checkIn->diffInDays($checkOut);

        $multipliers = [
            'standard'     => 1.0,
            'deluxe'       => 1.3,
            'suite'        => 1.8,
            'presidential' => 3.0,
        ];

        $totalPrice = $hotel->price_per_night * $nights * $multipliers[$validated['room_type']];

        $conflict = Booking::where('hotel_id', $hotel->id)
            ->where('room_type', $validated['room_type'])
            ->where('status', '!=', 'cancelled')
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->exists();

        if ($conflict) {
            return response()->json([
                'message' => 'The selected room type is not available for the requested dates.',
            ], 422);
        }

        $booking = Booking::create([
            ...$validated,
            'user_id'     => $request->user()->id,
            'total_price' => $totalPrice,
            'status'      => 'confirmed',
        ]);

        return response()->json([
            'message' => 'Booking confirmed.',
            'booking' => $booking->load('hotel'),
        ], 201);
    }

    /**
     * GET /api/bookings/{booking}
     * - user  → own booking only
     * - admin → any booking
     */
    public function show(Request $request, Booking $booking): JsonResponse
    {
        if (!$request->user()->isAdmin() && $booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json($booking->load('hotel', 'user'));
    }

    /**
     * PUT /api/bookings/{booking}
     * - user  → own booking, can only cancel or update special_requests
     * - admin → any booking, can change any status
     */
    public function update(Request $request, Booking $booking): JsonResponse
    {
        if (!$request->user()->isAdmin() && $booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($request->user()->isAdmin()) {
            // Admin can update anything
            $validated = $request->validate([
                'status'           => 'sometimes|in:pending,confirmed,checked_in,checked_out,cancelled',
                'special_requests' => 'nullable|string|max:1000',
                'room_type'        => 'sometimes|in:standard,deluxe,suite,presidential',
                'check_in'         => 'sometimes|date',
                'check_out'        => 'sometimes|date|after:check_in',
                'guests'           => 'sometimes|integer|min:1|max:10',
            ]);
        } else {
            // User can only cancel or update special requests
            $validated = $request->validate([
                'special_requests' => 'nullable|string|max:1000',
                'status'           => 'sometimes|in:cancelled',
            ]);

            if (isset($validated['status']) && $booking->check_in->isPast()) {
                return response()->json(['message' => 'Cannot cancel a past booking.'], 422);
            }
        }

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated.',
            'booking' => $booking->fresh()->load('hotel'),
        ]);
    }

    /**
     * DELETE /api/bookings/{booking}
     * - user  → own booking, only future
     * - admin → any booking
     */
    public function destroy(Request $request, Booking $booking): JsonResponse
    {
        if (!$request->user()->isAdmin() && $booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (!$request->user()->isAdmin() && $booking->check_in->isPast()) {
            return response()->json(['message' => 'Cannot delete a past booking.'], 422);
        }

        $booking->delete();

        return response()->json(['message' => 'Booking cancelled and deleted.']);
    }
}
