<?php

namespace App\Filament\User\Pages;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * 用户中心首页：账号概览 + 今日/累计用量 + 常用入口。
 */
class Dashboard extends Page
{
    protected static ?string $slug = '/';

    protected static string $view = 'filament.user.pages.dashboard';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = '概览';

    protected static ?int $navigationSort = 0;

    public function getTitle(): string|Htmlable
    {
        return '概览';
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $user = Auth::guard('end_user')->user();
        $user->rolloverQuotaIfDue();

        $totals = NodeUserTraffic::query()
            ->where('user_id', $user->uuid)
            ->selectRaw('coalesce(sum(tx_bytes),0) as tx, coalesce(sum(rx_bytes),0) as rx')
            ->first();

        $today = NodeUserTraffic::query()
            ->where('user_id', $user->uuid)
            ->where('day', now()->utc()->toDateString())
            ->selectRaw('coalesce(sum(tx_bytes),0) as tx, coalesce(sum(rx_bytes),0) as rx')
            ->first();

        $nodes = Node::query()->where('provider_id', $user->provider_id)->where('status', Node::STATUS_ACTIVE)->count();

        $connections = (int) NodeUserOnline::query()->where('user_id', $user->uuid)->sum('connections');

        $quotaBytes = $user->traffic_quota_bytes !== null ? (int) $user->traffic_quota_bytes : null;
        $quotaUsed = (int) $user->quota_used_bytes;
        $quotaPercent = $quotaBytes !== null && $quotaBytes > 0
            ? min(100, (int) round($quotaUsed / $quotaBytes * 100))
            : null;

        return [
            'user' => $user,
            'provider' => $user->provider,
            'totalBytes' => (int) (($totals->tx ?? 0) + ($totals->rx ?? 0)),
            'todayBytes' => (int) (($today->tx ?? 0) + ($today->rx ?? 0)),
            'nodes' => $nodes,
            'connections' => $connections,
            'quotaLabel' => $user->hasQuota() ? EndUser::humanBytes((int) $user->traffic_quota_bytes) : '不限',
            'quotaUsageLabel' => $user->quotaUsageLabel(),
            'quotaResetLabel' => $user->quotaResetLabel(),
            'quotaExceeded' => $user->quotaExceeded(),
            'quotaBytes' => $quotaBytes,
            'quotaPercent' => $quotaPercent,
            'quotaRemainingLabel' => $quotaBytes !== null ? EndUser::humanBytes(max(0, $quotaBytes - $quotaUsed)) : null,
            'quotaBarColor' => $quotaPercent === null
                ? 'primary'
                : ($quotaPercent >= 100 ? 'danger' : ($quotaPercent >= 80 ? 'warning' : 'primary')),
            'quotaBarHex' => $quotaPercent === null
                ? '#10b981'
                : ($quotaPercent >= 100 ? '#ef4444' : ($quotaPercent >= 80 ? '#f59e0b' : '#10b981')),
            'expiresLabel' => $user->expires_at?->format('Y-m-d H:i') ?? '不限',
        ];
    }
}
