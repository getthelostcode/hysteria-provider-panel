<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 标准版 Hysteria config.yaml 模板。
 *
 * 平台后台「系统 → Hysteria 配置模板」里编辑保存（version 自增），
 * 节点安装脚本通过 GET /api/config-template 拉取，再用自己的识别码/端口/secret 渲染。
 */
class ConfigTemplate extends Model
{
    public const NAME = 'standard';

    protected $fillable = ['name', 'content', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public static function standard(): self
    {
        return self::query()->firstOrCreate(
            ['name' => self::NAME],
            ['content' => self::defaultContent(), 'version' => 1],
        );
    }

    public function sha256(): string
    {
        return hash('sha256', $this->content);
    }

    public static function defaultContent(): string
    {
        return <<<'YAML'
listen: ':443'
tls:
  cert: /etc/hysteria/cert.pem
  key: /etc/hysteria/key.pem
obfs:
  type: salamander
  salamander:
    password: change-me-operator-code
auth:
  type: http
  http:
    url: 'https://auth.ipx.sarl/auth'
    insecure: false
trafficStats:
  listen: '0.0.0.0:8080'
  secret: change-me-generated-per-node
masquerade:
  type: file
  file:
    dir: /var/www/hysteria
bandwidth:
  up: '1 gbps'
  down: '1 gbps'
quic:
  initStreamReceiveWindow: 8388608
  maxStreamReceiveWindow: 8388608
  initConnReceiveWindow: 20971520
  maxConnReceiveWindow: 20971520
  maxIdleTimeout: 30s
YAML;
    }
}
