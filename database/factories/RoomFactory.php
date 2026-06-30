<?php

namespace Database\Factories;

use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        $types = ['Standard King', 'Standard Double', 'Deluxe King', 'Double Queen', 'Penthouse Suite', 'Junior Suite'];

        return [
            'hotel_id'      => Hotel::factory(),
            'room_number'   => (string) $this->faker->unique()->numberBetween(100, 599),
            'room_type'     => $this->faker->randomElement($types),
            'floor'         => $this->faker->numberBetween(1, 5),
            'base_rate'     => $this->faker->randomFloat(2, 120, 900),
            'max_occupancy' => $this->faker->numberBetween(1, 4),
            'status'        => $this->faker->randomElement(['available', 'occupied', 'cleaning', 'maintenance']),
        ];
    }
}
