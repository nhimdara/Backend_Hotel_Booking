<?php

namespace App\Http\Controllers;

use App\Models\Hotel;
use App\Models\Room;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RoomController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Room::with('hotel')->latest();

        if (!$request->user()->isSuperAdmin()) {
            abort_unless($request->user()->hotel_id, 422, 'Your admin account is not assigned to a hotel.');
            $query->where('hotel_id', $request->user()->hotel_id);
        } elseif ($request->filled('hotel_id')) {
            $query->where('hotel_id', $request->integer('hotel_id'));
        }

        return response()->json([
            'rooms' => $query->paginate($request->integer('per_page', 50)),
        ]);
    }

    public function show(Request $request, Room $room): JsonResponse
    {
        $this->authorizeHotel($request, (int) $room->hotel_id);
        return response()->json($room->load('hotel'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRoom($request);
        if (!$request->user()->isSuperAdmin()) {
            abort_unless($request->user()->hotel_id, 422, 'Your admin account is not assigned to a hotel.');
            $validated['hotel_id'] = $request->user()->hotel_id;
        } else {
            $validated['hotel_id'] = $validated['hotel_id'] ?? Hotel::orderBy('id')->value('id');
        }
        $validated['images'] = $this->imageLinksFromRequest($request);
        unset($validated['image_files'], $validated['image_urls']);

        if (!$validated['hotel_id']) {
            return response()->json(['message' => 'Create a hotel before adding rooms.'], 422);
        }

        $room = Room::create($validated);

        return response()->json([
            'message' => 'Room created.',
            'room' => $room->load('hotel'),
        ], 201);
    }

    public function update(Request $request, Room $room): JsonResponse
    {
        $this->authorizeHotel($request, (int) $room->hotel_id);
        $validated = $this->validateRoom($request, true);
        if (!$request->user()->isSuperAdmin()) {
            unset($validated['hotel_id']);
        }

        // Handle file uploads separately from main validation to allow updating URLs without files.
        $request->validate([
            'image_files'   => 'nullable|array',
            'image_files.*' => 'nullable|file|image|max:5120',
        ]);

        // The 'images' input is the new list of existing URLs. New uploads/URLs are added to it.
        $validated['images'] = $this->imageLinksFromRequest($request, $request->input('images', []));

        unset($validated['image_files'], $validated['image_urls']);
        $room->update($validated);

        return response()->json([
            'message' => 'Room updated.',
            'room' => $room->fresh()->load('hotel'),
        ]);
    }

    public function destroy(Request $request, Room $room): JsonResponse
    {
        $this->authorizeHotel($request, (int) $room->hotel_id);
        $room->delete();

        return response()->json(['message' => 'Room deleted.']);
    }

    private function validateRoom(Request $request, bool $isUpdate = false): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';

        return $request->validate([
            'hotel_id'       => 'nullable|exists:hotels,id',
            'room_number'    => "{$required}|string|max:50",
            'room_type'      => "{$required}|string|max:100",
            'floor'          => "{$required}|integer|min:0|max:200",
            'wing'           => 'nullable|string|max:100',
            'base_rate'      => "{$required}|numeric|min:0",
            'max_occupancy'  => "{$required}|integer|min:1|max:20",
            'status'         => 'sometimes|in:available,occupied,cleaning,maintenance',
            'description'    => 'nullable|string|max:1000',
            'images'         => 'nullable|array',
            'images.*'       => 'string', // Existing images are sent as URLs
            'image_urls'     => 'nullable|array',
            'image_urls.*'   => 'nullable|url',
        ]);
    }

    private function authorizeHotel(Request $request, int $hotelId): void
    {
        abort_unless($request->user()->canManageHotel($hotelId), 403, 'You cannot manage rooms from another hotel.');
    }

    private function imageLinksFromRequest(Request $request, array $existingImages = []): array
    {
        // Start with the list of images the user wants to keep.
        // If 'images' is not sent, it's an empty array, effectively clearing existing images unless new ones are added.
        $links = $existingImages;

        foreach ($request->input('image_urls', []) as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
                $links[] = $url;
            }
        }
        foreach ($request->file('image_files', []) as $image) {
            $path = $image->store('rooms', 'public');
            $links[] = url(Storage::url($path));
        }

        return array_values(array_unique($links));
    }
}
