<?php

namespace App\Filament\Resources\UserTrafficResource\Pages;

use App\Filament\Resources\UserTrafficResource;
use Filament\Resources\Pages\ListRecords;

class ListUserTraffic extends ListRecords
{
    protected static string $resource = UserTrafficResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
