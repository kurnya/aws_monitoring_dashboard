<?php

use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WeatherController::class, 'index']);
Route::get('/api/latest', [WeatherController::class, 'latest']);
Route::get('/api/historical', [WeatherController::class, 'getHistoricalData'])->name('api.historical');
Route::get('/historical', [WeatherController::class, 'historical'])->name('historical');



