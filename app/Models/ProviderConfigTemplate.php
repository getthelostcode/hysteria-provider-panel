<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 服务商自定义的标准 config.yaml（有则覆盖平台标准模板）。
 */
class ProviderConfigTemplate extends Model
{
    protected $fillable = ['provider_id', 'content', 'version', 'updated_by'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function sha256(): string
    {
        return hash('sha256', $this->content);
    }
}
