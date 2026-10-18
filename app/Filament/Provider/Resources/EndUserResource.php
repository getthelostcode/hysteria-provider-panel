<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\EndUserResource\Pages;
use App\Models\EndUser;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * 服务商后台 → 用户：只显示、只能创建本运营商（当前租户）的终端用户。
 * 用户也可以通过 /join/{code} 自助注册。
 */
class EndUserResource extends Resource
{
    protected static ?string $model = EndUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = '用户';

    protected static ?string $modelLabel = '用户';

    protected static ?string $pluralModelLabel = '用户';

    protected static ?string $slug = 'users';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('email')
                ->label('邮箱（登录账号）')
                ->email()
                ->required()
                ->maxLength(190)
                ->unique(ignoreRecord: true),
            Forms\Components\TextInput::make('name')->label('昵称')->maxLength(60),
            Forms\Components\TextInput::make('password')
                ->label('密码')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(72)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->helperText('留空表示不修改'),
            Forms\Components\Select::make('status')
                ->label('状态')
                ->options(['active' => '正常', 'disabled' => '已停用'])
                ->default('active')
                ->required(),
            Forms\Components\DateTimePicker::make('expires_at')->label('到期时间')->seconds(false),
            Forms\Components\TextInput::make('traffic_quota_bytes')
                ->label('流量配额（字节，留空不限）')
                ->numeric()
                ->minValue(0),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')->label('邮箱')->searchable()->copyable()->weight('bold'),
                Tables\Columns\TextColumn::make('name')->label('昵称')->placeholder('—'),
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
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('还没有用户')
            ->emptyStateDescription('把注册链接发给客户，或点右上角「新建用户」手工添加。');
    }

    /** 租户隔离：只看当前运营商的数据 */
    public static function getEloquentQuery(): Builder
    {
        $tenant = Filament::getTenant();

        return parent::getEloquentQuery()
            ->when($tenant, fn (Builder $query): Builder => $query->where('provider_id', $tenant->getKey()));
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListEndUsers::route('/'),
            'create' => Pages\CreateEndUser::route('/create'),
            'edit' => Pages\EditEndUser::route('/{record}/edit'),
        ];
    }
}
