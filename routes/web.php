<?php

use App\Http\Controllers\PublicJoinController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 每个运营商一个公开注册页：/join/{code}
Route::get('/join/{code}', [PublicJoinController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('join.show');

Route::post('/join/{code}', [PublicJoinController::class, 'store'])
    ->middleware('throttle:20,1')
    ->name('join.store');

Route::get('/join/{code}/welcome', [PublicJoinController::class, 'welcome'])
    ->middleware('throttle:60,1')
    ->name('join.welcome');
