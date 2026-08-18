<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;

class FaqController extends Controller
{
    public function index()
    {
        return view('public.faq');
    }
}
