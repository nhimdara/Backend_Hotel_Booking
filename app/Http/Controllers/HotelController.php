<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class HotelController extends Controller
{
    public function adminIndex(Request $request): JsonResponse
    {
        $query = Hotel::query()->with('badges');
        if (!$request->user()->isSuperAdmin()) {
            abort_unless($request->user()->hotel_id, 422, 'Your admin account is not assigned to a hotel.');
            $query->whereKey($request->user()->hotel_id);
        }
        $hotels = $query->orderBy('name')->get()->map(fn (Hotel $hotel) => $this->withPublicImages($hotel));
        return response()->json(['hotels' => $hotels]);
    }
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
        $hotels->getCollection()->transform(fn (Hotel $hotel) => $this->withPublicImages($hotel));

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
        abort_unless($request->user()->isSuperAdmin(), 403, 'Only a super admin can create hotels.');
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
            'hotel'   => $this->withPublicImages($hotel->load('badges')),
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
            'rooms' => fn($q) => $q->where('status', '!=', 'maintenance')->orderBy('room_type')->orderBy('room_number'),
            'roomTypes' => fn($q) => $q->where('is_active', true),
        ]);

        return response()->json($this->withPublicImages($hotel));
    }

    /**
     * Update a hotel.
     * PUT /api/hotels/{hotel}
     */
    public function update(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()->canManageHotel((int) $hotel->id), 403, 'You cannot update another hotel.');
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
            'images.*'        => 'string', // Existing images are sent as URLs
            'image_urls'      => 'nullable|array',
            'image_urls.*'    => 'url',
            'is_active'       => 'sometimes|boolean',
            'badge_ids'       => 'nullable|array',
            'badge_ids.*'     => 'exists:badges,id',
        ]);

        // Handle file uploads and URL additions for images.
        // The 'images' input from the request is the new desired list of existing URLs.
        $validated['images'] = $this->imageLinksFromRequest($request, $request->input('images', []));
        $request->validate(['image' => 'nullable|file|image|max:5120']); // Validate single file upload separately

        unset($validated['image'], $validated['image_urls']);

        $hotel->update($validated);

        if (array_key_exists('badge_ids', $validated)) {
            $hotel->badges()->sync($validated['badge_ids'] ?? []);
        }

        return response()->json([
            'message' => 'Hotel updated.',
            'hotel'   => $this->withPublicImages($hotel->fresh()->load('badges')),
        ]);
    }

    /**
     * Delete a hotel.
     * DELETE /api/hotels/{hotel}
     */
    public function destroy(Request $request, Hotel $hotel): JsonResponse
    {
        abort_unless($request->user()->canManageHotel((int) $hotel->id), 403, 'You cannot delete another hotel.');
        $hotel->delete();

        return response()->json(['message' => 'Hotel deleted.']);
    }

    private function imageLinksFromRequest(Request $request, array $existingImages = []): array
    {
        // Start with the list of images the user wants to keep.
        // If 'images' is not sent, it's an empty array, effectively clearing existing images unless new ones are added.
        $links = $existingImages;

        foreach ($request->input('image_urls', []) as $url) {
            $links[] = $url;
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('hotels', 'public');
            abort_unless($path && Storage::disk('public')->exists($path), 500, 'The hotel image could not be stored.');
            $links[] = '/storage/' . ltrim($path, '/');
        }

        foreach ($request->file('images', []) as $image) {
            $path = $image->store('hotels', 'public');
            abort_unless($path && Storage::disk('public')->exists($path), 500, 'A hotel image could not be stored.');
            $links[] = '/storage/' . ltrim($path, '/');
        }

        return array_values(array_unique(array_map(fn ($link) => $this->normalizeImageLink($link), $links)));
    }

    private function withPublicImages(Hotel $hotel): Hotel
    {
        $images = collect($hotel->images ?? [])
            ->map(fn ($link) => $this->normalizeImageLink($link))
            ->filter(fn ($link) => !$this->isMissingLocalImage($link))
            ->values()
            ->all();

        $hotel->setAttribute('images', $images);
        return $hotel;
    }

    private function normalizeImageLink(string $link): string
    {
        $path = parse_url(trim($link), PHP_URL_PATH) ?: trim($link);
        if (str_starts_with($path, '/storage/')) {
            return $path;
        }
        if (str_starts_with($path, 'storage/')) {
            return '/' . $path;
        }
        if (str_starts_with($path, 'hotels/')) {
            return '/storage/' . $path;
        }
        return $link;
    }

    private function isMissingLocalImage(string $link): bool
    {
        $path = parse_url($link, PHP_URL_PATH) ?: $link;
        if (!str_starts_with($path, '/storage/')) {
            return false;
        }
        return !Storage::disk('public')->exists(substr($path, strlen('/storage/')));
    }
}
