<?php

namespace App\Filament\Resources\ProviderResource\RelationManagers;

use App\Models\ProviderUser;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

/**
 * 平台后台 → 服务商 → 登录账号：改邮箱、改名、重置密码。
 *
 * 服务商后台自己也能在「账号资料」改，这里是平台侧兜底（忘密码、换负责人）。
 */
class ProviderUsersRelationManager extends RelationManager
{
    protected static string $relationship = 'providerUsers';

    protected static ?string $title = '登录账号';

    protected static ?string $modelLabel = '登录账号';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('姓名（显示名）')
                ->required()
                ->maxLength(60),
            Forms\Components\TextInput::make('email')
                ->label('登录邮箱')
                ->email()
                ->required()
                ->maxLength(190)
                ->unique(ignoreRecord: true)
                ->validationMessages(['unique' => '这个邮箱已经被其他账号使用']),
            Forms\Components\TextInput::make('password')
                ->label('登录密码')
                ->password()
                ->revealable()
                ->minLength(8)
                ->maxLength(190)
                ->confirmed()
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->required(fn (string $operation): bool => $operation === 'create')
                ->helperText(fn (string $operation): string => $operation === 'create'
                    ? '至少 8 位；服务商用这个邮箱 + 密码登录服务商后台。'
                    : '留空表示不修改密码。'),
            Forms\Components\TextInput::make('password_confirmation')
                ->label('再输一次密码')
                ->password()
                ->revealable()
                ->dehydrated(false),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('email')
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->label('登录邮箱')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('name')
                    ->label('姓名')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('最近登录')
                    ->since()
                    ->placeholder('从未'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('新增登录账号')
                    ->modalHeading('新增服务商登录账号'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label('编辑')
                    ->modalHeading('修改登录账号'),
                Tables\Actions\Action::make('reset_password')
                    ->label('重置密码')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->modalHeading('重置服务商登录密码')
                    ->modalDescription('不需要旧密码；重置后把新密码发给服务商。')
                    ->form([
                        Forms\Components\TextInput::make('password')
                            ->label('新密码')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8)
                            ->maxLength(190)
                            ->confirmed(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('再输一次新密码')
                            ->password()
                            ->revealable()
                            ->required(),
                    ])
                    ->modalSubmitActionLabel('重置密码')
                    ->action(function (ProviderUser $record, array $data): void {
                        $record->forceFill(['password' => Hash::make((string) $data['password'])])->save();

                        Notification::make()
                            ->title('密码已重置')
                            ->body("账号：{$record->email}\n新密码已生效，请把刚才设置的密码发给服务商。")
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                Tables\Actions\DeleteAction::make()
                    ->label('删除')
                    ->visible(fn (ProviderUser $record): bool => $this->canDeleteAccount($record))
                    ->modalDescription('删除后这个账号不能登录；服务商至少要保留一个登录账号。'),
            ])
            ->emptyStateHeading('还没有登录账号')
            ->emptyStateDescription('新增一个，服务商才能登录自己的后台。');
    }

    /** 至少保留一个账号，避免服务商被锁在门外 */
    private function canDeleteAccount(ProviderUser $record): bool
    {
        return ProviderUser::query()->where('provider_id', $record->provider_id)->count() > 1;
    }

    public function isReadOnly(): bool
    {
        return false;
    }
}
