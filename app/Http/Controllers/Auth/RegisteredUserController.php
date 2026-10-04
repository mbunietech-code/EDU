<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\PendingRegistration;
use App\Models\User;
use App\Notifications\Auth\EmailVerificationCode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    private const PENDING_EMAIL_SESSION_KEY = 'pending_registration_email';

    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email:rfc,dns', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $pending = $this->storePendingRegistration($validated);

        $request->session()->put(self::PENDING_EMAIL_SESSION_KEY, $pending->email);

        return redirect()->route('register.verify')
            ->with('status', 'verification-code-sent');
    }

    public function verifyForm(Request $request): RedirectResponse|View
    {
        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            return redirect()->route('register');
        }

        return view('auth.verify-registration', compact('pending'));
    }

    /**
     * @throws ValidationException
     */
    public function verify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            throw ValidationException::withMessages([
                'code' => 'Please start registration again so we can send a fresh verification code.',
            ]);
        }

        if (User::where('email', $pending->email)->exists()) {
            $this->deletePendingRegistration($pending);
            $request->session()->forget(self::PENDING_EMAIL_SESSION_KEY);

            throw ValidationException::withMessages([
                'code' => 'This email is already registered. Please sign in instead.',
            ]);
        }

        if (! $this->pendingCodeIsValid($pending, $validated['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or has expired. Please request a new one.',
            ]);
        }

        $user = User::forceCreate([
            'name' => $pending->name,
            'email' => $pending->email,
            'email_verified_at' => now(),
            'password' => $pending->password,
        ]);

        $this->deletePendingRegistration($pending);
        $request->session()->forget(self::PENDING_EMAIL_SESSION_KEY);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false).'?verified=1');
    }

    public function resend(Request $request): RedirectResponse
    {
        $pending = $this->pendingRegistration($request);

        if (! $pending) {
            return redirect()->route('register');
        }

        $this->refreshPendingRegistrationCode($pending);

        return back()->with('status', 'verification-code-sent');
    }

    protected function pendingRegistration(Request $request): ?object
    {
        $email = $request->session()->get(self::PENDING_EMAIL_SESSION_KEY);

        if (! is_string($email) || $email === '') {
            return null;
        }

        if ($this->pendingRegistrationsTableExists()) {
            return PendingRegistration::where('email', $email)->first();
        }

        $payload = Cache::get($this->pendingRegistrationCacheKey($email));

        return is_array($payload) ? (object) $payload : null;
    }

    /**
     * @param array{name:string,email:string,password:string} $validated
     */
    protected function storePendingRegistration(array $validated): object
    {
        $hashedPassword = Hash::make($validated['password']);

        if ($this->pendingRegistrationsTableExists()) {
            $pending = PendingRegistration::updateOrCreate([
                'email' => $validated['email'],
            ], [
                'name' => $validated['name'],
                'password' => $hashedPassword,
                'verification_code' => '000000',
                'verification_code_expires_at' => now(),
            ]);

            $pending->refreshVerificationCode();

            return $pending;
        }

        $expiresInMinutes = 15;
        $code = (string) random_int(100000, 999999);
        $payload = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $hashedPassword,
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
        ];

        Cache::put($this->pendingRegistrationCacheKey($validated['email']), $payload, now()->addMinutes($expiresInMinutes));
        Notification::route('mail', $validated['email'])
            ->notify(new EmailVerificationCode($code, $expiresInMinutes));

        return (object) $payload;
    }

    protected function refreshPendingRegistrationCode(object $pending): void
    {
        if ($pending instanceof PendingRegistration) {
            $pending->refreshVerificationCode();

            return;
        }

        $expiresInMinutes = 15;
        $code = (string) random_int(100000, 999999);
        $payload = [
            'name' => $pending->name,
            'email' => $pending->email,
            'password' => $pending->password,
            'verification_code' => $code,
            'verification_code_expires_at' => now()->addMinutes($expiresInMinutes)->timestamp,
        ];

        Cache::put($this->pendingRegistrationCacheKey($pending->email), $payload, now()->addMinutes($expiresInMinutes));
        Notification::route('mail', $pending->email)
            ->notify(new EmailVerificationCode($code, $expiresInMinutes));
    }

    protected function pendingCodeIsValid(object $pending, string $code): bool
    {
        if ($pending instanceof PendingRegistration) {
            return $pending->hasValidVerificationCode($code);
        }

        return hash_equals((string) $pending->verification_code, preg_replace('/\D+/', '', $code))
            && now()->timestamp < (int) $pending->verification_code_expires_at;
    }

    protected function deletePendingRegistration(object $pending): void
    {
        if ($pending instanceof PendingRegistration) {
            $pending->delete();

            return;
        }

        Cache::forget($this->pendingRegistrationCacheKey($pending->email));
    }

    protected function pendingRegistrationCacheKey(string $email): string
    {
        return 'pending-registration:' . sha1(strtolower($email));
    }

    protected function pendingRegistrationsTableExists(): bool
    {
        return Schema::hasTable('pending_registrations');
    }
}
