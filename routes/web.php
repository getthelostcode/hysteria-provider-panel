<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// 兼容旧链接：/join/{code} 跳到 Filament 面板（JoinPanelProvider）里的注册页
Route::get('/join/{code}', function (string $code) {
    return redirect("/join/{$code}/register");
})->name('join.show');
