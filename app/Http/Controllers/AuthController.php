<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Hotel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Register a new user (role defaults to 'user').
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role'     => 'user', // always user on public register
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'user'    => $user,
            'token'   => $token,
        ], 201);
    }

    /**
     * Login and return a Sanctum token.
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($validated)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user  = Auth::user();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'Login successful.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }

    /**
     * Logout (revoke current token).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    /**
     * Get authenticated user profile.
     */
    public function profile(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    /**
     * Update own profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name'     => 'sometimes|string|max:255',
            'email'    => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8|confirmed',
        ]);

        if (isset($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        }

        $user->update($validated);

        return response()->json([
            'message' => 'Profile updated.',
            'user'    => $user->fresh(),
        ]);
    }

    // -----------------------------------------------------------------------
    // Admin only
    // -----------------------------------------------------------------------

    /**
     * List all users (admin).
     */
    public function allUsers(Request $request): JsonResponse
    {
        $query = User::query()->where('role', 'user');
        if (!$request->user()->isSuperAdmin()) {
            abort_unless($request->user()->hotel_id, 422, 'Your admin account is not assigned to a hotel.');
            $query->whereHas('bookings', fn ($booking) => $booking->where('hotel_id', $request->user()->hotel_id));
        }
        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    /**
     * Change a user's role (admin).
     */
    public function changeRole(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'role' => 'required|in:user,admin,super_admin',
        ]);

        abort_if($validated['role'] === 'super_admin' && !$request->user()->isSuperAdmin(), 403, 'Only a super admin can grant this role.');
        abort_if($request->user()->is($user) && $validated['role'] !== 'super_admin', 422, 'You cannot remove your own super-admin access.');

        $user->update(['role' => $validated['role']]);

        return response()->json([
            'message' => "User role updated to {$validated['role']}.",
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Delete a user (admin).
     */
    public function deleteUser(User $user): JsonResponse
    {
        $user->delete();

        return response()->json(['message' => 'User deleted.']);
    }

    /** List administrators assigned to the authenticated admin's hotel. */
    public function hotelAdmins(Request $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin->isSuperAdmin() || $admin->hotel_id, 422, 'Your admin account is not assigned to a hotel.');

        $query = User::query()->with('hotel')->whereIn('role', ['admin', 'super_admin']);
        if (!$admin->isSuperAdmin()) {
            $query->where('hotel_id', $admin->hotel_id);
        }

        return response()->json([
            'hotel' => $admin->hotel,
            'is_super_admin' => $admin->isSuperAdmin(),
            'hotels' => $admin->isSuperAdmin()
                ? Hotel::query()->select('id', 'name', 'location')->orderBy('name')->get()
                : collect([$admin->hotel])->filter(),
            'admins' => $query->latest()->get(),
        ]);
    }

    /** Create an administrator for the authenticated admin's hotel. */
    public function createHotelAdmin(Request $request): JsonResponse
    {
        $manager = $request->user();
        abort_unless($manager->isSuperAdmin() || $manager->hotel_id, 422, 'Your admin account is not assigned to a hotel.');

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'hotel_id' => $manager->isSuperAdmin() ? 'required|exists:hotels,id' : 'prohibited',
        ]);

        $email = $this->generateAdminEmail($validated['name']);
        $temporaryPassword = Str::password(12);

        $admin = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($temporaryPassword),
            'role' => 'admin',
            'hotel_id' => $manager->isSuperAdmin() ? $validated['hotel_id'] : $manager->hotel_id,
        ]);

        return response()->json([
            'message' => 'Hotel administrator created.',
            'admin' => $admin->load('hotel'),
            'credentials' => ['email' => $email, 'temporary_password' => $temporaryPassword],
        ], 201);
    }

    /** Update an administrator belonging to the same hotel. */
    public function updateHotelAdmin(Request $request, User $user): JsonResponse
    {
        $manager = $request->user();
        $this->ensureSameHotelAdmin($manager, $user);

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
            'hotel_id' => $manager->isSuperAdmin() ? 'sometimes|required|exists:hotels,id' : 'prohibited',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }
        $user->update($validated);

        return response()->json(['message' => 'Administrator updated.', 'admin' => $user->fresh()]);
    }

    /** Remove an administrator belonging to the same hotel. */
    public function deleteHotelAdmin(Request $request, User $user): JsonResponse
    {
        $manager = $request->user();
        $this->ensureSameHotelAdmin($manager, $user);
        abort_if($manager->is($user), 422, 'You cannot delete your own administrator account.');

        $user->tokens()->delete();
        $user->delete();
        return response()->json(['message' => 'Administrator removed.']);
    }

    private function ensureSameHotelAdmin(User $manager, User $user): void
    {
        if ($manager->isSuperAdmin() && $user->isAdmin()) {
            return;
        }
        abort_unless(
            $manager->hotel_id && $user->role === 'admin' && $user->hotel_id === $manager->hotel_id,
            404,
            'Administrator not found for your hotel.'
        );
    }

    private function generateAdminEmail(string $name): string
    {
        $base = Str::slug($name, '.');
        $base = $base !== '' ? $base : 'hotel.admin';
        $email = $base . '@gmail.com';
        $suffix = 2;

        while (User::where('email', $email)->exists()) {
            $email = $base . $suffix . '@gmail.com';
            $suffix++;
        }

        return $email;
    }
}
