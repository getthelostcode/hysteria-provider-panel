<?php

namespace App\Filament\Provider\Widgets;

use App\Filament\Provider\Resources\EndUserResource;
use App\Filament\Provider\Resources\NodeResource;
use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserTraffic;
use App\Support\Bytes;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * 服务商仪表板顶部指标：节点在线、用户、今日/近 7 天流量、超额用户。
 *
 * 全部按当前运营商租户收窄；流量取 node_user_traffic（与用户/节点页面同一数据源）。
 */
class ProviderStatsOverview extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $provider = Filament::getTenant();

        if (! $provider) {
            return [];
        }

        $ttl = (int) config('hysteria.node_online_ttl', 90);
        $today = now()->utc()->toDateString();
        $weekStart = now()->utc()->subDays(6)->toDateString();

        $nodes = Node::query()->where('provider_id', $provider->id);
        $nodeTotal = (clone $nodes)->count();
        $nodeOnline = (clone $nodes)
            ->where('status', Node::STATUS_ACTIVE)
            ->where('last_seen_at', '>=', now()->subSeconds($ttl))
            ->count();
        $nodeOffline = max(0, $nodeTotal - $nodeOnline);

        $nodeIds = (clone $nodes)->pluck('id');

        $userQuery = EndUser::query()->where('provider_id', $provider->id);
        $userTotal = (clone $userQuery)->count();
        $userActive = (clone $userQuery)->where('status', EndUser::STATUS_ACTIVE)->count();
        $userExceeded = (clone $userQuery)
            ->whereNotNull('traffic_quota_bytes')
            ->whereColumn('quota_used_bytes', '>=', 'traffic_quota_bytes')
            ->count();

        $sum = function (string $column, ?string $from = null) use ($nodeIds, $today): int {
            $query = NodeUserTraffic::query()->whereIn('node_id', $nodeIds);

            if ($from === null) {
                $query->where('day', $today);
            } else {
                $query->where('day', '>=', $from);
            }

            return (int) $query->sum($column);
        };

        $todayTx = $sum('tx_bytes');
        $todayRx = $sum('rx_bytes');
        $weekTx = $nodeIds->isEmpty() ? 0 : $sum('tx_bytes', $weekStart);
        $weekRx = $nodeIds->isEmpty() ? 0 : $sum('rx_bytes', $weekStart);
        $weekTotal = $weekTx + $weekRx;

        return [
            Stat::make('节点在线', "{$nodeOnline} / {$nodeTotal}")
                ->description($nodeTotal === 0 ? '还没有接入节点' : "离线 {$nodeOffline} 台 · {$ttl}s 内有心跳算在线")
                ->descriptionIcon($nodeTotal === 0 ? 'heroicon-m-plus-circle' : 'heroicon-m-server-stack')
                ->color($nodeTotal === 0 ? 'gray' : ($nodeOnline > 0 ? 'success' : 'danger'))
                ->url(NodeResource::getUrl(tenant: $provider)),

            Stat::make('用户', "{$userActive} / {$userTotal}")
                ->description($userTotal === 0 ? '还没有用户，去发行注册链接' : '启用 / 全部')
                ->descriptionIcon('heroicon-m-users')
                ->color('info')
                ->url(EndUserResource::getUrl(tenant: $provider)),

            Stat::make('今日流量', Bytes::human($todayTx + $todayRx))
                ->description('↑ ' . Bytes::human($todayTx) . '　↓ ' . Bytes::human($todayRx))
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('primary'),

            Stat::make('近 7 天流量', Bytes::human($weekTotal))
                ->description('日均 ' . Bytes::human((int) ($weekTotal / 7)))
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('gray'),

            Stat::make('超额用户', (string) $userExceeded)
                ->description($userExceeded > 0 ? '本期流量已用完，需要处理' : '本期没有用户超额')
                ->descriptionIcon($userExceeded > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($userExceeded > 0 ? 'danger' : 'gray')
                ->url(EndUserResource::getUrl(tenant: $provider)),
        ];
    }
}
