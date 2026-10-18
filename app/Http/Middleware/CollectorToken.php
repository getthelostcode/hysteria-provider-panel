<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 采集器专用鉴权：X-Collector-Token: <COLLECTOR_TOKEN>。
 * 不配 token 时直接 503，避免“忘了配就裸奔”。
 */
class CollectorToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('collector.token');

        if ($expected === '') {
            return response()->json([
                'error' => 'collector_api_disabled',
                'message' => '面板未配置 COLLECTOR_TOKEN',
                'hint' => '若 .env 里已写入，执行 php artisan config:clear && php artisan config:cache；详见 php artisan collector:status',
            ], 503);
        }

        $given = (string) $request->header('X-Collector-Token', '');

        if (! hash_equals($expected, $given)) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => '缺少或错误的 X-Collector-Token',
            ], 401);
        }

        return $next($request);
    }
}
