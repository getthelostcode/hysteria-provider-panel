<?php

namespace App\Filament\Provider\Resources\EndUserResource\Pages;

use App\Filament\Provider\Resources\EndUserResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateEndUser extends CreateRecord
{
    protected static string $resource = EndUserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // 强制归属当前运营商，前端改不了
        $data['provider_id'] = Filament::getTenant()?->getKey();

        return EndUserResource::mergeQuotaInput($data);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '用户已创建，可直接用「邮箱 + 密码」连接';
    }
}
