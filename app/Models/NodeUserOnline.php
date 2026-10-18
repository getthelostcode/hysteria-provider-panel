<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NodeUserOnline extends Model
{
    use HasFactory;

    protected $table = 'node_user_online';

    protected $fillable = ['node_id', 'user_id', 'connections', 'seen_at'];

    protected function casts(): array
    {
        return ['connections' => 'integer', 'seen_at' => 'datetime'];
    }

    public function node(): BelongsTo
    {
        return $this->belongsTo(Node::class);
    }
}
