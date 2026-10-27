<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Finance\FinanceAuditService;
use Illuminate\Http\Request;

/** Finance → Audit: checks to fix, and who added, changed or removed what. */
class FinanceAuditController extends Controller
{
    public function index(Request $request, FinanceAuditService $audit)
    {
        $kind = in_array($request->query('kind'), ['added', 'changed', 'removed'], true) ? $request->query('kind') : null;
        $activity = $audit->activity($request->boolean('more') ? 1000 : 150);

        return view('admin.finance.audit', [
            'checks' => $audit->checks(),
            'activity' => $kind ? $activity->where('kind', $kind)->values() : $activity,
            'kind' => $kind,
        ]);
    }
}
