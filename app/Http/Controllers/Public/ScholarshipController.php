<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Scholarship;

class ScholarshipController extends Controller
{
    public function index()
    {
        $scholarships = Scholarship::published()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->paginate(12);

        return view('public.scholarships.index', compact('scholarships'));
    }

    public function show(Scholarship $scholarship)
    {
        if (! $scholarship->isPublished()) {
            abort(404);
        }

        return view('public.scholarships.show', compact('scholarship'));
    }
}
