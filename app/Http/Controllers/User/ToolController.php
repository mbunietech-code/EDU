<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Tool;

class ToolController extends Controller
{
    public function index()
    {
        $tools = Tool::published()
            ->orderBy('sort_order')
            ->paginate(12);

        return view('user.tools.index', compact('tools'));
    }

    public function show(Tool $tool)
    {
        if (! $tool->isPublished()) {
            abort(404);
        }

        return view('user.tools.show', compact('tool'));
    }
}
