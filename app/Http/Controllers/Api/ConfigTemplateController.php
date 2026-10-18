<?php

namespace App\Http\Controllers\Api;

use App\Support\ConfigTemplateResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 标准版 Hysteria config.yaml（节点安装脚本拉取）。
 *
 *   GET /api/config-template[?code=<运营商识别码>]
 *   {"name":"standard","version":3,"source":"provider|standard","sha256":"…","updated_at":"…","content":"…"}
 *
 * 带 code 时优先返回该服务商在服务商后台保存的自定义模板，没有则回退平台标准。
 *
 * 内容里的 obfs 密码 / trafficStats / 证书路径都是占位或示例值，
 * install.sh 会用本节点的识别码、端口、secret 覆盖后再写入 /etc/hysteria/config.yaml。
 */
class ConfigTemplateController
{
    public function show(Request $request): JsonResponse
    {
        // ?code=运营商识别码 → 该服务商自定义模板优先，否则平台标准
        $resolved = ConfigTemplateResolver::forProviderCode(
            $request->query('code') !== null ? (string) $request->query('code') : null,
        );

        return response()->json([
            'name' => 'standard',
            'version' => $resolved['version'],
            'source' => $resolved['source'],
            'sha256' => $resolved['sha256'],
            'updated_at' => $resolved['updated_at'],
            'content' => $resolved['content'],
        ]);
    }
}
