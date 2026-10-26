<?php

namespace App\Filament\Provider\Widgets;

use App\Filament\Provider\Resources\NodeResource;
use App\Models\Node;
use App\Support\Bytes;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * 仪表板：当前运营商的节点用量（在线状态 + 今日/累计流量）。
 */
class ProviderNodesTable extends TableWidget
{
    protected static ?string $heading = '节点用量';

    protected static bool $isLazy = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $provider = Filament::getTenant();
        $today = now()->utc()->toDateString();

        $query = Node::query()
            ->where('provider_id', $provider?->id ?? 0)
            ->withSum(['userTraffic as today_tx' => fn (Builder $q) => $q->where('day', $today)], 'tx_bytes')
            ->withSum(['userTraffic as today_rx' => fn (Builder $q) => $q->where('day', $today)], 'rx_bytes')
            ->withSum('userTraffic as total_tx', 'tx_bytes')
            ->withSum('userTraffic as total_rx', 'rx_bytes')
            ->orderByDesc('last_seen_at');

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('ip')
                    ->label('节点')
                    ->weight('bold')
                    ->copyable()
                    ->description(fn (Node $record): ?string => $record->hostname ?: null),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (Node $record): string => $record->runtimeStatusLabel())
                    ->color(fn (Node $record): string => $record->runtimeStatusColor()),
                Tables\Columns\TextColumn::make('today_bytes')
                    ->label('今日流量')
                    ->state(fn (Node $record): string => Bytes::human((int) ($record->today_tx ?? 0) + (int) ($record->today_rx ?? 0)))
                    ->description(fn (Node $record): string => Bytes::upDown((int) ($record->today_tx ?? 0), (int) ($record->today_rx ?? 0))),
                Tables\Columns\TextColumn::make('total_bytes')
                    ->label('累计流量')
                    ->state(fn (Node $record): string => Bytes::human((int) ($record->total_tx ?? 0) + (int) ($record->total_rx ?? 0)))
                    ->description(fn (Node $record): string => Bytes::upDown((int) ($record->total_tx ?? 0), (int) ($record->total_rx ?? 0))),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('最后心跳')
                    ->since()
                    ->placeholder('从未'),
            ])
            ->paginated([5, 10, 25])
            ->headerActions([
                Tables\Actions\Action::make('all_nodes')
                    ->label('查看全部节点')
                    ->link()
                    ->url($provider ? NodeResource::getUrl(tenant: $provider) : null),
            ])
            ->emptyStateHeading('还没有接入节点')
            ->emptyStateDescription('在节点上执行一键接入命令，节点会自动出现在这里。')
            ->emptyStateIcon('heroicon-o-server-stack');
    }
}
