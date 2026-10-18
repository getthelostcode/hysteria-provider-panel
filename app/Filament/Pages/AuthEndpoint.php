<?php

namespace App\Filament\Pages;

use App\Http\Controllers\HysteriaAuthController;
use App\Models\EndUser;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Request;

/**
 * 平台后台 → 鉴权接口（/auth）：看地址、看样例、直接拿账号试一次。
 *
 * 节点侧配置：
 *   auth:
 *     type: http
 *     http:
 *       url: https://<面板域名>/auth
 */
class AuthEndpoint extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'auth-endpoint';

    protected static string $view = 'filament.pages.auth-endpoint';

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = '鉴权接口';

    protected static ?string $navigationGroup = '系统';

    protected static ?string $title = 'Hysteria HTTP 鉴权';

    protected static ?int $navigationSort = 91;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** 最近一次自测结果 */
    public ?array $result = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('login')
                    ->label('账号（用户 uuid 或邮箱）')
                    ->placeholder('wrx4vuqrqdayw6iocdporh6sb89nwcul')
                    ->maxLength(190),
                Forms\Components\TextInput::make('password')
                    ->label('密码（留空 = uuid 直连模式）')
                    ->password()
                    ->revealable()
                    ->maxLength(190),
            ])
            ->columns(2)
            ->statePath('data');
    }

    public function test(): void
    {
        $data = $this->form->getState();
        $login = trim((string) ($data['login'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($login === '') {
            // 空 auth 自测：能返回 ok:false 就说明路由活着
            $this->result = $this->callAuth('');

            Notification::make()
                ->title('接口可达')
                ->body('/auth 返回：' . json_encode($this->result, JSON_UNESCAPED_UNICODE))
                ->success()
                ->send();

            return;
        }

        $auth = $password === '' ? $login : $login . ':' . $password;
        $this->result = $this->callAuth(base64_encode($auth));

        $ok = (bool) ($this->result['ok'] ?? false);

        Notification::make()
            ->title($ok ? '鉴权成功' : '鉴权失败')
            ->body(($ok ? 'id=' . ($this->result['id'] ?? '') : (string) ($this->result['msg'] ?? '')))
            ->{$ok ? 'success' : 'danger'}()
            ->send();
    }

    /** @return array<string, mixed> */
    private function callAuth(string $auth): array
    {
        $request = Request::create(
            '/auth',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            json_encode(['addr' => '127.0.0.1:0', 'auth' => $auth, 'tx' => 0, 'rx' => 0]),
        );

        $response = app(HysteriaAuthController::class)($request);

        return json_decode((string) $response->getContent(), true) ?: ['raw' => (string) $response->getContent()];
    }

    public function getTitle(): string|Htmlable
    {
        return 'Hysteria HTTP 鉴权';
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        return [
            'endpoint' => url('/auth'),
            'users' => EndUser::query()->count(),
            'activeUsers' => EndUser::query()->where('status', EndUser::STATUS_ACTIVE)->count(),
            'sampleRequest' => '{"addr":"1.2.3.4:5678","auth":"<base64(uuid)>","tx":0,"rx":0}',
            'sampleCurl' => 'curl -s -X POST ' . url('/auth') . ' -H \'Content-Type: application/json\' -d \'{"addr":"1.2.3.4:1","auth":"\'$(printf %s "你的uuid" | base64 -w0)\'"}\'',
            'nodeConfig' => implode("\n", [
                'auth:',
                '  type: http',
                '  http:',
                '    url: \'' . url('/auth') . '\'',
                '    insecure: false',
            ]),
        ];
    }
}
