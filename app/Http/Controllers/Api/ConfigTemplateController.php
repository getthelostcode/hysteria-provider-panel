<?php

namespace App\Http\Controllers\Api;

use App\Models\ConfigTemplate;
use Illuminate\Http\JsonResponse;

/**
 * 标准版 Hysteria config.yaml（节点安装脚本拉取）。
 *
 *   GET /api/config-template
 *   {"name":"standard","version":3,"sha256":"…","updated_at":"…","content":"listen: ':443'\n…"}
 *
 * 内容里的 obfs 密码 / trafficStats / 证书路径都是占位或示例值，
 * install.sh 会用本节点的识别码、端口、secret 覆盖后再写入 /etc/hysteria/config.yaml。
 */
class ConfigTemplateController
{
    public function show(): JsonResponse
    {
        $template = ConfigTemplate::standard();

        return response()->json([
            'name' => $template->name,
            'version' => $template->version,
            'sha256' => $template->sha256(),
            'updated_at' => $template->updated_at?->toIso8601String(),
            'content' => $template->content,
        ]);
    }
}
