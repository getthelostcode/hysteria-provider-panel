<?php

namespace App\Support;

use App\Models\EndUser;
use App\Models\Node;

/**
 * 生成 Hysteria2 连接 URL（可直接导入客户端 / 生成二维码）。
 *
 *   hysteria2://<uuid>@<host>:<port>/?sni=<sni>&obfs=salamander
 *             &obfs-password=<运营商识别码>&pinSHA256=<证书SHA256，hex 无冒号>#<IP>
 *
 * - 账号就是 uuid，URL 里不带密码（/auth 认「只有 uuid」的 auth）
 * - sni 固定为 bing.com（TLS 只靠 pinSHA256 校验，与节点域名无关）
 * - 指纹用节点上报的 cert_pin_sha256；没有才 insecure=1
 *
 * 有证书 pin 时不需要 insecure；没有则带 insecure=1（自签证书）。
 */
class HysteriaUri
{
    /** 客户端/二维码里的 SNI 固定值 */
    public const SNI = 'bing.com';

    public static function for(Node $node, EndUser $user, ?string $providerCode = null, ?int $port = null): string
    {
        // 连接地址以 IP 为准（节点换域名/CDN 时不影响客户端；证书靠 pinSHA256）
        $host = trim((string) $node->ip) !== '' ? (string) $node->ip : $node->hostForClient();
        $port = $port ?? (int) config('hysteria.public_port', 443);
        $code = $providerCode ?? (string) $user->provider?->code;

        // 账号就是 uuid（URL 里不带密码）
        $userinfo = rawurlencode((string) $user->uuid);

        $query = [
            'sni' => self::SNI,
            'obfs' => 'salamander',
            'obfs-password' => $code,
        ];

        // 指纹用 register-hysteria 上报的证书 SHA256，hex 大写、不带冒号
        if (($pin = $node->certPinHex()) !== null) {
            $query['pinSHA256'] = $pin;
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

    private static function formatHost(string $host): string
    {
        return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false ? '[' . $host . ']' : $host;
    }
}
