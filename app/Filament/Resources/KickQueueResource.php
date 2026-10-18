<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KickQueueResource\Pages;
use App\Models\NodeKickQueue;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * 待踢队列（平台后台）：加一条 uuid，采集器下一轮发现该用户在线就在对应节点踢掉。
 */
class KickQueueResource extends Resource
{
    protected static ?string $model = NodeKickQueue::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-minus';

    protected static ?string $navigationLabel = '待踢队列';

    protected static ?string $modelLabel = '待踢记录';

    protected static ?string $pluralModelLabel = '待踢队列';

    protected static ?string $navigationGroup = '流量与在线';

    protected static ?string $slug = 'kick-queue';

    protected static ?int $navigationSort = 20;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('uuid')
                ->label('用户 UUID / ID')
                ->required()
                ->maxLength(190)
                ->helperText('与节点 /online 里的用户标识一致；在线时下一轮采集就会被踢'),
            Forms\Components\TextInput::make('reason')
                ->label('原因')
                ->maxLength(190)
                ->placeholder('quota / 违规 / 手工踢出'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uuid')->label('UUID')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('reason')->label('原因')->placeholder('—'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (NodeKickQueue $record): string => $record->statusLabel())
                    ->color(fn (NodeKickQueue $record): string => $record->statusColor()),
                Tables\Columns\TextColumn::make('node_id')->label('节点')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('http_status')->label('节点返回')->placeholder('—')->toggleable(),
                Tables\Columns\TextColumn::make('requested_by')->label('发起人')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')->label('入队时间')->dateTime('Y-m-d H:i:s')->sortable(),
                Tables\Columns\TextColumn::make('processed_at')->label('处理时间')->dateTime('Y-m-d H:i:s')->placeholder('—'),
                Tables\Columns\TextColumn::make('note')->label('备注')->limit(40)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(['pending' => '待踢', 'kicked' => '已踢出', 'failed' => '失败']),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()->label('删除'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKickQueue::route('/'),
            'create' => Pages\CreateKickQueue::route('/create'),
        ];
    }
}
