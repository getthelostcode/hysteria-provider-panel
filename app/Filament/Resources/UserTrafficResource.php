<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserTrafficResource\Pages;
use App\Models\Node;
use App\Models\NodeUserTraffic;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 用户流量（只读）：采集器回传的「节点 + 用户 + 按天」累计。
 */
class UserTrafficResource extends Resource
{
    protected static ?string $model = NodeUserTraffic::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static ?string $navigationLabel = '用户流量';

    protected static ?string $modelLabel = '用户流量';

    protected static ?string $pluralModelLabel = '用户流量';

    protected static ?string $navigationGroup = '流量与在线';

    protected static ?string $slug = 'user-traffic';

    protected static ?int $navigationSort = 10;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('node.name')->label('节点')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('用户')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('day')->label('日期')->sortable(),
                Tables\Columns\TextColumn::make('tx_bytes')
                    ->label('上行')
                    ->formatStateUsing(fn (int $state): string => NodeUserTraffic::human($state)),
                Tables\Columns\TextColumn::make('rx_bytes')
                    ->label('下行')
                    ->formatStateUsing(fn (int $state): string => NodeUserTraffic::human($state)),
                Tables\Columns\TextColumn::make('total')
                    ->label('合计')
                    ->state(fn (NodeUserTraffic $record): string => NodeUserTraffic::human($record->totalBytes())),
                Tables\Columns\TextColumn::make('updated_at')->label('更新时间')->dateTime('Y-m-d H:i:s')->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('node_id')
                    ->label('节点')
                    ->options(fn (): array => Node::query()->orderBy('id')->pluck('name', 'id')->all()),
                Tables\Filters\Filter::make('day')
                    ->label('按日期')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('day')->label('日期'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['day'] ?? null, fn (Builder $q, string $day): Builder => $q->where('day', $day))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUserTraffic::route('/'),
        ];
    }
}
