<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\AthleteController;
use Illuminate\Support\Facades\Route;
use App\Livewire\ImportHistoricalData;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', [HomeController::class, 'show']);
Route::get('/athletes/{athlete}', [AthleteController::class, 'show'])->name('athletes.show');
Route::get('/import-historical', ImportHistoricalData::class)->name('import.historical');
Route::get('/deduplicate-athletes', \App\Livewire\DeduplicateAthletes::class)->name('athletes.deduplicate');
Route::get('/deduplicate-events', \App\Livewire\DeduplicateEvents::class)->name('events.deduplicate');
Route::get('/deduplicate-disciplines', \App\Livewire\DeduplicateDisciplines::class)->name('disciplines.deduplicate');
Route::get('/leaderboard', \App\Livewire\Leaderboard::class)->name('leaderboard');
Route::get('/qualifications/check', \App\Livewire\CheckQualifications::class)->name('qualifications.check');
