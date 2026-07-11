<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\HotelRoomType;
use App\Models\Payment;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::create([
            'name'     => 'Alex Rivers',
            'email'    => 'admin@stayeasy.com',
            'password' => Hash::make('password'),
            'role'     => 'super_admin',
        ]);

        // Regular user
        $user = User::create([
            'name'           => 'Michael Watson',
            'email'          => 'michael.watson@example.com',
            'password'       => Hash::make('password'),
            'role'           => 'user',
            'loyalty_points' => 2593,
            'loyalty_tier'   => 'silver',
        ]);

        // Sample hotels
        $hotels = [
            [
                'name'            => 'Azure Sanctuary Resort',
                'slug'            => 'azure-sanctuary-resort',
                'description'     => 'Tucked in the pristine North Malé Atoll, masterclass in modern tropical architecture.',
                'location'        => 'North Malé Atoll',
                'country'         => 'Maldives',
                'price_per_night' => 580.00,
                'star_rating'     => 5,
                'review_score'    => 4.9,
                'review_count'    => 124,
                'amenities'       => ['Infinity Pool', 'Wellness Spa', 'Fine Dining', 'Giga-Wifi', 'Speedboat Access'],
                'images'          => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Grand Horizon Resort',
                'slug'            => 'grand-horizon-resort',
                'description'     => 'Stunning views of the Amalfi Coast with world-class amenities.',
                'location'        => 'Amalfi Coast',
                'country'         => 'Italy',
                'price_per_night' => 850.00,
                'star_rating'     => 5,
                'review_score'    => 4.8,
                'review_count'    => 98,
                'amenities'       => ['Pool Access', 'Fine Dining', 'Spa', 'Concierge'],
                'images'          => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Le Marais Maison',
                'slug'            => 'le-marais-maison',
                'description'     => 'Charming boutique stay tucked away in a historic Parisian quarter.',
                'location'        => 'Le Marais, Paris',
                'country'         => 'France',
                'price_per_night' => 310.00,
                'star_rating'     => 4,
                'review_score'    => 4.7,
                'review_count'    => 211,
                'amenities'       => ['Free WiFi', 'Breakfast Included', 'Concierge'],
                'images'          => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Forest Edge Retreat',
                'slug'            => 'forest-edge-retreat',
                'description'     => 'A serene escape into the Norwegian wilderness.',
                'location'        => 'Bergen',
                'country'         => 'Norway',
                'price_per_night' => 320.00,
                'star_rating'     => 4,
                'review_score'    => 4.92,
                'review_count'    => 76,
                'amenities'       => ['Sauna', 'Hiking Trails', 'Fireplace', 'Free Parking'],
                'images'          => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Sakura Sky Hotel',
                'slug'            => 'sakura-sky-hotel',
                'description'     => 'A polished city stay above Shinjuku with skyline views and calm interiors.',
                'location'        => 'Tokyo',
                'country'         => 'Japan',
                'latitude'        => 35.6895000,
                'longitude'       => 139.6917000,
                'price_per_night' => 420.00,
                'star_rating'     => 5,
                'review_score'    => 4.8,
                'review_count'    => 187,
                'amenities'       => ['Sky Bar', 'Onsen Bath', 'Airport Transfer', 'Free WiFi'],
                'images'             => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Desert Moon Camp',
                'slug'            => 'desert-moon-camp',
                'description'     => 'Luxury tented suites set among red dunes with private terraces and stargazing decks.',
                'location'        => 'Wadi Rum',
                'country'         => 'Jordan',
                'latitude'        => 29.5321000,
                'longitude'       => 35.0063000,
                'price_per_night' => 265.00,
                'star_rating'     => 4,
                'review_score'    => 4.6,
                'review_count'    => 143,
                'amenities'       => ['Desert Tours', 'Private Terrace', 'Campfire Dining', 'Stargazing'],
                'images'          => [],
                'is_active'       => true,
            ],
            [
                'name'            => 'Harbor Light Suites',
                'slug'            => 'harbor-light-suites',
                'description'     => 'Modern waterfront suites steps from the marina, seafood markets, and ferry piers.',
                'location'        => 'Vancouver',
                'country'         => 'Canada',
                'latitude'        => 49.2827000,
                'longitude'       => -123.1207000,
                'price_per_night' => 390.00,
                'star_rating'     => 4,
                'review_score'    => 4.7,
                'review_count'    => 205,
                'amenities'       => ['Harbor View', 'Kitchenette', 'Gym', 'Pet Friendly'],
                'images'          => [],
                'is_active'       => true,
            ],
        ];

        foreach ($hotels as $data) {
            Hotel::create($data);
        }

        $admin->update(['hotel_id' => Hotel::query()->value('id')]);

        // Badges shown as colored pills on hotel cards
        $badgeDefs = [
            ['label' => 'Top Rated',          'color' => 'amber'],
            ['label' => 'Pool Access',        'color' => 'teal'],
            ['label' => 'Hidden Gem',         'color' => 'purple'],
            ['label' => 'New',                'color' => 'green'],
            ['label' => 'Editor\'s Choice',   'color' => 'orange'],
            ['label' => 'Eco-Certified Gold', 'color' => 'green'],
            ['label' => 'Best Rate Guaranteed', 'color' => 'blue'],
        ];

        $badges = collect($badgeDefs)->mapWithKeys(function ($def) {
            $badge = Badge::create($def);
            return [$def['label'] => $badge];
        });

        $azure   = Hotel::where('slug', 'azure-sanctuary-resort')->first();
        $horizon = Hotel::where('slug', 'grand-horizon-resort')->first();
        $marais  = Hotel::where('slug', 'le-marais-maison')->first();
        $forest  = Hotel::where('slug', 'forest-edge-retreat')->first();
        $sakura  = Hotel::where('slug', 'sakura-sky-hotel')->first();
        $desert  = Hotel::where('slug', 'desert-moon-camp')->first();
        $harbor  = Hotel::where('slug', 'harbor-light-suites')->first();

        $azure->badges()->attach([
            $badges['Editor\'s Choice']->id,
            $badges['Eco-Certified Gold']->id,
        ]);
        $horizon->badges()->attach([
            $badges['Top Rated']->id,
            $badges['Pool Access']->id,
        ]);
        $marais->badges()->attach([$badges['Best Rate Guaranteed']->id]);
        $forest->badges()->attach([$badges['Hidden Gem']->id, $badges['New']->id]);
        $sakura->badges()->attach([$badges['Top Rated']->id, $badges['Editor\'s Choice']->id]);
        $desert->badges()->attach([$badges['Hidden Gem']->id, $badges['Eco-Certified Gold']->id]);
        $harbor->badges()->attach([$badges['Best Rate Guaranteed']->id, $badges['New']->id]);

        // Room types per hotel ("Choose Your Room" cards)
        HotelRoomType::insert([
            [
                'hotel_id'        => $azure->id,
                'name'            => 'Beachfront Sanctuary',
                'badge'           => 'Best Value',
                'size'            => '120m²',
                'bed_type'        => 'King Bed',
                'max_guests'      => 3,
                'price_per_night' => 580.00,
                'images'          => json_encode([]),
                'description'     => 'Steps from the lagoon with curated amenities.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'hotel_id'        => $azure->id,
                'name'            => 'Overwater Sunset Villa',
                'badge'           => "Editor's Pick",
                'size'            => '145m²',
                'bed_type'        => 'King Bed',
                'max_guests'      => 2,
                'price_per_night' => 720.00,
                'images'          => json_encode([]),
                'description'     => 'Infinite horizon views over the lagoon.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'hotel_id'        => $azure->id,
                'name'            => 'Azure Grand Suite',
                'badge'           => 'Ultra Luxury',
                'size'            => '280m²',
                'bed_type'        => '2x King',
                'max_guests'      => 4,
                'price_per_night' => 1450.00,
                'images'          => json_encode([]),
                'description'     => 'Private pool and butler service.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'hotel_id'        => $sakura->id,
                'name'            => 'Shinjuku Skyline King',
                'badge'           => 'City View',
                'size'            => '48m2',
                'bed_type'        => 'King Bed',
                'max_guests'      => 2,
                'price_per_night' => 420.00,
                'images'          => json_encode([]),
                'description'     => 'High-floor room with skyline views and a deep soaking tub.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'hotel_id'        => $desert->id,
                'name'            => 'Dune Terrace Tent',
                'badge'           => 'Stargazer',
                'size'            => '62m2',
                'bed_type'        => 'Queen Bed',
                'max_guests'      => 2,
                'price_per_night' => 265.00,
                'images'          => json_encode([]),
                'description'     => 'Private terrace, woven interiors, and guided evening stargazing.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'hotel_id'        => $harbor->id,
                'name'            => 'Marina One-Bedroom Suite',
                'badge'           => 'Family Ready',
                'size'            => '70m2',
                'bed_type'        => 'King Bed + Sofa Bed',
                'max_guests'      => 4,
                'price_per_night' => 390.00,
                'images'          => json_encode([]),
                'description'     => 'Waterfront suite with kitchenette, balcony, and marina views.',
                'is_active'       => true,
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        // Seed rooms for the first hotel (drives occupancy/check-in stats)
        $roomTypes = [
            ['Deluxe King', 350.00],
            ['Standard Double', 220.00],
            ['Penthouse Suite', 1450.00],
            ['Standard King', 240.00],
            ['Double Queen', 280.00],
            ['Junior Suite', 520.00],
        ];

        $floor = 1;
        $roomNumber = 101;
        for ($i = 0; $i < 27; $i++) {
            [$type, $rate] = $roomTypes[$i % count($roomTypes)];

            Room::create([
                'hotel_id'      => 1,
                'room_number'   => (string) $roomNumber,
                'room_type'     => $type,
                'floor'         => $floor,
                'base_rate'     => $rate,
                'max_occupancy' => $type === 'Penthouse Suite' ? 4 : 2,
                'status'        => 'occupied',
            ]);

            $roomNumber++;
            if ($roomNumber % 100 === 0) {
                $floor++;
                $roomNumber = $floor * 100 + 1;
            }
        }

        // Sample booking for the regular user
        $booking = Booking::create([
            'user_id'           => $user->id,
            'hotel_id'          => 1,
            'check_in'          => now()->addDays(10)->toDateString(),
            'check_out'         => now()->addDays(16)->toDateString(),
            'guests'            => 2,
            'room_type'         => 'suite',
            'total_price'       => 6264.00,
            'status'            => 'confirmed',
            'booking_reference' => 'SE-8829-4102',
            'special_requests'  => 'Ocean-facing villa preferred.',
        ]);

        // Completed payment for the sample booking (mirrors the Confirm & Pay screen)
        Payment::create([
            'booking_id'      => $booking->id,
            'reference'       => 'PAY-' . strtoupper(substr(md5($booking->id . 'seed'), 0, 6)),
            'amount'          => $booking->total_price,
            'method'          => 'qr_scan',
            'status'          => 'completed',
            'qr_code_payload' => asset('images/ada-pay-qr.jpg'),
            'hold_expires_at' => now()->addMinutes(15),
            'authorized_at'   => now(),
            'points_earned'   => 2593,
        ]);
    }
}
