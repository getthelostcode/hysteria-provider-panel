<?php

namespace App\Filament\Provider\Resources\EndUserResource\Pages;

use App\Filament\Provider\Resources\EndUserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditEndUser extends EditRecord
{
    protected static string $resource = EndUserResource::class;

    protected function getHeaderActions(): array
    {
        return [Actions\DeleteAction::make()->label('删除')];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return EndUserResource::fillQuotaInput($data);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return EndUserResource::mergeQuotaInput($data);
    }
}
