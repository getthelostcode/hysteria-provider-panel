<?php

use App\Http\Controllers\ProviderSignupController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 兼容旧链接：/join/{code} 跳到 Filament 面板（JoinPanelProvider）里的注册页
Route::get('/join/{code}', function (string $code) {
    return redirect("/join/{$code}/register");
})->name('join.show');

// 服务商自助注册（公开；PROVIDER_SIGNUP_ENABLED=false 可关闭）
Route::get('/provider-signup', [ProviderSignupController::class, 'show'])->name('provider.signup');
Route::post('/provider-signup', [ProviderSignupController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('provider.signup.store');
