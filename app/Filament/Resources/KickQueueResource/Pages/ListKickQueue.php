<?php

namespace App\Filament\Resources\KickQueueResource\Pages;

use App\Filament\Resources\KickQueueResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListKickQueue extends ListRecords
{
    protected static string $resource = KickQueueResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\CreateAction::make()->label('加入待踢')];
    }
}
