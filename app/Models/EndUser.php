<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * 终端用户：运营商通过 /join/{code} 页自助注册，或服务商后台手工创建。
 * uuid 是 Hysteria 的 user id（/auth 返回它，流量与踢人都以它为准）。
 */
class EndUser extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'provider_id', 'uuid', 'email', 'name', 'password', 'status',
        'traffic_quota_bytes', 'expires_at', 'last_auth_at', 'register_ip',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'expires_at' => 'datetime',
            'last_auth_at' => 'datetime',
            'traffic_quota_bytes' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EndUser $user): void {
            if (empty($user->uuid)) {
                $user->uuid = Str::lower(Str::random(32));
            }
        });
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function isActive(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        return $this->provider?->isActive() ?? false;
    }

    public function statusLabel(): string
    {
        return $this->status === self::STATUS_ACTIVE ? '正常' : '已停用';
    }

    public function statusColor(): string
    {
        return $this->status === self::STATUS_ACTIVE ? 'success' : 'danger';
    }

    /** 该用户所属运营商的注册页 */
    public function joinUrl(): string
    {
        return url('/join/' . $this->provider->code);
    }
}
