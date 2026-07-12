<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private ?Hotel $resolvedHotel = null;

    /** Return everything required by the overview screen in one round trip. */
    public function snapshot(Request $request): JsonResponse
    {
        $request->merge(['limit' => $request->integer('limit', 10)]);
        $hotel = $this->resolveHotel($request);
        $key = implode(':', ['dashboard-snapshot', $request->user()->id, $hotel->id, $request->get('range', 'current')]);

        return response()->json(Cache::remember($key, now()->addSeconds(15), fn () => [
            'overview' => $this->overview($request)->getData(true),
            'revenue' => $this->revenuePerformance($request)->getData(true),
            'recent_bookings' => $this->recentBookings($request)->getData(true),
        ]));
    }

    /**
     * GET /api/admin/dashboard/overview?hotel_id=1
     *
     * Returns the four summary cards shown on the Overview screen:
     * occupancy %, today's check-ins, revenue (MTD), and basic deltas.
     */
    public function overview(Request $request): JsonResponse
    {
        $hotel = $this->resolveHotel($request);

        $roomStats = Room::where('hotel_id', $hotel->id)
            ->selectRaw("COUNT(*) as total_rooms, SUM(CASE WHEN status = 'occupied' THEN 1 ELSE 0 END) as occupied")
            ->first();
        $totalRooms = (int) ($roomStats->total_rooms ?? 0);
        $occupied = (int) ($roomStats->occupied ?? 0);

        $occupancyRate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;

        $checkInStats = Booking::where('hotel_id', $hotel->id)
            ->whereDate('check_in', Carbon::today())
            ->selectRaw("COUNT(*) as expected, SUM(CASE WHEN status IN ('confirmed','checked_in') THEN 1 ELSE 0 END) as actual")
            ->first();
        $todayCheckIns = (int) ($checkInStats->actual ?? 0);
        $expectedToday = (int) ($checkInStats->expected ?? 0);

        $monthStart = Carbon::now()->startOfMonth();
        $todayStart = Carbon::today();
        $yesterdayStart = Carbon::yesterday();
        $revenueStats = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['awaiting_approval', 'confirmed', 'checked_in', 'checked_out'])
            ->where('created_at', '>=', $monthStart)
            ->selectRaw(
                'SUM(total_price) as mtd, SUM(CASE WHEN created_at >= ? THEN total_price ELSE 0 END) as today, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN total_price ELSE 0 END) as yesterday',
                [$todayStart, $yesterdayStart, $todayStart]
            )->first();
        $revenueMtd = (float) ($revenueStats->mtd ?? 0);
        $revenueToday = (float) ($revenueStats->today ?? 0);
        $revenueYesterday = (float) ($revenueStats->yesterday ?? 0);

        $revenueDeltaPct = $revenueYesterday > 0
            ? round((($revenueToday - $revenueYesterday) / $revenueYesterday) * 100, 1)
            : null;

        return response()->json([
            'hotel' => [
                'id'   => $hotel->id,
                'name' => $hotel->name,
            ],
            'occupancy' => [
                'rate'          => $occupancyRate,
                'occupied'      => $occupied,
                'total_rooms'   => $totalRooms,
                'label'         => $occupancyRate >= 80 ? 'Optimal' : ($occupancyRate >= 50 ? 'Moderate' : 'Low'),
            ],
            'check_ins' => [
                'today'    => $todayCheckIns,
                'expected' => $expectedToday,
            ],
            'revenue' => [
                'today'              => round($revenueToday, 2),
                'mtd'                => round($revenueMtd, 2),
                'delta_pct_vs_yesterday' => $revenueDeltaPct,
            ],
        ]);
    }

    /**
     * GET /api/admin/dashboard/revenue-performance?hotel_id=1&range=current|yearly
     *
     * Returns OHLC-style daily revenue data for the candlestick chart
     * (opening, high, low, closing per day).
     */
    public function revenuePerformance(Request $request): JsonResponse
    {
        $hotel = $this->resolveHotel($request);
        $range = $request->get('range', 'current'); // 'current' = this month, 'yearly' = last 12 months

        if ($range === 'yearly') {
            $start = Carbon::now()->subMonths(11)->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
        } else {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
        }

        // Load the compact revenue input once and group it in memory. The old
        // implementation ran two additional queries for every chart period.
        $bookings = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['awaiting_approval', 'confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('created_at', [$start, $end])
            ->orderBy('created_at')
            ->get(['created_at', 'total_price']);

        $series = $bookings
            ->groupBy(fn (Booking $booking) => $range === 'yearly'
                ? $booking->created_at->format('Y-m')
                : $booking->created_at->format('Y-m-d'))
            ->map(function ($periodBookings, $period) {
                $prices = $periodBookings->pluck('total_price')->map(fn ($price) => (float) $price);

                return [
                'date'    => $period,
                'opening' => round((float) $prices->first(), 2),
                'high'    => round((float) $prices->max(), 2),
                'low'     => round((float) $prices->min(), 2),
                'closing' => round((float) $prices->last(), 2),
                'volume'  => $prices->count(),
            ];
            })->values();

        return response()->json([
            'hotel_id' => $hotel->id,
            'range'    => $range,
            'series'   => $series,
        ]);
    }

    /**
     * GET /api/admin/dashboard/recent-bookings?hotel_id=1&limit=5
     *
     * Powers the "Recent bookings" table on the Overview screen.
     */
    public function recentBookings(Request $request): JsonResponse
    {
        $hotel = $this->resolveHotel($request);
        $limit = $request->integer('limit', 5);

        $bookings = Booking::with('user:id,name')
            ->where('hotel_id', $hotel->id)
            ->latest()
            ->limit($limit)
            ->get()
            ->map(function (Booking $booking) {
                return [
                    'id'                => $booking->id,
                    'booking_reference' => $booking->booking_reference,
                    'guest_name'        => $booking->user->name,
                    'guest_initials'    => $this->initials($booking->user->name),
                    'room_type'         => $booking->room_type,
                    'check_in'          => $booking->check_in->format('M d'),
                    'check_out'         => $booking->check_out->format('M d'),
                    'status'            => $booking->status,
                ];
            });

        return response()->json(['bookings' => $bookings]);
    }

    /**
     * GET /api/admin/dashboard/bookings-summary?hotel_id=1
     *
     * Powers the four summary cards on the Bookings Management screen
     * (Total Bookings, Check-ins Today, Occupancy Rate, Revenue MTD).
     */
    public function bookingsSummary(Request $request): JsonResponse
    {
        $bookingQuery = Booking::query();
        $roomQuery = Room::query();
        $hotel = $this->resolveHotel($request);
        $bookingQuery->where('hotel_id', $hotel->id);
        $roomQuery->where('hotel_id', $hotel->id);

        $totalBookings = (clone $bookingQuery)->count();

        $checkInsToday = (clone $bookingQuery)
            ->whereDate('check_in', Carbon::today())
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        $pendingApproval = (clone $bookingQuery)
            ->where('status', 'awaiting_approval')
            ->count();

        $totalRooms = (clone $roomQuery)->count();
        $occupied   = (clone $roomQuery)->where('status', 'occupied')->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;

        $revenueMtd = (clone $bookingQuery)
            ->whereIn('status', ['awaiting_approval', 'confirmed', 'checked_in', 'checked_out'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_price');

        return response()->json([
            'total_bookings' => $totalBookings,
            'check_ins_today' => $checkInsToday,
            'pending_approval' => $pendingApproval,
            'occupancy_rate' => $occupancyRate,
            'revenue_mtd' => round($revenueMtd, 2),
        ]);
    }

    /**
     * Resolve which hotel to report on.
     * Accepts ?hotel_id=, falls back to the admin's first managed hotel,
     * else the first hotel in the system (single-property setups).
     */
    private function resolveHotel(Request $request): Hotel
    {
        if ($this->resolvedHotel) {
            return $this->resolvedHotel;
        }

        $admin = $request->user();
        if (!$admin->isSuperAdmin()) {
            abort_unless($admin->hotel_id, 422, 'Your admin account is not assigned to a hotel.');
            if ($request->filled('hotel_id')) {
                abort_unless($request->integer('hotel_id') === (int) $admin->hotel_id, 403, 'You cannot access another hotel.');
            }
            return $this->resolvedHotel = Hotel::findOrFail($admin->hotel_id);
        }
        if ($request->filled('hotel_id')) {
            return $this->resolvedHotel = Hotel::findOrFail($request->get('hotel_id'));
        }

        return $this->resolvedHotel = Hotel::orderBy('id')->firstOrFail();
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $first = $parts[0][0] ?? '';
        $last  = $parts[count($parts) - 1][0] ?? '';

        return strtoupper($first . $last);
    }
}
