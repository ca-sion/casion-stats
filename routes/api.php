<?php

use App\Http\Controllers\Api\V1\AthleteController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\DisciplineController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('/athletes', [AthleteController::class, 'index']);
    Route::get('/athletes/{id}', [AthleteController::class, 'show']);
    
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/disciplines', [DisciplineController::class, 'index']);
});
