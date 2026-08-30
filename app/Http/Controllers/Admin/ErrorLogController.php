<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ErrorLog;
use Illuminate\Http\Request;

class ErrorLogController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('status', 'open');

        $logs = ErrorLog::query()
            ->with('user:id,name')
            ->when($filter === 'open', fn ($q) => $q->whereNull('resolved_at'))
            ->when($filter === 'resolved', fn ($q) => $q->whereNotNull('resolved_at'))
            ->orderByDesc('last_seen_at')
            ->paginate(20)
            ->withQueryString();

        $openCount = ErrorLog::whereNull('resolved_at')->count();

        return view('admin.error-logs.index', compact('logs', 'filter', 'openCount'));
    }

    public function show(ErrorLog $errorLog)
    {
        $errorLog->load(['user:id,name,email', 'resolver:id,name']);

        return view('admin.error-logs.show', compact('errorLog'));
    }

    public function resolve(ErrorLog $errorLog)
    {
        $this->authorize('error_logs.manage');

        $errorLog->update([
            'resolved_at' => now(),
            'resolved_by' => auth()->id(),
        ]);

        ActivityLog::log('error_log_resolved', 'ErrorLog', $errorLog->id, [
            'exception' => $errorLog->exception,
        ]);

        return back()->with('success', 'Marked as resolved.');
    }

    public function reopen(ErrorLog $errorLog)
    {
        $this->authorize('error_logs.manage');

        $errorLog->update(['resolved_at' => null, 'resolved_by' => null]);

        return back()->with('success', 'Reopened.');
    }

    public function destroy(ErrorLog $errorLog)
    {
        $this->authorize('error_logs.manage');

        $errorLog->delete();

        return redirect()->route('admin.error-logs.index')->with('success', 'Error log deleted.');
    }

    public function clearResolved()
    {
        $this->authorize('error_logs.manage');

        $count = ErrorLog::whereNotNull('resolved_at')->delete();

        return back()->with('success', "Cleared {$count} resolved error(s).");
    }
}
