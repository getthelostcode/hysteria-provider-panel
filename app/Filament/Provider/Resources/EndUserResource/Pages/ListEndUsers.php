<?php

namespace App\Filament\Provider\Resources\EndUserResource\Pages;

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
            Actions\CreateAction::make()->label('新建用户'),
        ];
    }
}
