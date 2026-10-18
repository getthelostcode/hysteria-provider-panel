<?php

namespace App\Filament\Resources\EndUserResource\Pages;

use App\Filament\Resources\EndUserResource;
use Filament\Resources\Pages\ListRecords;

class ListEndUsers extends ListRecords
{
    protected static string $resource = EndUserResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
