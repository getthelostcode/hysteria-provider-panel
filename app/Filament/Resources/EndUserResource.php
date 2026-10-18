<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EndUserResource\Pages;
use App\Models\EndUser;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * 平台后台 → 全部终端用户（跨运营商）。服务商后台只看自己那份。
 */
class EndUserResource extends Resource
{
    protected static ?string $model = EndUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = '终端用户';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '终端用户';

    protected static ?string $navigationGroup = '流量与在线';

    protected static ?string $slug = 'end-users';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('provider_id')
                ->label('运营商')
                ->relationship('provider', 'name')
                ->searchable()
                ->required(),
            Forms\Components\TextInput::make('email')->label('邮箱')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('昵称')->maxLength(60),
            Forms\Components\TextInput::make('password')
                ->label('重置密码')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(72)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('留空不改'),
            Forms\Components\Select::make('status')
                ->label('状态')
                ->options(['active' => '正常', 'disabled' => '已停用'])
                ->required(),
            Forms\Components\DateTimePicker::make('expires_at')->label('到期时间')->seconds(false),
            Forms\Components\TextInput::make('traffic_quota_bytes')->label('流量配额（字节）')->numeric(),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider.name')->label('运营商')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('email')->label('邮箱')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->formatStateUsing(fn (EndUser $record): string => $record->statusLabel())
                    ->color(fn (EndUser $record): string => $record->statusColor()),
                Tables\Columns\TextColumn::make('uuid')->label('用户 ID')->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('last_auth_at')->label('最近登录')->since()->placeholder('从未'),
                Tables\Columns\TextColumn::make('created_at')->label('注册时间')->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('provider_id')
                    ->label('运营商')
                    ->options(fn (): array => Provider::query()->orderBy('name')->pluck('name', 'id')->all()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(['active' => '正常', 'disabled' => '已停用']),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle')
                    ->label(fn (EndUser $record): string => $record->status === 'active' ? '停用' : '启用')
                    ->icon('heroicon-o-power')
                    ->color(fn (EndUser $record): string => $record->status === 'active' ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (EndUser $record) => $record->update([
                        'status' => $record->status === 'active' ? 'disabled' : 'active',
                    ])),
                Tables\Actions\EditAction::make()->label('编辑'),
                Tables\Actions\DeleteAction::make()->label('删除'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEndUsers::route('/'),
            'edit' => Pages\EditEndUser::route('/{record}/edit'),
        ];
    }
}
