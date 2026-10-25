<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Provider extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'code', 'name', 'contact', 'status', 'notes',
        'default_quota_bytes', 'default_quota_reset_days',
    ];

    protected function casts(): array
    {
        return [
            'default_quota_bytes' => 'integer',
            'default_quota_reset_days' => 'integer',
        ];
    }

    public function nodes(): HasMany
    {
        return $this->hasMany(Node::class);
    }

    public function providerUsers(): HasMany
    {
        return $this->hasMany(ProviderUser::class);
    }

    /** 该运营商自定义的 config.yaml（没有就用平台标准模板） */
    public function configTemplate(): HasOne
    {
        return $this->hasOne(ProviderConfigTemplate::class);
    }

    /** 终端用户（该运营商的客户） */
    public function endUsers(): HasMany
    {
        return $this->hasMany(EndUser::class);
    }

    /** 该运营商的公开注册页 */
    public function joinUrl(): string
    {
        return url('/join/' . $this->code);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function statusLabel(): string
    {
        return $this->isActive() ? '正常' : '已停用';
    }

    public function statusColor(): string
    {
        return $this->isActive() ? 'success' : 'danger';
    }

    /** 面板里显示用：code 是租户标识，也是节点 obfs 密码 */
    public function getFilamentName(): string
    {
        return $this->name;
    }
}
