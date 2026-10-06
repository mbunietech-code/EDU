<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Start registration for the app. The real user row is created only after
     * the email code is verified, so fake addresses never enter users.
     */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $pending = PendingRegistration::updateOrCreate([
            'email' => $data['email'],
        ], [
            'name' => $data['name'],
            'password' => Hash::make($data['password']),
        ]);

        $pending->refreshVerificationCode();

        return response()->json([
            'pending' => true,
            'email' => $pending->email,
            'message' => 'A 6-digit verification code has been sent to '.$pending->email.'.',
        ], 202);
    }

    /**
     * Verify a pending registration, then create the real user and issue a token.
     */
    public function verifyRegistration(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $pending = PendingRegistration::where('email', $data['email'])->first();

        if (! $pending) {
            throw ValidationException::withMessages([
                'code' => ['Please start registration again so we can send a fresh verification code.'],
            ]);
        }

        if (User::where('email', $pending->email)->exists()) {
            $pending->delete();

            throw ValidationException::withMessages([
                'email' => ['This email is already registered. Please sign in instead.'],
            ]);
        }

        if (! $pending->hasValidVerificationCode($data['code'])) {
            throw ValidationException::withMessages([
                'code' => ['The verification code is invalid or has expired. Please request a new one.'],
            ]);
        }

        $user = User::forceCreate([
            'name' => $pending->name,
            'email' => $pending->email,
            'email_verified_at' => now(),
            'password' => $pending->password,
        ]);

        $pending->delete();

        $token = $user->createToken($data['device_name'] ?? 'mhub-app')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ], 201);
    }

    /**
     * Issue a Sanctum token for valid credentials.
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials do not match our records.'],
            ]);
        }

        if (! $user->isActive()) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active. Contact support.'],
            ]);
        }

        $deviceName = $data['device_name'] ?? 'mhub-app';

        // One token per device name — replace any previous one.
        $user->tokens()->where('name', $deviceName)->delete();

        $token = $user->createToken($deviceName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
        ]);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($this->userPayload($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_admin' => (bool) $user->is_admin,
            'can_write_research' => $user->canWriteResearch(),
            'can_teach' => $user->isInstructor(),
            'can_access_studio' => $user->canAccessStudio(),
            'status' => $user->status,
            'email_verified_at' => optional($user->email_verified_at)->toIso8601String(),
        ];
    }
}
