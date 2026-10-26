<?php

namespace App\Filament\Provider\Widgets;

use App\Filament\Provider\Resources\EndUserResource;
use App\Models\EndUser;
use App\Support\Bytes;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * 仪表板：本期用量最高的用户（按 quota_used_bytes 排序，最多 5 个）。
 */
class ProviderTopUsers extends TableWidget
{
    protected static ?string $heading = '用量最高的用户';

    protected static bool $isLazy = false;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $provider = Filament::getTenant();

        $query = EndUser::query()
            ->where('provider_id', $provider?->id ?? 0)
            ->withSum('traffic as total_tx', 'tx_bytes')
            ->withSum('traffic as total_rx', 'rx_bytes')
            ->orderByDesc('quota_used_bytes')
            ->limit(5);

        return $table
            ->query($query)
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('用户')
                    ->weight('bold')
                    ->description(fn (EndUser $record): ?string => $record->name ?: null),
                Tables\Columns\TextColumn::make('quota_used_bytes')
                    ->label('本期用量')
                    ->state(fn (EndUser $record): string => $record->quotaUsageLabel())
                    ->description(fn (EndUser $record): string => $record->quotaExceeded()
                        ? '已用尽'
                        : ($record->hasQuota() ? '下次 ' . $record->quotaResetLabel() : '不限流量'))
                    ->color(fn (EndUser $record): string => $record->quotaExceeded() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('total_bytes')
                    ->label('累计用量')
                    ->state(fn (EndUser $record): string => Bytes::human((int) ($record->total_tx ?? 0) + (int) ($record->total_rx ?? 0))),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (EndUser $record): string => $record->statusLabel())
                    ->color(fn (EndUser $record): string => $record->statusColor()),
            ])
            ->paginated(false)
            ->headerActions([
                Tables\Actions\Action::make('all_users')
                    ->label('查看全部用户')
                    ->link()
                    ->url($provider ? EndUserResource::getUrl(tenant: $provider) : null),
            ])
            ->emptyStateHeading('还没有用户')
            ->emptyStateDescription('把注册页发给用户，或直接在「用户」里新建。')
            ->emptyStateIcon('heroicon-o-users');
    }
}
