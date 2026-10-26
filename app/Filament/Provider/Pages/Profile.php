<?php

namespace App\Filament\Provider\Pages;

use App\Models\Provider;
use App\Models\ProviderUser;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * 服务商后台 → 账号资料：改运营商名称/联系方式、登录邮箱、重置密码。
 *
 * 名称全局唯一（providers.name 有唯一索引），邮箱在 provider_users 里唯一。
 */
class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'profile';

    protected static string $view = 'filament.provider.pages.profile';

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = '账号资料';

    protected static ?string $navigationGroup = '配置';

    protected static ?string $title = '账号资料';

    protected static ?int $navigationSort = 20;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $provider = $this->provider();

        $this->form->fill([
            'name' => $provider->name,
            'contact' => $provider->contact,
            'email' => $this->account()->email,
            'account_name' => $this->account()->name,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('服务商资料')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('运营商名称')
                            ->required()
                            ->minLength(2)
                            ->maxLength(120)
                            ->rule(fn () => Rule::unique('providers', 'name')->ignore($this->provider()->id))
                            ->validationMessages(['unique' => '这个名称已被其他服务商使用，换一个'])
                            ->helperText('所有服务商名称不能重复，用户注册页和后台都会显示这个名称。'),
                        Forms\Components\TextInput::make('contact')
                            ->label('联系方式（选填）')
                            ->maxLength(190)
                            ->placeholder('TG / 微信 / 电话'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('登录账号')
                    ->schema([
                        Forms\Components\TextInput::make('account_name')
                            ->label('姓名（显示名）')
                            ->required()
                            ->maxLength(60)
                            ->helperText('显示在后台右上角；填你自己的名字或岗位即可。'),
                        Forms\Components\TextInput::make('email')
                            ->label('登录邮箱')
                            ->email()
                            ->required()
                            ->maxLength(190)
                            ->rule(fn () => Rule::unique('provider_users', 'email')->ignore($this->account()->id))
                            ->validationMessages(['unique' => '这个邮箱已经被其他账号使用']),
                    ]),

                Forms\Components\Section::make('重置密码')
                    ->description('不改密码就留空；改密码需要先填当前密码。')
                    ->schema([
                        Forms\Components\TextInput::make('current_password')
                            ->label('当前密码')
                            ->password()
                            ->revealable()
                            ->required(fn (Forms\Get $get): bool => filled($get('password')))
                            ->maxLength(190),
                        Forms\Components\TextInput::make('password')
                            ->label('新密码')
                            ->password()
                            ->revealable()
                            ->minLength(8)
                            ->maxLength(190)
                            ->confirmed(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('再输一次新密码')
                            ->password()
                            ->revealable(),
                    ])
                    ->columns(3),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $provider = $this->provider();
        $account = $this->account();

        $newPassword = (string) ($data['password'] ?? '');

        if ($newPassword !== '' && ! Hash::check((string) ($data['current_password'] ?? ''), $account->password)) {
            throw ValidationException::withMessages([
                'data.current_password' => '当前密码不正确',
            ]);
        }

        $provider->update([
            'name' => trim((string) $data['name']),
            'contact' => $data['contact'] !== '' ? $data['contact'] : null,
        ]);

        $account->name = trim((string) $data['account_name']);
        $account->email = mb_strtolower((string) $data['email']);
        if ($newPassword !== '') {
            $account->password = $newPassword;
        }
        $account->save();

        // 清掉密码输入框，避免明文留在页面上
        $this->form->fill([
            'name' => $provider->fresh()->name,
            'contact' => $provider->fresh()->contact,
            'email' => $account->fresh()->email,
            'account_name' => $account->fresh()->name,
        ]);

        Notification::make()
            ->title('已保存')
            ->body($newPassword !== '' ? '资料与密码都已更新，下次登录用新密码。' : '资料已更新。')
            ->success()
            ->send();
    }

    public function getSubheading(): ?string
    {
        return '识别码 ' . $this->provider()->code . ' · 注册页 ' . $this->provider()->joinUrl();
    }

    private function provider(): Provider
    {
        return Filament::getTenant();
    }

    private function account(): ProviderUser
    {
        /** @var ProviderUser $user */
        $user = Filament::auth()->user();

        return $user;
    }
}
