<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Research;
use App\Models\ResearchCategory;

/**
 * Public, logged-out view of the research library: teaser cards only.
 * Reading a research (chapters/sections) requires signing in.
 */
class ResearchController extends Controller
{
    public function index()
    {
        if (auth()->check()) {
            return redirect()->route('library.index');
        }

        $categories = ResearchCategory::query()
            ->withCount(['researches as published_count' => fn ($q) => $q->where('status', 'published')])
            ->orderBy('position')->orderBy('name')->get()
            ->filter(fn ($c) => $c->published_count > 0)
            ->values();

        $researches = Research::published()
            ->with(['category', 'author:id,name'])
            ->withCount('chapters')
            ->latest('published_at')
            ->paginate(12);

        return view('public.research.index', compact('categories', 'researches'));
    }

    public function category(ResearchCategory $category)
    {
        if (auth()->check()) {
            return redirect()->route('library.category', $category);
        }

        $researches = $category->researches()
            ->where('status', 'published')
            ->with('author:id,name')->withCount('chapters')
            ->latest('published_at')
            ->paginate(12);

        return view('public.research.category', compact('category', 'researches'));
    }

    public function show(Research $research)
    {
        abort_unless($research->isPublished(), 404);

        if (auth()->check()) {
            return redirect()->route('library.show', $research);
        }

        $research->load([
            'category', 'author:id,name',
            'chapters.sections:id,research_chapter_id,heading,position',
        ]);

        $firstChapter = $research->chapters->first();

        return view('public.research.show', compact('research', 'firstChapter'));
    }
}
