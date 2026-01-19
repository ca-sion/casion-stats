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
