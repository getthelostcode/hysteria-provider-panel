<?php

namespace App\Filament\Provider\Resources;

use App\Filament\Provider\Resources\EndUserResource\Pages;
use App\Models\EndUser;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Notifications\Notification;
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

    /** 表单里的 GB → 字节，并同步重置时间 */
    public static function mergeQuotaInput(array $data): array
    {
        $gb = $data['quota_gb'] ?? null;
        $data['traffic_quota_bytes'] = ($gb === null || $gb === '') ? null : (int) round(((float) $gb) * 1024 ** 3);

        $days = $data['quota_reset_days'] ?? null;
        $data['quota_reset_days'] = ($days === null || $days === '') ? null : (int) $days;

        if ($data['quota_reset_days'] === null) {
            $data['quota_reset_at'] = null;
        } elseif (empty($data['quota_reset_at'])) {
            $data['quota_reset_at'] = now()->addDays($data['quota_reset_days']);
        }

        unset($data['quota_gb']);

        return $data;
    }

    /** 字节 → 表单里的 GB */
    public static function fillQuotaInput(array $data): array
    {
        $bytes = $data['traffic_quota_bytes'] ?? null;
        $data['quota_gb'] = $bytes === null ? null : round(((int) $bytes) / 1024 ** 3, 2);

        return $data;
    }

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
            Forms\Components\TextInput::make('quota_gb')
                ->label('流量配额（GB，留空不限）')
                ->numeric()
                ->minValue(0)
                ->step(0.1)
                ->helperText('例如 10 = 10 GB。用户本期用量超过后，鉴权会拒绝新连接。'),
            Forms\Components\TextInput::make('quota_reset_days')
                ->label('重置周期（天，留空不重置）')
                ->numeric()
                ->integer()
                ->minValue(1)
                ->maxValue(365)
                ->helperText('例如 7 = 每 7 天把本期用量清零（10 GB / 7 天）。'),
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
                Tables\Columns\TextColumn::make('quota_used_bytes')
                    ->label('本期用量')
                    ->state(fn (EndUser $record): string => $record->quotaUsageLabel())
                    ->description(fn (EndUser $record): string => $record->hasQuota() && $record->quotaExceeded() ? '已用尽' : '')
                    ->color(fn (EndUser $record): string => $record->quotaExceeded() ? 'danger' : 'gray'),
                Tables\Columns\TextColumn::make('quota_reset_at')
                    ->label('下次重置')
                    ->state(fn (EndUser $record): string => $record->quotaResetLabel())
                    ->description(fn (EndUser $record): ?string => (int) ($record->quota_reset_days ?? 0) > 0 ? '每 ' . $record->quota_reset_days . ' 天' : null)
                    ->placeholder('不重置'),
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
                Tables\Actions\Action::make('reset_uuid')
                    ->label('重置 UUID')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('重置该用户的 UUID')
                    ->modalDescription('UUID 就是客户端的账号与密码。重置后旧二维码/链接立即失效；历史流量与在线快照会迁移到新 UUID。')
                    ->modalSubmitActionLabel('确认重置')
                    ->action(function (EndUser $record): void {
                        $old = $record->uuid;
                        $new = $record->resetUuid();

                        Notification::make()
                            ->title('UUID 已重置')
                            ->body("旧 {$old}\n新 {$new}\n让用户到用户中心重新生成二维码即可。")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\Action::make('reset_quota')
                    ->label('清零本期用量')
                    ->icon('heroicon-o-arrow-path-rounded-square')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->modalDescription('把该用户本期已用流量清零；如果设了重置周期，下一周期从现在重新算。')
                    ->action(function (EndUser $record): void {
                        $record->forceFill([
                            'quota_used_bytes' => 0,
                            'quota_reset_at' => (int) ($record->quota_reset_days ?? 0) > 0
                                ? now()->addDays((int) $record->quota_reset_days)
                                : null,
                        ])->save();

                        Notification::make()->title('本期用量已清零')->success()->send();
                    }),
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
