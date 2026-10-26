<?php

namespace App\Http\Controllers\Api;

use App\Models\Node;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * 官网 /site/ 的服务商列表（公开、只读）。
 *
 * 只返回：状态正常 + 平台后台勾选了「官网展示」的服务商。
 */
class PublicProviderController
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('public_providers:v1', 60, function (): array {
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

            return ['count' => count($providers), 'providers' => $providers];
        });

        return response()
            ->json($payload)
            ->header('Cache-Control', 'public, max-age=60');
    }
}
