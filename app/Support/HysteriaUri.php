<?php

namespace App\Support;

use App\Models\EndUser;
use App\Models\Node;

/**
 * 生成 Hysteria2 连接 URL（可直接导入客户端 / 生成二维码）。
 *
 *   hysteria2://<uuid>@<host>:<port>/?sni=<sni>&obfs=salamander
 *             &obfs-password=<运营商识别码>&pinSHA256=<证书pin>#<host>
 *
 * - 账号就是 uuid，URL 里不带密码（/auth 认「只有 uuid」的 auth）
 * - sni：节点没有域名时用 config('hysteria.uri_sni')（默认 bing.com），有域名就用域名
 * - 指纹用节点上报的 cert_pin_sha256；没有才 insecure=1
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

        // 账号就是 uuid（URL 里不带密码）
        $userinfo = rawurlencode((string) $user->uuid);

        $query = [
            'sni' => self::sniFor($node, $host),
            'obfs' => 'salamander',
            'obfs-password' => $code,
        ];

        // 指纹用 register-hysteria 上报的 cert_pin_sha256（base64(SHA256(DER))）
        if (filled($node->cert_pin_sha256)) {
            $query['pinSHA256'] = $node->cert_pin_sha256;
        } else {
            $query['insecure'] = '1';
        }

        // 片段用地址（客户端里显示成 "<host>"）
        $fragment = rawurlencode($host);

        return sprintf(
            'hysteria2://%s@%s:%d/?%s#%s',
            $userinfo,
            self::formatHost($host),
            $port,
            http_build_query($query, '', '&', PHP_QUERY_RFC3986),
            $fragment,
        );
    }

    /** 节点没有域名（hostname 是 IP 或空）时，SNI 用配置值（默认 bing.com） */
    private static function sniFor(Node $node, string $fallbackHost): string
    {
        $hostname = (string) $node->hostname;

        if ($hostname !== '' && filter_var($hostname, FILTER_VALIDATE_IP) === false) {
            return $hostname;
        }

        return (string) (config('hysteria.uri_sni') ?: $fallbackHost);
    }

    private static function formatHost(string $host): string
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $host . ']' : $host;
    }
}
