<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    /**
     * List hotels with optional search & filters.
     * GET /api/hotels?location=Paris&min_price=100&max_price=500&star_rating=4&badge=Top+Rated&per_page=15
     */
    public function index(Request $request): JsonResponse
    {
        $query = Hotel::query()->with('badges')->where('is_active', true);

        if ($request->filled('location')) {
            $query->where(function ($q) use ($request) {
                $q->where('location', 'like', '%' . $request->location . '%')
                  ->orWhere('country', 'like', '%' . $request->location . '%');
            });
        }

        if ($request->filled('min_price')) {
            $query->where('price_per_night', '>=', $request->min_price);
        }

        if ($request->filled('max_price')) {
            $query->where('price_per_night', '<=', $request->max_price);
        }

        if ($request->filled('star_rating')) {
            $query->where('star_rating', $request->star_rating);
        }

        if ($request->filled('min_rating')) {
            $query->where('review_score', '>=', $request->min_rating);
        }

        if ($request->filled('badge')) {
            $query->whereHas('badges', function ($q) use ($request) {
                $q->where('label', $request->badge);
            });
        }

        $sortBy  = in_array($request->sort_by, ['price_per_night', 'review_score', 'star_rating'])
                    ? $request->sort_by : 'review_score';
        $sortDir = $request->sort_dir === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sortBy, $sortDir);

        $hotels = $query->paginate($request->integer('per_page', 15));

        // Stats bar shown on search results: "16 Matches / $419 Avg Price / 4.7 Guest Rating"
        $stats = [
            'matches'      => $hotels->total(),
            'avg_price'    => round($query->avg('price_per_night'), 0),
            'avg_rating'   => round($query->avg('review_score'), 1),
        ];

        return response()->json([
            'stats'  => $stats,
            'hotels' => $hotels,
        ]);
    }

    /**
     * Store a new hotel (admin use).
     * POST /api/hotels
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'slug'            => 'required|string|unique:hotels,slug',
            'description'     => 'nullable|string',
            'location'        => 'required|string|max:255',
            'country'         => 'required|string|max:100',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'price_per_night' => 'required|numeric|min:0',
            'star_rating'     => 'required|integer|between:1,5',
            'amenities'       => 'nullable|array',
            'images'          => 'nullable|array',
            'images.*'        => 'nullable',
            'image'           => 'nullable|file|image|max:5120',
            'image_urls'      => 'nullable|array',
            'image_urls.*'    => 'url',
            'badge_ids'       => 'nullable|array',
            'badge_ids.*'     => 'exists:badges,id',
        ]);

        $validated['images'] = $this->imageLinksFromRequest($request);
        unset($validated['image'], $validated['image_urls']);

        $hotel = Hotel::create($validated);

        if (!empty($validated['badge_ids'])) {
            $hotel->badges()->sync($validated['badge_ids']);
        }

        return response()->json([
            'message' => 'Hotel created.',
            'hotel'   => $hotel->load('badges'),
        ], 201);
    }

    /**
     * Show a single hotel with room types and badges.
     * GET /api/hotels/{hotel}
     */
    public function show(Hotel $hotel): JsonResponse
    {
        $hotel->loadCount('bookings');
        $hotel->load([
            'badges',
            'rooms' => fn ($q) => $q->where('status', '!=', 'maintenance')->orderBy('room_type')->orderBy('room_number'),
            'roomTypes' => fn ($q) => $q->where('is_active', true),
        ]);

        return response()->json($hotel);
    }

    /**
     * Update a hotel.
     * PUT /api/hotels/{hotel}
     */
    public function update(Request $request, Hotel $hotel): JsonResponse
    {
        $validated = $request->validate([
            'name'            => 'sometimes|string|max:255',
            'slug'            => 'sometimes|string|unique:hotels,slug,' . $hotel->id,
            'description'     => 'nullable|string',
            'location'        => 'sometimes|string|max:255',
            'country'         => 'sometimes|string|max:100',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'price_per_night' => 'sometimes|numeric|min:0',
            'star_rating'     => 'sometimes|integer|between:1,5',
            'amenities'       => 'nullable|array',
            'images'          => 'nullable|array',
            'images.*'        => 'nullable',
            'image'           => 'nullable|file|image|max:5120',
            'image_urls'      => 'nullable|array',
            'image_urls.*'    => 'url',
            'is_active'       => 'sometimes|boolean',
            'badge_ids'       => 'nullable|array',
            'badge_ids.*'     => 'exists:badges,id',
        ]);

        if ($request->hasFile('images') || $request->hasFile('image') || $request->filled('images') || $request->filled('image_urls')) {
            $validated['images'] = $this->imageLinksFromRequest($request, $hotel->images ?? []);
        }

        unset($validated['image'], $validated['image_urls']);

        $hotel->update($validated);

        if (array_key_exists('badge_ids', $validated)) {
            $hotel->badges()->sync($validated['badge_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Hotel updated.',
            'hotel'   => $hotel->fresh()->load('badges'),
        ]);
    }

    /**
     * Delete a hotel.
     * DELETE /api/hotels/{hotel}
     */
    public function destroy(Hotel $hotel): JsonResponse
    {
        $hotel->delete();

        return response()->json(['message' => 'Hotel deleted.']);
    }

    private function imageLinksFromRequest(Request $request, array $existingImages = []): array
    {
        $links = $existingImages;

        foreach ($request->input('image_urls', []) as $url) {
            $links[] = $url;
        }

        foreach ($request->input('images', []) as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $links[] = $url;
            }
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('hotels', 'public');
            $links[] = url(Storage::url($path));
        }

        foreach ($request->file('images', []) as $image) {
            $path = $image->store('hotels', 'public');
            $links[] = url(Storage::url($path));
        }

        return array_values(array_unique($links));
    }
}
