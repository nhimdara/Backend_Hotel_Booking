<?php

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        $checkIn  = $this->faker->dateTimeBetween('+1 days', '+3 months');
        $checkOut = $this->faker->dateTimeBetween($checkIn, '+4 months');
        $nights   = $checkIn->diff($checkOut)->days ?: 1;
        $roomType = $this->faker->randomElement(['standard', 'deluxe', 'suite', 'presidential']);

        $multipliers = [
            'standard'     => 1.0,
            'deluxe'       => 1.3,
            'suite'        => 1.8,
            'presidential' => 3.0,
        ];

        $pricePerNight = $this->faker->randomFloat(2, 100, 800);
        $totalPrice    = $pricePerNight * $nights * $multipliers[$roomType];

        return [
            'user_id'           => User::factory(),
            'hotel_id'          => Hotel::factory(),
            'check_in'          => $checkIn->format('Y-m-d'),
            'check_out'         => $checkOut->format('Y-m-d'),
            'guests'            => $this->faker->numberBetween(1, 4),
            'room_type'         => $roomType,
            'total_price'       => $totalPrice,
            'status'            => $this->faker->randomElement(['pending', 'confirmed', 'checked_in', 'checked_out', 'cancelled']),
            'booking_reference' => 'SE-' . strtoupper(Str::random(4)) . '-' . rand(1000, 9999),
            'special_requests'  => $this->faker->optional()->sentence(),
        ];
    }
}
