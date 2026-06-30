<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class HotelFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->company() . ' Hotel';

        return [
            'name'            => $name,
            'slug'            => Str::slug($name) . '-' . $this->faker->unique()->numberBetween(1, 9999),
            'description'     => $this->faker->paragraph(3),
            'location'        => $this->faker->city(),
            'country'         => $this->faker->country(),
            'latitude'        => $this->faker->latitude(-90, 90),
            'longitude'       => $this->faker->longitude(-180, 180),
            'price_per_night' => $this->faker->randomFloat(2, 80, 1500),
            'star_rating'     => $this->faker->numberBetween(2, 5),
            'review_score'    => $this->faker->randomFloat(1, 3.0, 5.0),
            'review_count'    => $this->faker->numberBetween(0, 500),
            'amenities'       => $this->faker->randomElements(
                ['Pool', 'Spa', 'Gym', 'Free WiFi', 'Parking', 'Restaurant', 'Bar', 'Beach Access', 'Concierge'],
                $this->faker->numberBetween(2, 5)
            ),
            'images'          => [],
            'is_active'       => true,
        ];
    }
}
