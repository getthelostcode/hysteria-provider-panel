<?php

namespace App\Filament\Join\Pages;

use App\Models\EndUser;
use App\Models\Node;
use App\Models\NodeUserOnline;
use App\Models\NodeUserTraffic;
use App\Models\Provider;
use App\Support\HysteriaUri;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;

/**
 * 开通结果：/join/{code}/welcome
 * - 我的账号（状态/到期/配额/已用流量/uuid/在线连接数）
 * - 该运营商的服务器表格（Filament Table）：状态/在线人数/连接数/最后心跳/版本 + 客户端配置弹窗
 */
class Welcome extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'welcome';

    protected static string $view = 'filament.join.pages.welcome';

    protected static bool $shouldRegisterNavigation = false;

    public Provider $provider;

    public ?EndUser $user = null;

    public int $usedBytes = 0;

    public int $myConnections = 0;

    public function mount(string $code): void
    {
        $this->provider = Provider::query()->where('code', $code)->first() ?? abort(404);
        abort_unless($this->provider->isActive(), 404);

        $userId = session('join_user_id');
        $user = $userId ? EndUser::query()->where('provider_id', $this->provider->id)->find($userId) : null;

        if (! $user) {
            $this->redirect(route('filament.join.pages.register', ['code' => $this->provider->code]));

            return;
        }

        $this->user = $user;

        $traffic = NodeUserTraffic::query()
            ->where('user_id', $user->uuid)
            ->selectRaw('coalesce(sum(tx_bytes),0) as tx, coalesce(sum(rx_bytes),0) as rx')
            ->first();

        $this->usedBytes = (int) (($traffic->tx ?? 0) + ($traffic->rx ?? 0));
        $this->myConnections = (int) NodeUserOnline::query()->where('user_id', $user->uuid)->sum('connections');
    }

    public function getTitle(): string|Htmlable
    {
        return '开通成功';
    }

    public function getHeading(): string
    {
        return '开通成功';
    }

    public function getSubheading(): ?string
    {
        return '账号已开通，下面任选一台服务器就能用';
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Node::query()
                    ->where('provider_id', $this->provider->id)
                    ->where('status', Node::STATUS_ACTIVE)
                    ->withCount('onlineUsers as online_users_count')
                    ->withSum('onlineUsers as connections_sum', 'connections'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('ip')
                    ->label('服务器')
                    ->state(fn (Node $record): string => (string) $record->ip)
                    ->description(fn (Node $record): string => $record->clientLabel((int) config('hysteria.public_port', 443)))
                    ->weight('bold')
                    ->copyable()
                    ->searchable(['ip', 'name', 'hostname']),
                Tables\Columns\TextColumn::make('status_label')
                    ->label('状态')
                    ->badge()
                    ->state(fn (Node $record): string => $this->statusLabel($record))
                    ->color(fn (Node $record): string => $this->statusColor($record)),
                Tables\Columns\TextColumn::make('online_users_count')
                    ->label('在线人数')
                    ->numeric()
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('connections_sum')
                    ->label('连接数')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): int => (int) $state),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label('最后心跳')
                    ->since()
                    ->description(fn (Node $record): ?string => $record->last_seen_at?->format('Y-m-d H:i:s'))
                    ->placeholder('从未'),
                Tables\Columns\TextColumn::make('version')
                    ->label('版本')
                    ->placeholder('—'),
            ])
            ->defaultSort('name')
            ->paginated(false)
            ->actions([
                Tables\Actions\Action::make('client_config')
                    ->label('客户端配置')
                    ->icon('heroicon-m-clipboard-document')
                    ->color('gray')
                    ->modalHeading(fn (Node $record): string => '客户端配置 · ' . $record->name)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('关闭')
                    ->modalContent(fn (Node $record) => view('filament.join.partials.client-config', [
                        'yaml' => $this->clientYaml($record),
                    ]))
                    ->action(fn () => Notification::make()
                        ->title('右侧「复制」按钮可一键复制')
                        ->success()
                        ->send()),
            ])
            ->emptyStateHeading('服务商暂时没有可用服务器')
            ->emptyStateDescription('稍后刷新页面，或联系服务商。');
    }

    public function statusLabel(Node $node): string
    {
        if ($node->last_seen_at === null) {
            return '未上报';
        }

        return $this->isOnline($node) ? '在线' : '离线';
    }

    public function statusColor(Node $node): string
    {
        if ($node->last_seen_at === null) {
            return 'gray';
        }

        return $this->isOnline($node) ? 'success' : 'danger';
    }

    public function isOnline(Node $node): bool
    {
        $ttl = (int) config('hysteria.node_online_ttl', 90);

        return $node->last_seen_at !== null && $node->last_seen_at->gt(now()->subSeconds($ttl));
    }

    public function quotaLabel(): string
    {
        if (! $this->user?->traffic_quota_bytes) {
            return '不限';
        }

        return NodeUserTraffic::human((int) $this->user->traffic_quota_bytes);
    }

    public function usedLabel(): string
    {
        return NodeUserTraffic::human($this->usedBytes);
    }

    public function expiresLabel(): string
    {
        return $this->user?->expires_at?->format('Y-m-d H:i') ?? '不限';
    }

    private function clientYaml(Node $node): string
    {
        $host = $node->hostForClient();
        $port = (int) config('hysteria.public_port', 443);

        return implode("\n", array_merge([
            'server: ' . $host . ':' . $port,
            'auth: ' . $this->user->uuid,
            'obfs:',
            '  type: salamander',
            '  salamander:',
            '    password: ' . $this->provider->code,
            'tls:',
            '  sni: ' . HysteriaUri::SNI,
        ], $node->tlsLines())) . "\n";
    }
}
