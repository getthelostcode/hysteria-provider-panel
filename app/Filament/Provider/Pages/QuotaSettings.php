<?php

namespace App\Filament\Provider\Pages;

use App\Models\Provider;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

/**
 * 服务商后台 → 套餐默认值：新注册用户套用的流量配额与重置周期。
 *
 * 例：10 GB / 7 天 —— 用户注册后每 7 天把本期用量清零，超过 10 GB 鉴权就拒绝。
 * 已有用户可在「用户」列表里单独改。
 */
class QuotaSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'quota';

    protected static string $view = 'filament.provider.pages.quota-settings';

    protected static ?string $navigationIcon = 'heroicon-o-gift';

    protected static ?string $navigationLabel = '套餐默认值';

    protected static ?string $navigationGroup = '配置';

    protected static ?string $title = '套餐默认值';

    protected static ?int $navigationSort = 30;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $provider = $this->provider();

        $this->form->fill([
            'default_quota_gb' => $provider->default_quota_bytes === null
                ? null
                : round($provider->default_quota_bytes / 1024 ** 3, 2),
            'default_reset_days' => $provider->default_quota_reset_days,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('新用户默认套餐')
                    ->description('只影响之后通过注册页或后台新建的用户；已有用户请在「用户」里单独改。')
                    ->schema([
                        Forms\Components\TextInput::make('default_quota_gb')
                            ->label('流量配额（GB，留空不限）')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.1)
                            ->helperText('填 10 就是每个周期 10 GB。'),
                        Forms\Components\TextInput::make('default_reset_days')
                            ->label('重置周期（天，留空不重置）')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->helperText('填 7 就是每 7 天清零一次；留空表示一次性额度，用完不恢复。'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        if (! Schema::hasColumn('providers', 'default_quota_bytes')) {
            Notification::make()
                ->title('数据库还没迁移')
                ->body('先执行：php artisan migrate --force（缺 providers.default_quota_bytes 等字段）')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $data = $this->form->getState();

        $gb = $data['default_quota_gb'] ?? null;
        $days = $data['default_reset_days'] ?? null;

        try {
            $this->provider()->update([
                'default_quota_bytes' => ($gb === null || $gb === '') ? null : (int) round(((float) $gb) * 1024 ** 3),
                'default_quota_reset_days' => ($days === null || $days === '') ? null : (int) $days,
            ]);
        } catch (QueryException $e) {
            Notification::make()
                ->title('保存失败：数据库缺字段')
                ->body('在面板目录执行 php artisan migrate --force 后重试。' . $e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()
            ->title('已保存')
            ->body($gb === null || $gb === ''
                ? '新用户不限流量。'
                : '新用户默认 ' . $gb . ' GB' . ($days ? " / {$days} 天重置" : '（不重置）') . '。')
            ->success()
            ->send();
    }

    private function provider(): Provider
    {
        return Filament::getTenant();
    }
}
