<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 注册/心跳是给机器调的接口：无论调用方有没有带 Accept 头，都要返回 JSON，
 * 否则 Laravel 会把校验失败转成 302 + HTML（`redirect()->back()`），
 * 调用方（register-hysteria）只能看到一个跳转，拿不到 unknown_code 这类错误语义。
 */
class ForceJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        return $next($request);
    }
}
