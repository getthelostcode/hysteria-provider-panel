<?php

namespace App\Support;

use App\Models\EndUser;
use App\Models\Node;

/**
 * 生成 Hysteria2 连接 URL（可直接导入客户端 / 生成二维码）。
 *
 *   hysteria2://<uuid>:<connect_token>@<host>:<port>/?sni=<host>&obfs=salamander
 *             &obfs-password=<运营商识别码>&pinSHA256=<证书pin>#<节点名>
 *
 * 有证书 pin 时不需要 insecure；没有则带 insecure=1（自签证书）。
 */
class HysteriaUri
{
    public static function for(Node $node, EndUser $user, ?string $providerCode = null, ?int $port = null): string
    {
        $host = $node->hostForClient();
        $port = $port ?? (int) config('hysteria.public_port', 443);
        $code = $providerCode ?? (string) $user->provider?->code;

        $userinfo = rawurlencode((string) $user->uuid) . ':' . rawurlencode((string) $user->connect_token);

        $query = [
            'sni' => $host,
            'obfs' => 'salamander',
            'obfs-password' => $code,
        ];

        if (filled($node->cert_pin_sha256)) {
            $query['pinSHA256'] = $node->cert_pin_sha256;
        } else {
            $query['insecure'] = '1';
        }

        $fragment = rawurlencode((string) ($node->name ?: $host));

        return sprintf(
            'hysteria2://%s@%s:%d/?%s#%s',
            $userinfo,
            self::formatHost($host),
            $port,
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            $fragment,
        );
    }

    private static function formatHost(string $host): string
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $host . ']' : $host;
    }
}
