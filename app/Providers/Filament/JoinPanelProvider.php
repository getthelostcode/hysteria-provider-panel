<?php

namespace App\Providers\Filament;

use App\Models\Provider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * 终端用户注册面板（公开，无后台、无登录）。
 *
 * 路径带运营商识别码：/join/{code}/*，每个运营商一套自己的注册页。
 * 页面：/join/{code}/register（表单）、/join/{code}/welcome（开通结果 + 服务器清单）。
 */
class JoinPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('join')
            ->path('join/{code}')
            ->brandName(fn (): string => (string) (Provider::query()->where('code', request()->route('code'))->value('name') ?: '开通账号'))
            // 首页/logo 链接默认是 url(path)（会带字面量 {code}），改成当前运营商的注册页
            ->homeUrl(fn (): string => route('filament.join.pages.register', ['code' => request()->route('code') ?? '']))
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->discoverPages(in: app_path('Filament/Join/Pages'), for: 'App\\Filament\\Join\\Pages')
            ->pages([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([]);
    }
}
