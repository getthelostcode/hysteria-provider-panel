<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

/**
 * 终端用户：运营商通过 /join/{code} 页自助注册，或服务商后台手工创建。
 * uuid 是 Hysteria 的 user id（/auth 返回它，流量与踢人都以它为准）。
 */
class EndUser extends Authenticatable implements FilamentUser, HasName
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'provider_id', 'uuid', 'email', 'name', 'password', 'connect_token', 'status',
        'traffic_quota_bytes', 'expires_at', 'last_auth_at', 'register_ip',
    ];

    protected $hidden = ['password', 'remember_token', 'connect_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'connect_token' => 'encrypted',
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

            if (empty($user->connect_token)) {
                $user->connect_token = Str::lower(Str::random(32));
            }
        });
    }

    /** 只有启用的账号（且运营商正常）才能进用户中心 */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'user' && $this->isActive();
    }

    /** 用户中心右上角/头像用的显示名：昵称优先，否则邮箱 */
    public function getFilamentName(): string
    {
        return (string) ($this->name ?: $this->email);
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
