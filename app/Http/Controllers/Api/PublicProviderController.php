<?php

namespace App\Http\Controllers\Api;

use App\Models\Node;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;

/**
 * 官网 /site/ 的服务商列表（公开、只读）。
 *
 * 只返回：状态正常 + 平台后台勾选了「官网展示」的服务商。
 */
class PublicProviderController
{
    public function __invoke(): JsonResponse
    {
        try {
            $ttl = (int) config('hysteria.node_online_ttl', 90);
            $onlineAfter = now()->subSeconds($ttl);

            $providers = Provider::query()
                ->where('status', Provider::STATUS_ACTIVE)
                ->where('listed', true)
                ->withCount([
                    'nodes as nodes_count' => fn ($q) => $q->where('status', Node::STATUS_ACTIVE),
                    'nodes as nodes_online' => fn ($q) => $q
                        ->where('status', Node::STATUS_ACTIVE)
                        ->where('last_seen_at', '>=', $onlineAfter),
                ])
                ->orderByDesc('nodes_online')
                ->orderBy('name')
                ->get()
                ->map(fn (Provider $provider): array => [
                    'code' => $provider->code,
                    'name' => $provider->name,
                    'nodes' => (int) $provider->nodes_count,
                    'online' => (int) $provider->nodes_online,
                    'join_url' => url('/join/' . $provider->code),
                    'free_url' => url('/site/free.html?code=' . rawurlencode($provider->code)),
                ])
                ->values()
                ->all();

            return response()
                ->json(['count' => count($providers), 'providers' => $providers])
                ->header('Cache-Control', 'no-store, max-age=0');
        } catch (QueryException $e) {
            // 没跑迁移时给明确信号，站点会显示「列表暂不可用」
            Log::warning('public providers api failed: ' . $e->getMessage());

            return response()
                ->json([
                    'count' => 0,
                    'providers' => [],
                    'error' => 'providers_table_not_migrated',
                    'message' => '面板缺 providers.listed 字段，请在面板目录执行 php artisan migrate --force',
                ], 503)
                ->header('Cache-Control', 'no-store, max-age=0');
        }
    }
}
