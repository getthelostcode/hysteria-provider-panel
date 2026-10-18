<?php

namespace App\Filament\Join\Pages;

use App\Models\EndUser;
use App\Models\Provider;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;

/**
 * 运营商专属注册表单：/join/{code}/register
 *
 * URL 里的 {code} 由面板路径前缀提供（JoinPanelProvider::path('join/{code}')）。
 */
class Register extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'register';

    protected static string $view = 'filament.join.pages.register';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = '开通账号';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public Provider $provider;

    public function mount(string $code): void
    {
        $this->provider = Provider::query()->where('code', $code)->first() ?? abort(404);
        abort_unless($this->provider->isActive(), 404);

        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('昵称（可选）')
                    ->maxLength(60),
                Forms\Components\TextInput::make('email')
                    ->label('邮箱（登录账号）')
                    ->email()
                    ->required()
                    ->maxLength(190)
                    ->unique(table: EndUser::class, column: 'email'),
                Forms\Components\TextInput::make('password')
                    ->label('密码（至少 8 位）')
                    ->password()
                    ->revealable()
                    ->required()
                    ->minLength(8)
                    ->maxLength(72)
                    ->confirmed(),
                Forms\Components\TextInput::make('password_confirmation')
                    ->label('再输一次密码')
                    ->password()
                    ->required()
                    ->dehydrated(false),
            ])
            ->statePath('data');
    }

    public function create(): void
    {
        $data = $this->form->getState();

        $user = EndUser::query()->create([
            'provider_id' => $this->provider->id,
            'email' => mb_strtolower($data['email']),
            'name' => $data['name'] ?? null,
            'password' => $data['password'],
            'status' => EndUser::STATUS_ACTIVE,
            'register_ip' => request()->ip(),
        ]);

        session()->put('join_user_id', $user->id);

        $this->redirect(route('filament.join.pages.welcome', ['code' => $this->provider->code]));
    }

    public function getHeading(): string
    {
        return '开通账号';
    }

    public function getSubheading(): ?string
    {
        return '注册一个账号，用于连接本服务商的 Hysteria 服务';
    }
}
