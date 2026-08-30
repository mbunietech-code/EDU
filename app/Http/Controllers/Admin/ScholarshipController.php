<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreScholarshipRequest;
use App\Http\Requests\Admin\UpdateScholarshipRequest;
use App\Models\Scholarship;
use App\Services\DeletionService;
use Illuminate\Http\Request;

class ScholarshipController extends Controller
{
    public function index()
    {
        $scholarships = Scholarship::orderBy('sort_order')
            ->latest()
            ->paginate(15);

        return view('admin.scholarships.index', compact('scholarships'));
    }

    public function create()
    {
        return view('admin.scholarships.create');
    }

    public function store(StoreScholarshipRequest $request)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('scholarships', 'public');
        }

        $scholarship = Scholarship::create($validated);

        \App\Models\ActivityLog::log(
            'scholarship_created',
            'Scholarship',
            $scholarship->id,
            ['title' => $scholarship->title]
        );

        return redirect()->route('admin.scholarships.index')
            ->with('success', 'Scholarship created.');
    }

    public function edit(Scholarship $scholarship)
    {
        return view('admin.scholarships.edit', compact('scholarship'));
    }

    public function update(UpdateScholarshipRequest $request, Scholarship $scholarship)
    {
        $validated = $request->validated();

        if ($request->hasFile('image')) {
            $validated['image'] = $request->file('image')->store('scholarships', 'public');
        }

        $scholarship->update($validated);

        \App\Models\ActivityLog::log(
            'scholarship_updated',
            'Scholarship',
            $scholarship->id,
            ['title' => $scholarship->title]
        );

        return redirect()->route('admin.scholarships.index')
            ->with('success', 'Scholarship updated.');
    }

    public function destroy(Request $request, Scholarship $scholarship, DeletionService $deletionService)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $deletionService->delete($scholarship, $validated['reason']);

        return back()->with('success', 'Scholarship deleted.');
    }
}
