<?php

namespace App\Filament\Pages;

use App\Models\ConfigTemplate;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * 平台后台：Hysteria config.yaml 标准模板（可编辑保存）。
 *
 * 保存时 version 自增；节点安装脚本 GET /api/config-template 拉最新内容渲染。
 */
class ConfigTemplateSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'config-template';

    protected static string $view = 'filament.pages.config-template';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Hysteria 配置模板';

    protected static ?string $navigationGroup = '系统';

    protected static ?string $title = 'Hysteria 配置模板';

    protected static ?int $navigationSort = 90;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $template = ConfigTemplate::standard();

        $this->form->fill(['content' => $template->content]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('content')
                    ->label('config.yaml（标准版）')
                    ->required()
                    ->rows(30)
                    ->helperText('节点安装时按运营商识别码 / trafficStats 端口与 secret 渲染：obfs 密码、trafficStats、证书路径都会被覆盖，其余字段原样生效。')
                    ->extraInputAttributes([
                        'style' => 'font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 12.5px; line-height: 1.5;',
                        'spellcheck' => 'false',
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $template = ConfigTemplate::standard();
        $template->content = $data['content'];
        $template->version = $template->version + 1;
        $template->updated_by = (string) (auth('admin')->user()?->email ?? '');
        $template->save();

        Notification::make()
            ->title('已保存为标准模板 v' . $template->version)
            ->body('节点下次运行 install.sh 会拉到这一版（当前版本号 ' . $template->version . '）。')
            ->success()
            ->send();
    }

    public function resetToDefault(): void
    {
        $this->form->fill(['content' => ConfigTemplate::defaultContent()]);

        Notification::make()
            ->title('已恢复默认内容（尚未保存）')
            ->body('确认无误后点「保存」才会写入并递增版本号。')
            ->warning()
            ->send();
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $template = ConfigTemplate::standard();

        return [
            'version' => $template->version,
            'updatedBy' => $template->updated_by ?: '—',
            'updatedAt' => $template->updated_at?->format('Y-m-d H:i:s') ?? '—',
            'sha256' => $template->sha256(),
            'endpoint' => url('/api/config-template'),
        ];
    }
}
