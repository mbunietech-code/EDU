<?php

namespace App\Http\Controllers\Api\Admin\Concerns;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Finance API access: the finance.access permission plus the 6-digit PIN,
 * unlocked per app token (see Api\Admin\FinanceController::unlock).
 */
trait FinanceAccess
{
    private function gate(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->isSuperAdmin() || $user->hasPermission('finance.access'),
            403,
            'You do not have access to Finance.',
        );
    }

    private function unlockKey(Request $request): string
    {
        return 'finance-unlock:token:'.optional($request->user()->currentAccessToken())->id;
    }

    private function assertUnlocked(Request $request): void
    {
        abort_unless(
            Cache::get($this->unlockKey($request)) === true,
            423,
            'Finance is locked. Enter the PIN to unlock.',
        );
    }

    /** Both checks: allowed into Finance and the PIN was entered. */
    private function finance(Request $request): void
    {
        $this->gate($request);
        $this->assertUnlocked($request);
    }
}
