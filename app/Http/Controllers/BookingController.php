<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
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
        $query = Booking::with('hotel', 'user', 'payment');

        if (!$request->user()->isAdmin()) {
            $query->where('user_id', $request->user()->id);
        } elseif ($request->filled('hotel_id')) {
            $query->where('hotel_id', $request->integer('hotel_id'));
        }

        $bookings = $query->latest()->paginate(10);
        $bookings->getCollection()->each->append('nights');

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
            'guest_name'       => 'nullable|string|max:255',
            'guest_email'      => 'nullable|email|max:255',
            'guest_phone'      => 'nullable|string|max:40',
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

        $overlappingBookings = Booking::where('hotel_id', $hotel->id)
            ->where('room_type', $validated['room_type'])
            ->whereNotIn('status', ['cancelled', 'denied'])
            ->where('check_in', '<', $validated['check_out'])
            ->where('check_out', '>', $validated['check_in'])
            ->count();

        $availableUnits = $this->availableUnitsForRoomType($hotel->id, $validated['room_type']);

        if ($overlappingBookings >= $availableUnits) {
            return response()->json([
                'message' => 'The selected room type is not available for the requested dates.',
            ], 422);
        }

        $booking = Booking::create([
            ...$validated,
            'user_id'     => $request->user()->id,
            'guest_name'  => $validated['guest_name'] ?? $request->user()->name,
            'guest_email' => $validated['guest_email'] ?? $request->user()->email,
            'total_price' => $totalPrice,
            'status'      => 'pending',
        ]);

        return response()->json([
            'message' => 'Booking created. Complete payment to confirm.',
            'booking' => $booking->load('hotel', 'user')->append('nights'),
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

        return response()->json($booking->load('hotel', 'user', 'payment')->append('nights'));
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
                'status'           => 'sometimes|in:pending,awaiting_approval,confirmed,checked_in,checked_out,cancelled,denied',
                'special_requests' => 'nullable|string|max:1000',
                'guest_name'       => 'nullable|string|max:255',
                'guest_email'      => 'nullable|email|max:255',
                'guest_phone'      => 'nullable|string|max:40',
                'room_type'        => 'sometimes|in:standard,deluxe,suite,presidential',
                'check_in'         => 'sometimes|date',
                'check_out'        => 'sometimes|date|after:check_in',
                'guests'           => 'sometimes|integer|min:1|max:10',
            ]);
        } else {
            // User can only cancel or update special requests
            $validated = $request->validate([
                'special_requests' => 'nullable|string|max:1000',
                'guest_name'       => 'nullable|string|max:255',
                'guest_email'      => 'nullable|email|max:255',
                'guest_phone'      => 'nullable|string|max:40',
                'status'           => 'sometimes|in:cancelled',
            ]);

            if (isset($validated['status']) && $booking->check_in->isPast()) {
                return response()->json(['message' => 'Cannot cancel a past booking.'], 422);
            }
        }

        $booking->update($validated);

        return response()->json([
            'message' => 'Booking updated.',
            'booking' => $booking->fresh()->load('hotel', 'user', 'payment')->append('nights'),
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

    private function availableUnitsForRoomType(int $hotelId, string $roomType): int
    {
        $keywords = match ($roomType) {
            'standard' => ['standard'],
            'deluxe' => ['deluxe', 'double queen'],
            'suite' => ['suite', 'loft'],
            'presidential' => ['presidential', 'penthouse'],
            default => [$roomType],
        };

        $query = Room::where('hotel_id', $hotelId)
            ->whereNotIn('status', ['maintenance']);

        $inventoryCount = $query
            ->where(function ($roomQuery) use ($keywords) {
                foreach ($keywords as $keyword) {
                    $roomQuery->orWhere('room_type', 'like', '%' . $keyword . '%');
                }
            })
            ->count();

        return max($inventoryCount, 10);
    }
}
