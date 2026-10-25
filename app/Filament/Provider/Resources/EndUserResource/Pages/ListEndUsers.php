<?php

namespace App\Filament\Provider\Resources\EndUserResource\Pages;

use App\Filament\Provider\Pages\QuotaSettings;
use App\Filament\Provider\Resources\EndUserResource;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListEndUsers extends ListRecords
{
    protected static string $resource = EndUserResource::class;

    protected function getHeaderActions(): array
    {
        $tenant = Filament::getTenant();

        return [
            Actions\Action::make('join_link')
                ->label('注册链接')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->modalHeading('本运营商的用户注册页')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('关闭')
                ->modalContent(fn () => view('filament.provider.join-link', [
                    'url' => $tenant ? $tenant->joinUrl() : '—',
                ])),
            Actions\Action::make('quota_settings')
                ->label('套餐默认值')
                ->icon('heroicon-o-gift')
                ->color('gray')
                ->tooltip('新用户的默认流量配额与重置周期')
                ->url($tenant ? QuotaSettings::getUrl(tenant: $tenant) : null),
            Actions\CreateAction::make()->label('新建用户'),
        ];
    }
}
