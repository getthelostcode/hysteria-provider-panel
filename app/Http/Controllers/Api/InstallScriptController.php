<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Response;

/**
 * 一键安装脚本：GET /api/install.sh
 *
 * 由平台后台下发（服务商后台「配置模板」页直接给出 curl … | bash 的命令），
 * 脚本本身是 register-hysteria 的 install.sh，放在 resources/hysteria/install.sh，
 * 更新时把新版脚本覆盖进去即可（页面底部显示 sha256 与更新时间，方便核对版本）。
 */
class InstallScriptController
{
    public function show(): Response
    {
        $path = self::path();

        abort_unless(is_file($path), 500, '安装脚本缺失：resources/hysteria/install.sh');

        return response(file_get_contents($path), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-store',
            'X-Install-Script-Sha256' => hash_file('sha256', $path),
        ]);
    }

    public static function path(): string
    {
        return resource_path('hysteria/install.sh');
    }

    /** @return array{sha256: string, updated_at: string, bytes: int}|null */
    public static function info(): ?array
    {
        $path = self::path();

        if (! is_file($path)) {
            return null;
        }

        return [
            'sha256' => hash_file('sha256', $path),
            'updated_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            'bytes' => (int) filesize($path),
        ];
    }
}
