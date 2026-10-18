<?php

namespace App\Filament\Resources\OnlineUserResource\Pages;

use App\Filament\Resources\OnlineUserResource;
use Filament\Resources\Pages\ListRecords;

class ListOnlineUsers extends ListRecords
{
    protected static string $resource = OnlineUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
