<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Node extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'provider_id', 'name', 'ip', 'hostname', 'obfs_password',
        'traffic_port', 'traffic_secret', 'cert_sha256', 'cert_pin_sha256', 'version', 'config_version', 'status', 'meta',
        'last_seen_at', 'last_ingest_at', 'api_token_hash',
    ];

    protected $hidden = ['traffic_secret', 'api_token_hash'];

    protected function casts(): array
    {
        return [
            'traffic_secret' => 'encrypted',
            'meta' => 'array',
            'last_seen_at' => 'datetime',
            'last_ingest_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function trafficDaily(): HasMany
    {
        return $this->hasMany(NodeTrafficDaily::class);
    }

    /** 终端用户在线快照（采集器每轮整体替换） */
    public function onlineUsers(): HasMany
    {
        return $this->hasMany(NodeUserOnline::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function onlineTtl(): int
    {
        return (int) config('hysteria.node_online_ttl', 90);
    }

    /** 在线判定只看 DB 的 last_seen_at（Redis 里的 node:online:{id} 是给外部消费方用的加速副本） */
    public function isOnline(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subSeconds($this->onlineTtl()));
    }

    public function isDisabled(): bool
    {
        return $this->status === self::STATUS_DISABLED;
    }

    public function runtimeStatusLabel(): string
    {
        if ($this->isDisabled()) {
            return '已禁用';
        }

        return $this->isOnline() ? '在线' : '离线';
    }

    public function runtimeStatusColor(): string
    {
        if ($this->isDisabled()) {
            return 'gray';
        }

        return $this->isOnline() ? 'success' : 'danger';
    }

    public function trafficToday(): ?NodeTrafficDaily
    {
        return $this->trafficDaily()->where('date', now()->toDateString())->first();
    }

    public function trafficTodayBytes(): int
    {
        $row = $this->trafficToday();

        return $row ? $row->totalBytes() : 0;
    }

    /** 首次注册/重置时签发，明文只返回这一次，库里只存 sha256 */
    public function issueApiToken(): string
    {
        $plain = 'nd_' . Str::random(48);
        $this->api_token_hash = hash('sha256', $plain);
        $this->save();

        return $plain;
    }

    public static function findByApiToken(string $plain): ?self
    {
        if ($plain === '') {
            return null;
        }

        return static::query()->where('api_token_hash', hash('sha256', $plain))->first();
    }

    /** 采集器访问该节点 Traffic Stats API 的地址（IPv6 加方括号） */
    public function collectorApiBase(string $scheme = 'http'): string
    {
        $host = filter_var($this->ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false
            ? $this->ip
            : '[' . $this->ip . ']';

        return sprintf('%s://%s:%d', $scheme, $host, (int) $this->traffic_port);
    }

    /**
     * 客户端 pinSHA256 用的大写 hex（不带冒号）。
     *
     * 优先用上报的 cert_sha256（7A:FB:… → 7AFB…），没有则把 base64 的
     * cert_pin_sha256 转成 hex；两者都没有返回 null。
     */
    public function certPinHex(): ?string
    {
        if (filled($this->cert_sha256)) {
            $hex = strtoupper(str_replace(':', '', (string) $this->cert_sha256));

            return preg_match('/^[0-9A-F]{64}$/', $hex) ? $hex : null;
        }

        if (filled($this->cert_pin_sha256)) {
            $raw = base64_decode((string) $this->cert_pin_sha256, true);

            return $raw === false ? null : strtoupper(bin2hex($raw));
        }

        return null;
    }

    /** 客户端连接用的主机名：优先 hostname，否则 IP */
    public function hostForClient(): string
    {
        return $this->hostname ?: (string) $this->ip;
    }

    /**
     * TLS 行：有证书指纹就用 pinSHA256（不用 insecure），没有才退回 insecure: true。
     *
     * @return array<int, string>
     */
    public function tlsLines(): array
    {
        if (($pin = $this->certPinHex()) !== null) {
            return ['  insecure: false', '  pinSHA256: ' . $pin];
        }

        return ['  insecure: true      # 自签证书；有指纹时这里会变成 pinSHA256'];
    }

    /** 面板里给运维直接复制的客户端配置（服务器端口固定 443，由 install.sh 的 listen 决定） */
    public function clientConfigYaml(int $port = 443): string
    {
        return implode("\n", array_merge([
            "server: {$this->hostForClient()}:{$port}",
            'auth: <用户名:密码>   # 由鉴权服务发放',
            'obfs:',
            '  type: salamander',
            '  salamander:',
            "    password: {$this->obfs_password}",
            'tls:',
            '  sni: ' . \App\Support\HysteriaUri::SNI,
        ], $this->tlsLines()));
    }
}
