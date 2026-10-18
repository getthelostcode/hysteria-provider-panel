<?php

namespace App\Filament\User\Pages;

use App\Models\Node;
use App\Support\HysteriaUri;
use App\Support\QrCodeService;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

/**
 * 按服务器：该运营商每台服务器上「我自己」用了多少流量、当前几个连接、节点是否在线。
 */
class Servers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'servers';

    protected static string $view = 'filament.user.pages.servers';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationLabel = '服务器';

    protected static ?int $navigationSort = 20;

    public function getTitle(): string|Htmlable
    {
        return '服务器';
    }

    public function getSubheading(): ?string
    {
        return '每台服务器上你的流量与连接情况';
    }

    public function table(Table $table): Table
    {
        $user = Auth::guard('end_user')->user();

        return $table
            ->query(
                Node::query()
                    ->where('provider_id', $user->provider_id)
                    ->where('status', Node::STATUS_ACTIVE)
                    ->select('nodes.*')
                    ->selectSub(
                        NodeUserTraffic::query()
                            ->selectRaw('coalesce(sum(tx_bytes),0)')
                            ->whereColumn('node_id', 'nodes.id')
                            ->where('user_id', $user->uuid),
                        'my_tx',
                    )
                    ->selectSub(
                        NodeUserTraffic::query()
                            ->selectRaw('coalesce(sum(rx_bytes),0)')
                            ->whereColumn('node_id', 'nodes.id')
                            ->where('user_id', $user->uuid),
                        'my_rx',
                    )
                    ->selectSub(
                        NodeUserOnline::query()
                            ->selectRaw('coalesce(sum(connections),0)')
                            ->whereColumn('node_id', 'nodes.id')
                            ->where('user_id', $user->uuid),
                        'my_connections',
                    ),
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('服务器')
                    ->description(fn (Node $record): string => ($record->hostname ?: $record->ip) . ':' . (int) config('hysteria.public_port', 443))
                    ->weight('bold')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('状态')
                    ->badge()
                    ->state(fn (Node $record): string => $record->last_seen_at === null
                        ? '未上报'
                        : ($record->isOnline() ? '在线' : '离线'))
                    ->color(fn (Node $record): string => $record->last_seen_at === null
                        ? 'gray'
                        : ($record->isOnline() ? 'success' : 'danger')),
                Tables\Columns\TextColumn::make('my_tx')
                    ->label('我的上行')
                    ->formatStateUsing(fn ($state): string => NodeUserTraffic::human((int) $state))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('my_rx')
                    ->label('我的下行')
                    ->formatStateUsing(fn ($state): string => NodeUserTraffic::human((int) $state))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('my_total')
                    ->label('我的合计')
                    ->state(fn (Node $record): string => NodeUserTraffic::human((int) $record->my_tx + (int) $record->my_rx))
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('my_connections')
                    ->label('我的连接数')
                    ->alignEnd()
                    ->badge()
                    ->color(fn ($state): string => ((int) $state) > 0 ? 'success' : 'gray'),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('最后心跳')
                    ->since()
                    ->placeholder('从未'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('connect')
                    ->label('连接 / 二维码')
                    ->icon('heroicon-m-qr-code')
                    ->color('primary')
                    ->modalHeading(fn (Node $record): string => '连接 ' . $record->name)
                    ->modalDescription('手机扫二维码导入，或复制链接到客户端')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalContent(function (Node $record) use ($user) {
                        $uri = HysteriaUri::for($record, $user, (string) $user->provider?->code);

                        return view('filament.user.partials.connect', [
                            'uri' => $uri,
                            'qr' => QrCodeService::svg($uri),
                        ]);
                    }),
            ])
            ->emptyStateHeading('服务商暂时没有可用服务器');
    }
}
