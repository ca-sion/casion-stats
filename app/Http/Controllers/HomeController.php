<?php

namespace App\Http\Controllers;

use App\Models\AthleteCategory;
use App\Models\Discipline;
use App\Models\Result;
use Illuminate\Contracts\Database\Query\Builder;
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
