<?php

namespace App\Filament\Resources\KickQueueResource\Pages;

use App\Filament\Resources\KickQueueResource;
use App\Models\NodeKickQueue;
use Filament\Resources\Pages\CreateRecord;

class CreateKickQueue extends CreateRecord
{
    protected static string $resource = KickQueueResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = NodeKickQueue::STATUS_PENDING;
        $data['requested_by'] = auth('admin')->user()?->email;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '已加入待踢队列：下一次采集（≤30s）发现该用户在线就会踢';
    }
}
