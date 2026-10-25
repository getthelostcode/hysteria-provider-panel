<?php

namespace App\Http\Controllers\Api;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeKickQueue;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 采集器（hysteria-traffic-collector）专用接口，前缀 /api/collector/v1，令牌 X-Collector-Token。
 *
 * 采集器的所有数据操作都走这里，不再直接读面板数据库：
 *   GET  /nodes        拿节点清单（含解密后的 traffic secret）
 *   POST /ingest       回传每个用户的流量增量 + 在线快照
 *   GET  /kick-queue   拿待踢 uuid
 *   POST /kick-result  回传踢人结果
 */
class CollectorApiController
{
    public function health(): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'app' => config('app.name'),
            'time' => now()->toIso8601String(),
            'pending_kick' => NodeKickQueue::query()->pending()->count(),
        ]);
    }

    public function nodes(): JsonResponse
    {
        $nodes = Node::query()
            ->with('provider')
            ->where('status', Node::STATUS_ACTIVE)
            ->whereHas('provider', fn ($q) => $q->where('status', Provider::STATUS_ACTIVE))
            ->orderBy('id')
            ->get()
            ->map(fn (Node $node): array => [
                'id' => (string) $node->id,
                'name' => $node->name ?: $node->ip,
                'hostname' => $node->hostname,
                'api_base' => $node->collectorApiBase(),
                'secret' => $node->traffic_secret,
                'traffic_port' => (int) $node->traffic_port,
                'provider' => $node->provider?->code,
            ])
            ->values();

        return response()->json(['nodes' => $nodes, 'count' => $nodes->count()]);
    }

    public function ingest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'node_id' => ['required', 'string', 'max:64'],
            'polled_at' => ['nullable', 'date'],
            'users' => ['array', 'max:5000'],
            'users.*.user_id' => ['required', 'string', 'max:190'],
            'users.*.tx' => ['nullable', 'integer', 'min:0'],
            'users.*.rx' => ['nullable', 'integer', 'min:0'],
            'online' => ['array', 'max:5000'],
            'online.*.user_id' => ['required', 'string', 'max:190'],
            'online.*.connections' => ['nullable', 'integer', 'min:0'],
        ]);

        $node = Node::query()->find($data['node_id']);
        if (! $node) {
            return response()->json(['error' => 'unknown_node', 'message' => '面板里没有该节点: ' . $data['node_id']], 404);
        }

        $polledAt = isset($data['polled_at']) ? Carbon::parse($data['polled_at'])->utc() : now()->utc();

        // 幂等：同一批（polled_at 不新于上次入库时间）重复上报直接忽略，避免重试重复计费
        if ($node->last_ingest_at !== null && $polledAt->lessThanOrEqualTo($node->last_ingest_at)) {
            return response()->json([
                'ok' => true,
                'duplicate' => true,
                'node_id' => (string) $node->id,
                'last_ingest_at' => $node->last_ingest_at->toIso8601String(),
            ]);
        }

        $day = $polledAt->toDateString();
        $users = $data['users'] ?? [];
        $online = $data['online'] ?? null;

        DB::transaction(function () use ($node, $users, $online, $day, $polledAt): void {
            /** @var array<string, EndUser|null> $quotaUsers */
            $quotaUsers = [];

            foreach ($users as $row) {
                $tx = (int) ($row['tx'] ?? 0);
                $rx = (int) ($row['rx'] ?? 0);
                if ($tx === 0 && $rx === 0) {
                    continue;
                }

                $record = NodeUserTraffic::query()->firstOrNew([
                    'node_id' => $node->id,
                    'user_id' => $row['user_id'],
                    'day' => $day,
                ]);
                $record->tx_bytes = (int) $record->tx_bytes + $tx;
                $record->rx_bytes = (int) $record->rx_bytes + $rx;
                $record->save();

                // 配额按「本期已用」累计：周期到了先清零，再加这一批增量
                $uuid = (string) $row['user_id'];
                if (! array_key_exists($uuid, $quotaUsers)) {
                    $quotaUsers[$uuid] = EndUser::query()->where('uuid', $uuid)->first();
                }

                $endUser = $quotaUsers[$uuid];
                if ($endUser !== null) {
                    $endUser->rolloverQuotaIfDue();
                    $endUser->increment('quota_used_bytes', $tx + $rx);
                }
            }

            if ($online !== null) {
                NodeUserOnline::query()->where('node_id', $node->id)->delete();
                foreach ($online as $row) {
                    NodeUserOnline::query()->create([
                        'node_id' => $node->id,
                        'user_id' => $row['user_id'],
                        'connections' => (int) ($row['connections'] ?? 0),
                        'seen_at' => $polledAt,
                    ]);
                }
            }

            $node->forceFill([
                'last_seen_at' => $polledAt,
                'last_ingest_at' => $polledAt,
            ])->save();
        });

        return response()->json([
            'ok' => true,
            'node_id' => (string) $node->id,
            'day' => $day,
            'users' => count($users),
            'online' => $online === null ? null : count($online),
        ]);
    }

    public function kickQueue(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 50);
        $limit = max(1, min($limit, 500));

        $rows = NodeKickQueue::query()
            ->pending()
            ->orderBy('id')
            ->limit($limit)
            ->get(['id', 'uuid', 'reason', 'requested_by', 'created_at'])
            ->map(fn (NodeKickQueue $row): array => [
                'id' => $row->id,
                'uuid' => $row->uuid,
                'reason' => $row->reason,
                'requested_by' => $row->requested_by,
                'created_at' => $row->created_at?->toIso8601String(),
            ]);

        return response()->json(['queue' => $rows, 'count' => $rows->count()]);
    }

    public function kickResult(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id' => ['nullable', 'integer'],
            'uuid' => ['nullable', 'string', 'max:190', 'required_without:id'],
            'node_id' => ['nullable', 'string', 'max:64'],
            'http_status' => ['required', 'integer', 'min:0', 'max:599'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $entry = isset($data['id'])
            ? NodeKickQueue::query()->find($data['id'])
            : NodeKickQueue::query()->pending()->where('uuid', $data['uuid'])->orderBy('id')->first();

        if (! $entry) {
            return response()->json(['error' => 'not_found', 'message' => '待踢记录不存在或已处理'], 404);
        }

        $status = ($data['http_status'] >= 200 && $data['http_status'] < 300)
            ? NodeKickQueue::STATUS_KICKED
            : NodeKickQueue::STATUS_FAILED;

        $entry->update([
            'status' => $status,
            'processed_at' => now(),
            'node_id' => $data['node_id'] ?? null,
            'http_status' => $data['http_status'],
            'note' => $data['note'] ?? null,
        ]);

        return response()->json([
            'ok' => true,
            'id' => $entry->id,
            'uuid' => $entry->uuid,
            'status' => $status,
        ]);
    }
}
