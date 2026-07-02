<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    /**
     * POST /api/bookings/{booking}/payment
     *
     * Initiates a payment hold for a booking (returns the QR image URL,
     * starts the 15-minute countdown shown on the Confirm & Pay screen).
     */
    public function initiate(Request $request, Booking $booking): JsonResponse
    {
        if ($booking->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($booking->status === 'cancelled') {
            return response()->json(['message' => 'Cannot pay for a cancelled booking.'], 422);
        }

        if ($booking->payment) {
            return response()->json([
                'message' => 'Payment already initiated for this booking.',
                'payment' => $booking->payment,
            ], 409);
        }

        $validated = $request->validate([
            'method' => 'sometimes|in:card,qr_scan,bank_transfer',
        ]);

        $pointsEarned = (int) floor($booking->total_price); // $1 = 1 point, matches "$2,592.50 → 2,593 points" pattern roughly

        $payment = Payment::create([
            'booking_id'      => $booking->id,
            'reference'       => 'PAY-' . strtoupper(Str::random(6)),
            'amount'          => $booking->total_price,
            'method'          => $validated['method'] ?? 'qr_scan',
            'status'          => 'pending',
            'qr_code_payload' => $this->buildQrPayload(),
            'hold_expires_at' => Carbon::now()->addMinutes(15),
            'points_earned'   => $pointsEarned,
        ]);

        return response()->json([
            'message' => 'Payment hold created. Scan the QR code to authorize.',
            'payment' => $payment,
            'seconds_remaining' => 900,
        ], 201);
    }

    /**
     * GET /api/payments/{payment}/status
     *
     * Polled by the frontend countdown timer to check if payment was authorized.
     */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        if (!$this->canAccessPayment($request, $payment)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => 'expired']);

            if ($payment->booking->status === 'pending') {
                $payment->booking->update(['status' => 'cancelled']);
            }
        }

        return response()->json([
            'status'            => $payment->status,
            'seconds_remaining' => $payment->status === 'pending'
                ? max(0, Carbon::now()->diffInSeconds($payment->hold_expires_at, false))
                : 0,
        ]);
    }

    /**
     * POST /api/payments/{payment}/authorize
     *
     * Simulates the banking app scanning and authorizing the QR code.
     * In production this would be called by a webhook from the payment provider.
     */
    public function authorizePayment(Request $request, Payment $payment): JsonResponse
    {
        if (!$this->canAccessPayment($request, $payment)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($payment->status !== 'pending') {
            return response()->json(['message' => 'This payment can no longer be authorized.'], 422);
        }

        if ($payment->isExpired()) {
            $payment->update(['status' => 'expired']);
            if ($payment->booking->status === 'pending') {
                $payment->booking->update(['status' => 'cancelled']);
            }
            return response()->json(['message' => 'Payment hold has expired.'], 422);
        }

        $payment->update([
            'status'        => 'completed',
            'authorized_at' => now(),
        ]);

        $booking = $payment->booking;
        $booking->update(['status' => 'awaiting_approval']);

        // Award loyalty points and update tier
        $user = $booking->user;
        $user->increment('loyalty_points', $payment->points_earned);
        $this->recalculateTier($user);

        return response()->json([
            'message' => 'Payment authorized. Booking is waiting for hotel approval.',
            'payment' => $payment->fresh(),
            'booking' => $booking->fresh(),
            'points_earned' => $payment->points_earned,
            'new_points_balance' => $user->fresh()->loyalty_points,
        ]);
    }

    private function canAccessPayment(Request $request, Payment $payment): bool
    {
        return $request->user()->isAdmin() || $payment->booking->user_id === $request->user()->id;
    }

    private function buildQrPayload(): string
    {
        return asset('images/ada-pay-qr.jpg');
    }

    private function recalculateTier($user): void
    {
        $points = $user->loyalty_points;

        $tier = match (true) {
            $points >= 20000 => 'diamond_elite',
            $points >= 8000  => 'platinum',
            $points >= 2000  => 'silver',
            default          => 'standard',
        };

        $user->update(['loyalty_tier' => $tier]);
    }
}
