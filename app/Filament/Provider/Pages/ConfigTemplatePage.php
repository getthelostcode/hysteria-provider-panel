<?php

namespace App\Filament\Provider\Pages;

use App\Models\ProviderConfigTemplate;
use App\Support\ConfigTemplateResolver;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use App\Models\ConfigTemplate;

/**
 * 服务商后台 → 配置模板：看平台标准版、按需自定义、拿一键安装命令。
 *
 * 生效顺序：本运营商自定义模板 > 平台标准模板。
 */
class ConfigTemplatePage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'config-template';

    protected static string $view = 'filament.provider.pages.config-template';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = '配置模板';

    protected static ?string $navigationGroup = '节点';

    protected static ?string $title = 'Hysteria 配置模板';

    protected static ?int $navigationSort = 40;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $own = $this->ownTemplate();

        $this->form->fill(['content' => $own?->content ?? '']);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('自定义 config.yaml（留空 = 使用平台标准模板）')
                    ->rows(26)
                    ->helperText('保存后本运营商的节点安装时优先用这份；识别码 / trafficStats 端口与 secret / 证书路径 / 带宽仍由安装脚本覆盖。')
                    ->extraInputAttributes([
                        'style' => 'font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; line-height: 1.5;',
                        'spellcheck' => 'false',
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $provider = Filament::getTenant();
        $content = (string) ($this->form->getState()['content'] ?? '');
        $own = $this->ownTemplate();

        if (trim($content) === '') {
            $own?->delete();

            Notification::make()
                ->title('已恢复使用平台标准模板')
                ->success()
                ->send();

            return;
        }

        ProviderConfigTemplate::query()->updateOrCreate(
            ['provider_id' => $provider->getKey()],
            [
                'content' => $content,
                'version' => $own ? $own->version + 1 : 1,
                'updated_by' => (string) (auth('provider')->user()?->email ?? ''),
            ],
        );

        Notification::make()
            ->title('已保存自定义模板 v' . (($own?->version ?? 0) + 1))
            ->body('本运营商的节点下次运行 install.sh 会拉到这一版。')
            ->success()
            ->send();
    }

    public function useStandard(): void
    {
        $this->form->fill(['content' => '']);

        Notification::make()
            ->title('已清空自定义内容（尚未保存）')
            ->body('点「保存」后生效：节点将重新使用平台标准模板。')
            ->warning()
            ->send();
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $provider = Filament::getTenant();
        $resolved = ConfigTemplateResolver::forProvider($provider);
        $standard = ConfigTemplate::standard();
        $script = (string) config('hysteria.install_script_url');
        $code = $provider->code;

        return [
            'provider' => $provider,
            'effective' => $resolved,
            'hasOverride' => $resolved['source'] === 'provider',
            'standardVersion' => $standard->version,
            'standardUpdatedAt' => $standard->updated_at?->format('Y-m-d H:i:s') ?? '—',
            'endpoint' => url('/api/config-template') . '?code=' . $code,
            // 一行执行：拉脚本直接交给 bash（-s -- 把后面的参数传给 install.sh）
            'installCommand' => 'curl -fsSL ' . $script . ' | sudo bash -s -- --code ' . $code,
            'manualCommand' => 'curl -fsSL ' . $script . ' -o install.sh && sudo bash install.sh --code ' . $code,
        ];
    }

    private function ownTemplate(): ?ProviderConfigTemplate
    {
        return ProviderConfigTemplate::query()
            ->where('provider_id', Filament::getTenant()->getKey())
            ->first();
    }
}
