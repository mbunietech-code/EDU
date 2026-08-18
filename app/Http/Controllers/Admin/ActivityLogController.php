<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = ActivityLog::with('actor')
            ->when($request->filled('action'), function ($query) use ($request) {
                $query->where('action', $request->input('action'));
            })
            ->when($request->filled('entity'), function ($query) use ($request) {
                $query->where('entity', $request->input('entity'));
            })
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.activity-logs.index', compact('logs'));
    }
}