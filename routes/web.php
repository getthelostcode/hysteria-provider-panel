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

// 官网服务商列表（公开只读）
Route::get('/api/public/providers', \App\Http\Controllers\Api\PublicProviderController::class)
    ->middleware('throttle:120,1')
    ->name('api.public.providers');

// 服务商自助注册（公开；PROVIDER_SIGNUP_ENABLED=false 可关闭）
Route::get('/provider-signup', [ProviderSignupController::class, 'show'])->name('provider.signup');
Route::post('/provider-signup', [ProviderSignupController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('provider.signup.store');

// 宣传站（静态文件在 public/site/；nginx 直接出，这里兜底）
// 必须落到 /site/index.html：页面里的 assets/... 才是相对 /site/ 解析
Route::get('/site', fn () => redirect('/site/index.html', 301))->name('site');
Route::get('/site/index.html', fn () => response(
    (string) file_get_contents(public_path('site/index.html')),
    200,
    ['Content-Type' => 'text/html; charset=UTF-8'],
));
