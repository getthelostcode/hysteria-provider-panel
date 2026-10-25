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
        'traffic_quota_bytes', 'quota_used_bytes', 'quota_reset_days', 'quota_reset_at',
        'expires_at', 'last_auth_at', 'register_ip',
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
            'quota_used_bytes' => 'integer',
            'quota_reset_days' => 'integer',
            'quota_reset_at' => 'datetime',
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

    /**
     * 重置 uuid（= 客户端账号/密码）并轮换 connect_token。
     *
     * 旧二维码/链接立即失效；历史流量与在线快照改挂到新 uuid，用户中心不会丢记录。
     */
    public function resetUuid(): string
    {
        $old = (string) $this->uuid;
        $new = Str::lower(Str::random(32));

        \Illuminate\Support\Facades\DB::transaction(function () use ($old, $new): void {
            \App\Models\NodeUserTraffic::query()->where('user_id', $old)->update(['user_id' => $new]);
            \App\Models\NodeUserOnline::query()->where('user_id', $old)->update(['user_id' => $new]);

            $this->forceFill([
                'uuid' => $new,
                'connect_token' => Str::lower(Str::random(32)),
            ])->save();
        });

        return $new;
    }

    /** 是否设了配额（空 = 不限） */
    public function hasQuota(): bool
    {
        return $this->traffic_quota_bytes !== null;
    }

    /** 本期是否已用完 */
    public function quotaExceeded(): bool
    {
        return $this->hasQuota() && (int) $this->quota_used_bytes >= (int) $this->traffic_quota_bytes;
    }

    /** 本期剩余字节（不限返回 null） */
    public function quotaRemainingBytes(): ?int
    {
        if (! $this->hasQuota()) {
            return null;
        }

        return max(0, (int) $this->traffic_quota_bytes - (int) $this->quota_used_bytes);
    }

    /**
     * 周期到了就清零（惰性执行，鉴权与采集写入时都会调）。
     *
     * - 没设 quota_reset_days：不重置（一次性额度），什么都不做
     * - quota_reset_at 为空：从现在起算一个周期
     * - 已过期：清零并把 quota_reset_at 推到下一个未来时间点
     */
    public function rolloverQuotaIfDue(): void
    {
        $days = (int) ($this->quota_reset_days ?? 0);
        if ($days <= 0) {
            return;
        }

        $now = now();

        if ($this->quota_reset_at === null) {
            $this->forceFill(['quota_reset_at' => $now->copy()->addDays($days)])->saveQuietly();

            return;
        }

        if ($this->quota_reset_at->isFuture()) {
            return;
        }

        $next = $this->quota_reset_at->copy();
        do {
            $next->addDays($days);
        } while ($next->isPast());

        $this->forceFill([
            'quota_used_bytes' => 0,
            'quota_reset_at' => $next,
        ])->saveQuietly();
    }

    /** 服务商/平台改了周期后，保证 quota_reset_at 与周期一致 */
    public function syncQuotaCycle(): void
    {
        $days = (int) ($this->quota_reset_days ?? 0);

        if ($days <= 0) {
            $this->forceFill(['quota_reset_at' => null])->saveQuietly();

            return;
        }

        if ($this->quota_reset_at === null) {
            $this->forceFill(['quota_reset_at' => now()->addDays($days)])->saveQuietly();
        }
    }

    /** 本期用量标签，例如 6.4 GB / 10 GB（不限返回"不限"） */
    public function quotaUsageLabel(): string
    {
        if (! $this->hasQuota()) {
            return '不限';
        }

        return self::humanBytes((int) $this->quota_used_bytes)
            . ' / ' . self::humanBytes((int) $this->traffic_quota_bytes);
    }

    /** 人类可读字节，去掉多余的 .00（6.50 GB → 6.5 GB，10.00 GB → 10 GB） */
    public static function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        $text = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $text . ' ' . $units[$i];
    }

    /** 下次重置文案 */
    public function quotaResetLabel(): string
    {
        if ((int) ($this->quota_reset_days ?? 0) <= 0) {
            return '不重置';
        }

        return $this->quota_reset_at?->format('Y-m-d H:i') ?? '待开始';
    }

    /** 该用户所属运营商的注册页 */
    public function joinUrl(): string
    {
        return url('/join/' . $this->provider->code);
    }
}
