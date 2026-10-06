<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Services\NotificationService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Contact form and "forgot password" for the app (same as the web forms).
 */
class SupportController extends Controller
{
    public function contact(Request $request, NotificationService $notifications): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        $notifications->notifyAdminNewContactMessage(ContactMessage::create($validated));

        return response()->json(['message' => 'Your message has been sent. We will contact you soon.'], 201);
    }

    /** Send the "verify your email" link again (signed-in, unverified users). */
    public function resendVerification(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Your email is already verified.', 'verified' => true]);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'A new 6-digit verification code has been sent to '.$user->email.'.', 'verified' => false]);
    }

    /** Verify the email with the 6-digit code from the inbox (same as the web). */
    public function verifyEmailCode(Request $request): JsonResponse
    {
        $request->validate(['code' => ['required', 'string', 'regex:/^\d{6}$/']]);
        $user = $request->user();

        if (! $user->hasVerifiedEmail()) {
            if (! $user->hasValidEmailVerificationCode($request->string('code')->toString())) {
                return response()->json([
                    'message' => 'The verification code is invalid or has expired. Please request a new one.',
                    'errors' => ['code' => ['The verification code is invalid or has expired. Please request a new one.']],
                ], 422);
            }

            if ($user->markEmailAsVerified()) {
                $user->clearEmailVerificationCode();
                event(new Verified($user));
            }
        }

        return response()->json(['message' => 'Your email is verified.', 'verified' => true]);
    }

    /**
     * "Delete my account" in the app (Google Play requirement). The password
     * confirms it is really the owner; the request reaches the admins like a
     * contact message and is carried out within 30 days (see /account/delete).
     */
    public function accountDeletionRequest(Request $request, NotificationService $notifications): JsonResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);
        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'The password is not correct.']);
        }

        $message = ContactMessage::create([
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Account deletion request',
            'message' => "User #{$user->id} ({$user->email}) asked to delete their account from the app."
                .(filled($data['reason'] ?? null) ? "\n\nReason: ".$data['reason'] : ''),
        ]);
        ActivityLog::log('account_deletion_requested', 'User', $user->id);

        try {
            $notifications->notifyAdminNewContactMessage($message);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'message' => 'We received your request. Your account and personal data will be deleted within 30 days, and we will email you when it is done.',
        ], 201);
    }

    /**
     * Emails a reset link; the reset itself happens on the website. The reply
     * never says whether the email exists, so it cannot be used to probe accounts.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::RESET_THROTTLED) {
            return response()->json(['message' => 'Please wait before asking for another reset link.'], 429);
        }

        return response()->json(['message' => 'If an account uses that email, a password reset link is on its way.']);
    }
}
