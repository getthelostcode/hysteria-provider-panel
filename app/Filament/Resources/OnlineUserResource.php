<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OnlineUserResource\Pages;
use App\Models\Node;
use App\Models\NodeUserOnline;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 在线用户（只读）：最近一轮采集的快照，采集器每轮整体替换。
 */
class OnlineUserResource extends Resource
{
    protected static ?string $model = NodeUserOnline::class;

    protected static ?string $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = '在线用户';

    protected static ?string $modelLabel = '在线用户';

    protected static ?string $pluralModelLabel = '在线用户';

    protected static ?string $navigationGroup = '流量与在线';

    protected static ?string $slug = 'online-users';

    protected static ?int $navigationSort = 15;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('node.name')->label('节点')->sortable(),
                Tables\Columns\TextColumn::make('user_id')->label('用户')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('connections')->label('连接数')->badge()->color('info'),
                Tables\Columns\TextColumn::make('seen_at')->label('采集时间')->since()->sortable(),
            ])
            ->defaultSort('seen_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('node_id')
                    ->label('节点')
                    ->options(fn (): array => Node::query()->orderBy('id')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $id): Builder => $q->where('node_id', $id))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOnlineUsers::route('/'),
        ];
    }
}
