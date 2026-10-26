<?php

namespace App\Filament\Provider\Widgets;

use App\Models\Node;
use App\Models\NodeUserTraffic;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;

/**
 * 近 N 天流量（上行 / 下行），数据来自 node_user_traffic，按当前运营商收窄。
 */
class ProviderTrafficChart extends ChartWidget
{
    protected static ?string $heading = '流量趋势';

    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $maxHeight = '280px';

    public ?string $filter = '14';

    protected function getFilters(): ?array
    {
        return [
            '7' => '近 7 天',
            '14' => '近 14 天',
            '30' => '近 30 天',
        ];
    }

    protected function getData(): array
    {
        $provider = Filament::getTenant();
        $days = (int) ($this->filter ?: 14);
        $dates = collect(range($days - 1, 0))
            ->map(fn (int $i): string => now()->utc()->subDays($i)->toDateString());

        $empty = [
            'datasets' => [
                ['label' => '上行', 'data' => array_fill(0, $dates->count(), 0)],
                ['label' => '下行', 'data' => array_fill(0, $dates->count(), 0)],
            ],
            'labels' => $dates->map(fn (string $day): string => substr($day, 5))->all(),
        ];

        if (! $provider) {
            return $empty;
        }

        $nodeIds = Node::query()->where('provider_id', $provider->id)->pluck('id');
        if ($nodeIds->isEmpty()) {
            return $empty;
        }

        $rows = NodeUserTraffic::query()
            ->whereIn('node_id', $nodeIds)
            ->where('day', '>=', $dates->first())
            ->selectRaw('day, COALESCE(SUM(tx_bytes),0) as tx, COALESCE(SUM(rx_bytes),0) as rx')
            ->groupBy('day')
            ->get()
            ->keyBy('day');

        $gb = fn (string $day, string $col): float => round(((int) ($rows[$day]->{$col} ?? 0)) / 1024 ** 3, 3);

        return [
            'datasets' => [
                [
                    'label' => '上行',
                    'data' => $dates->map(fn (string $day): float => $gb($day, 'tx'))->all(),
                    'borderColor' => '#34d399',
                    'backgroundColor' => 'rgba(52, 211, 153, .12)',
                    'pointBackgroundColor' => '#34d399',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'fill' => false,
                ],
                [
                    'label' => '下行',
                    'data' => $dates->map(fn (string $day): float => $gb($day, 'rx'))->all(),
                    'borderColor' => '#22d3ee',
                    'backgroundColor' => 'rgba(34, 211, 238, .12)',
                    'pointBackgroundColor' => '#22d3ee',
                    'borderWidth' => 2,
                    'tension' => 0.35,
                    'fill' => false,
                ],
            ],
            'labels' => $dates->map(fn (string $day): string => substr($day, 5))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    public function getDescription(): ?string
    {
        return '单位 GB · 按天汇总所有节点';
    }
}
