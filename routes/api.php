<?php

use App\Http\Controllers\Api\CleanestCityController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\HistoryController;
use Illuminate\Support\Facades\Route;

Route::get('/cleanest-city', CleanestCityController::class)->name('api.cleanest-city');
Route::get('/history', HistoryController::class)->name('api.history');
Route::get('/health', HealthController::class)->name('api.health');
