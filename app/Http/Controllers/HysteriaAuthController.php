<?php

namespace App\Http\Controllers;

use App\Models\EndUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Hysteria2 HTTP 鉴权端点：POST /auth
 *
 * 节点配置：
 *   auth:
 *     type: http
 *     http:
 *       url: https://<面板域名>/auth
 *       insecure: false
 *
 * Hysteria 发来的 body（JSON）：
 *   {"addr":"1.2.3.4:5678","auth":"<base64(email:password)>","tx":0,"rx":0}
 *   {"addr":"1.2.3.4:5678","auth":"<base64(uuid:connect_token)>","tx":0,"rx":0}
 * 兼容非 base64 的明文 "email:password"。
 *
 * 返回：{"ok":true,"id":"<uuid>"} 或 {"ok":false,"msg":"原因"}
 * id 就是流量统计与踢人用的 user id。
 */
class HysteriaAuthController
{
    public function __invoke(Request $request): JsonResponse
    {
        $raw = (string) $request->input('auth', '');

        if ($raw === '') {
            return response()->json(['ok' => false, 'msg' => 'missing auth'], 200);
        }

        $decoded = base64_decode($raw, true);
        $pair = ($decoded !== false && str_contains($decoded, ':')) ? $decoded : $raw;

        [$login, $password] = array_pad(explode(':', $pair, 2), 2, '');
        $login = trim($login);

        if ($login === '' || $password === '') {
            return response()->json(['ok' => false, 'msg' => 'bad auth format'], 200);
        }

        $user = EndUser::query()
            ->with('provider')
            ->where('email', mb_strtolower($login))
            ->orWhere('uuid', $login)
            ->first();

        if ($user === null) {
            return response()->json(['ok' => false, 'msg' => '账号或密码错误'], 200);
        }

        // 两种凭据都认：邮箱 + 密码（用户自己设的），或 uuid + connect_token（URL/二维码里那个）
        $tokenOk = filled($user->connect_token) && hash_equals((string) $user->connect_token, $password);

        if (! $tokenOk && ! Hash::check($password, $user->password)) {
            return response()->json(['ok' => false, 'msg' => '账号或密码错误'], 200);
        }

        if ($user->status !== EndUser::STATUS_ACTIVE) {
            return response()->json(['ok' => false, 'msg' => '账号已停用，请联系服务商'], 200);
        }

        if ($user->expires_at !== null && $user->expires_at->isPast()) {
            return response()->json(['ok' => false, 'msg' => '账号已到期'], 200);
        }

        if (! $user->provider?->isActive()) {
            return response()->json(['ok' => false, 'msg' => '服务商已停用'], 200);
        }

        // 写库节流：同一用户 60s 内只更新一次 last_auth_at
        if ($user->last_auth_at === null || $user->last_auth_at->lt(now()->subMinute())) {
            $user->forceFill(['last_auth_at' => now()])->saveQuietly();
        }

        return response()->json(['ok' => true, 'id' => $user->uuid]);
    }
}
