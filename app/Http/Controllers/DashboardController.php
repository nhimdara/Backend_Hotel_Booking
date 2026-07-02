<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * GET /api/admin/dashboard/overview?hotel_id=1
     *
     * Returns the four summary cards shown on the Overview screen:
     * occupancy %, today's check-ins, revenue (MTD), and basic deltas.
     */
    public function overview(Request $request): JsonResponse
    {
        $hotel = $this->resolveHotel($request);

        $totalRooms = Room::where('hotel_id', $hotel->id)->count();
        $occupied   = Room::where('hotel_id', $hotel->id)->where('status', 'occupied')->count();

        $occupancyRate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;

        $todayCheckIns = Booking::where('hotel_id', $hotel->id)
            ->whereDate('check_in', Carbon::today())
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        $expectedToday = Booking::where('hotel_id', $hotel->id)
            ->whereDate('check_in', Carbon::today())
            ->count();

        $revenueMtd = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_price');

        $revenueYesterday = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereDate('created_at', Carbon::yesterday())
            ->sum('total_price');

        $revenueToday = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereDate('created_at', Carbon::today())
            ->sum('total_price');

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
            $groupFormat = '%Y-%m';
        } else {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
            $groupFormat = '%Y-%m-%d';
        }

        $rows = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereBetween('created_at', [$start, $end])
            ->select([
                DB::raw("DATE_FORMAT(created_at, '{$groupFormat}') as period"),
                DB::raw('MIN(total_price) as low'),
                DB::raw('MAX(total_price) as high'),
                DB::raw('SUM(total_price) as total'),
                DB::raw('COUNT(*) as bookings_count'),
            ])
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        // Build OHLC: opening = first booking's price that day, closing = last booking's price that day
        $series = $rows->map(function ($row) use ($hotel, $range) {
            $periodStart = $range === 'yearly'
                ? Carbon::createFromFormat('Y-m', $row->period)->startOfMonth()
                : Carbon::createFromFormat('Y-m-d', $row->period);
            $periodEnd = $range === 'yearly'
                ? (clone $periodStart)->endOfMonth()
                : (clone $periodStart)->endOfDay();

            $opening = Booking::where('hotel_id', $hotel->id)
                ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->orderBy('created_at')
                ->value('total_price');

            $closing = Booking::where('hotel_id', $hotel->id)
                ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
                ->whereBetween('created_at', [$periodStart, $periodEnd])
                ->orderByDesc('created_at')
                ->value('total_price');

            return [
                'date'    => $row->period,
                'opening' => round((float) $opening, 2),
                'high'    => round((float) $row->high, 2),
                'low'     => round((float) $row->low, 2),
                'closing' => round((float) $closing, 2),
                'volume'  => (int) $row->bookings_count,
            ];
        });

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

        $bookings = Booking::with('user', 'hotel')
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
        $hotel = $this->resolveHotel($request);

        $totalBookings = Booking::where('hotel_id', $hotel->id)->count();

        $checkInsToday = Booking::where('hotel_id', $hotel->id)
            ->whereDate('check_in', Carbon::today())
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->count();

        $totalRooms = Room::where('hotel_id', $hotel->id)->count();
        $occupied   = Room::where('hotel_id', $hotel->id)->where('status', 'occupied')->count();
        $occupancyRate = $totalRooms > 0 ? round(($occupied / $totalRooms) * 100, 1) : 0;

        $revenueMtd = Booking::where('hotel_id', $hotel->id)
            ->whereIn('status', ['confirmed', 'checked_in', 'checked_out'])
            ->whereMonth('created_at', Carbon::now()->month)
            ->whereYear('created_at', Carbon::now()->year)
            ->sum('total_price');

        return response()->json([
            'total_bookings' => $totalBookings,
            'check_ins_today' => $checkInsToday,
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
        if ($request->filled('hotel_id')) {
            return Hotel::findOrFail($request->get('hotel_id'));
        }

        return Hotel::orderBy('id')->firstOrFail();
    }

    private function initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        $first = $parts[0][0] ?? '';
        $last  = $parts[count($parts) - 1][0] ?? '';

        return strtoupper($first . $last);
    }
}
