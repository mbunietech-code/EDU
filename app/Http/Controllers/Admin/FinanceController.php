<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class FinanceController extends Controller
{
    public function pin()
    {
        if (session('finance_unlocked')) {
            return redirect()->route('admin.finance.dashboard');
        }

        return view('admin.finance.pin');
    }

    public function verify(Request $request)
    {
        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $key = 'finance-pin:' . auth()->id();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            return back()->withErrors(['pin' => "Too many attempts. Try again in {$seconds} seconds."]);
        }

        if ($validated['pin'] !== Setting::get('finance_pin')) {
            RateLimiter::hit($key, 300);

            return back()->withErrors(['pin' => 'Incorrect PIN.']);
        }

        RateLimiter::clear($key);

        $request->session()->put('finance_unlocked', true);

        ActivityLog::log('finance_unlocked', 'Finance', null, []);

        return redirect()->route('admin.finance.dashboard');
    }

    public function lock(Request $request)
    {
        $request->session()->forget('finance_unlocked');

        return redirect()->route('admin.finance.pin');
    }

    public function dashboard()
    {
        return view('admin.finance.dashboard');
    }
}
