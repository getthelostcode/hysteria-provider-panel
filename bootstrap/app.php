<?php

use App\Http\Controllers\Api\CollectorApiController;
use App\Http\Controllers\Api\ConfigTemplateController;
use App\Http\Controllers\Api\InstallScriptController;
use App\Http\Controllers\Api\NodeRegistrationController;
use App\Http\Controllers\HysteriaAuthController;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (): void {
            // register-hysteria 的 install.sh 固定往 https://<host>/new 上报
            Route::middleware(['api', 'force.json'])
                ->post('/new', [NodeRegistrationController::class, 'store'])
                ->middleware('throttle:60,1')
                ->name('api.nodes.register');

            // Hysteria2 节点鉴权：POST /auth（节点 auth.http.url 指向这里）
            Route::middleware(['api', 'force.json'])
                ->post('/auth', HysteriaAuthController::class)
                ->middleware('throttle:600,1')
                ->name('api.auth');

            // 一键安装脚本（服务商后台给的 curl … | bash 命令用它）
            Route::middleware(['api'])
                ->get('/api/install.sh', [InstallScriptController::class, 'show'])
                ->middleware('throttle:120,1')
                ->name('api.install_script');

            // 标准版 Hysteria config.yaml（register-hysteria 的 install.sh 拉取）
            Route::middleware(['api', 'force.json'])
                ->get('/api/config-template', [ConfigTemplateController::class, 'show'])
                ->middleware('throttle:120,1')
                ->name('api.config_template');

            // 采集器专用：采集器所有数据操作都走这里，不直连面板数据库
            Route::prefix('api/collector/v1')
                ->middleware(['api', 'force.json', 'collector.token', 'throttle:600,1'])
                ->name('api.collector.')
                ->group(function (): void {
                    Route::get('/health', [CollectorApiController::class, 'health'])->name('health');
                    Route::get('/nodes', [CollectorApiController::class, 'nodes'])->name('nodes');
                    Route::post('/ingest', [CollectorApiController::class, 'ingest'])->name('ingest');
                    Route::get('/kick-queue', [CollectorApiController::class, 'kickQueue'])->name('kick_queue');
                    Route::post('/kick-result', [CollectorApiController::class, 'kickResult'])->name('kick_result');
                });
        },
    )
    // 显式注册 app/Console/Commands（panel:check / collector:status），避免部署后命令找不到
    ->withCommands()
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'force.json' => \App\Http\Middleware\ForceJsonResponse::class,
            'collector.token' => \App\Http\Middleware\CollectorToken::class,
        ]);
        // 面板/接口常部署在 Cloudflare Tunnel 或 nginx 后面，需按可信代理推导真实 IP
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES', '127.0.0.1,::1'),
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO,
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
