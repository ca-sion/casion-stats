<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the home.
     */
    public function show(): View
    {
        return view('home');
    }
}
