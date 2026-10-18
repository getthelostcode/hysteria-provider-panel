<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 待踢队列：面板里加一条 uuid（pending），采集器下一轮发现该用户在线就在对应节点踢掉，
 * 然后回调 collector API 把状态改成 kicked/failed。
 */
class NodeKickQueue extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_KICKED = 'kicked';
    public const STATUS_FAILED = 'failed';

    protected $table = 'node_kick_queue';

    protected $fillable = [
        'uuid', 'reason', 'status', 'requested_by',
        'processed_at', 'node_id', 'http_status', 'note',
    ];

    protected function casts(): array
    {
        return ['processed_at' => 'datetime', 'http_status' => 'integer'];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => '待踢',
            self::STATUS_KICKED => '已踢出',
            self::STATUS_FAILED => '失败',
            default => $this->status,
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_KICKED => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }
}
