<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HotelController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\RoomController;
use App\Http\Middleware\IsAdmin;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json([
    'status' => 'ok',
    'service' => config('app.name'),
]));

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/hotels',         [HotelController::class, 'index']);
Route::get('/hotels/{hotel}', [HotelController::class, 'show']);

/*
|--------------------------------------------------------------------------
| Auth routes (any logged-in user)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Auth
    Route::post('/logout',  [AuthController::class, 'logout']);
    Route::get('/profile',  [AuthController::class, 'profile']);
    Route::put('/profile',  [AuthController::class, 'updateProfile']);

    // Bookings (user sees own, admin sees all — logic inside controller)
    Route::get('/bookings',              [BookingController::class, 'index']);
    Route::post('/bookings',             [BookingController::class, 'store']);
    Route::get('/bookings/{booking}',    [BookingController::class, 'show']);
    Route::put('/bookings/{booking}',    [BookingController::class, 'update']);
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy']);

    // Payments (Confirm & Pay flow with QR scan-to-pay)
    Route::post('/bookings/{booking}/payment',   [PaymentController::class, 'initiate']);
    Route::get('/payments/{payment}/status',     [PaymentController::class, 'status']);
    Route::post('/payments/{payment}/authorize', [PaymentController::class, 'authorizePayment']);

    /*
    |--------------------------------------------------------------------------
    | Admin-only routes
    |--------------------------------------------------------------------------
    */
    Route::middleware(IsAdmin::class)->group(function () {

        // Hotel management
        Route::get('/admin/hotels',     [HotelController::class, 'adminIndex']);
        Route::post('/hotels',           [HotelController::class, 'store']);
        Route::put('/hotels/{hotel}',    [HotelController::class, 'update']);
        Route::delete('/hotels/{hotel}', [HotelController::class, 'destroy']);

        // User management
        Route::get('/admin/users',                    [AuthController::class, 'allUsers']);
        Route::put('/admin/users/{user}/role',        [AuthController::class, 'changeRole']);
        Route::delete('/admin/users/{user}',          [AuthController::class, 'deleteUser']);

        // Administrators are always scoped to the signed-in admin's hotel.
        Route::get('/admin/hotel-admins',              [AuthController::class, 'hotelAdmins']);
        Route::post('/admin/hotel-admins',             [AuthController::class, 'createHotelAdmin']);
        Route::put('/admin/hotel-admins/{user}',       [AuthController::class, 'updateHotelAdmin']);
        Route::delete('/admin/hotel-admins/{user}',    [AuthController::class, 'deleteHotelAdmin']);

        // Booking management
        Route::get('/admin/bookings',                 [BookingController::class, 'index']);

        // Room management
        Route::get('/admin/rooms',                    [RoomController::class, 'index']);
        Route::post('/admin/rooms',                   [RoomController::class, 'store']);
        Route::get('/admin/rooms/{room}',             [RoomController::class, 'show']);
        Route::put('/admin/rooms/{room}',             [RoomController::class, 'update']);
        Route::delete('/admin/rooms/{room}',          [RoomController::class, 'destroy']);

        // Dashboard analytics (Overview screen)
        Route::get('/admin/dashboard/overview',            [DashboardController::class, 'overview']);
        Route::get('/admin/dashboard/snapshot',            [DashboardController::class, 'snapshot']);
        Route::get('/admin/dashboard/revenue-performance',  [DashboardController::class, 'revenuePerformance']);
        Route::get('/admin/dashboard/recent-bookings',      [DashboardController::class, 'recentBookings']);
        Route::get('/admin/dashboard/bookings-summary',     [DashboardController::class, 'bookingsSummary']);
    });
});
