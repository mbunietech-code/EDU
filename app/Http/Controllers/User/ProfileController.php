<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('user.profile.edit');
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        auth()->user()->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    /** A new sign-in email; your password confirms it is you. */
    public function updateEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . auth()->id()],
            'email_password' => ['required', 'current_password'],
        ], [], ['email_password' => 'password']);

        $user = auth()->user();
        $old = $user->email;
        $user->update(['email' => $validated['email']]);

        if ($old !== $user->email) {
            \App\Models\ActivityLog::log('user_email_changed', 'User', $user->id, ['from' => $old, 'to' => $user->email]);
        }

        return back()->with('success', 'Email updated. Use it the next time you sign in.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        auth()->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('success', 'Password updated successfully.');
    }
}