<?php

namespace App\Filament\Provider\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * 服务商仪表板：只放能直接指导运营的指标（节点、用户、流量、超额），
 * 依次是概览指标 → 流量趋势 → 节点用量 → 用量最高的用户。
 */
class Dashboard extends BaseDashboard
{
    protected static bool $shouldRegisterNavigation = true;

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = '仪表板';

    protected static ?string $title = '仪表板';

    protected static ?int $navigationSort = -10;

    public function getHeading(): string|Htmlable
    {
        return '仪表板';
    }

    public function getSubheading(): ?string
    {
        return '节点、用户与流量一览';
    }
}
