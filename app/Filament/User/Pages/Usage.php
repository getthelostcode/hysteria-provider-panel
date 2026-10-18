<?php

namespace App\Filament\User\Pages;

use App\Models\Node;
use App\Models\NodeUserTraffic;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * 使用记录：按「日期 × 服务器」列出自己的流量（node_user_traffic，采集器回传）。
 */
class Usage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'usage';

    protected static string $view = 'filament.user.pages.usage';

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = '使用记录';

    protected static ?int $navigationSort = 10;

    public function getTitle(): string|Htmlable
    {
        return '使用记录';
    }

    public function getSubheading(): ?string
    {
        return '按天、按服务器统计你的流量（约 30 秒更新一次）';
    }

    public function table(Table $table): Table
    {
        $uuid = Auth::guard('end_user')->user()->uuid;

        return $table
            ->query(
                NodeUserTraffic::query()
                    ->where('user_id', $uuid)
                    ->with('node'),
            )
            ->columns([
                Tables\Columns\TextColumn::make('day')->label('日期')->sortable()->weight('bold'),
                Tables\Columns\TextColumn::make('node.name')->label('服务器')->placeholder('—'),
                Tables\Columns\TextColumn::make('tx_bytes')
                    ->label('上行')
                    ->formatStateUsing(fn (int $state): string => NodeUserTraffic::human($state))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('rx_bytes')
                    ->label('下行')
                    ->formatStateUsing(fn (int $state): string => NodeUserTraffic::human($state))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('total')
                    ->label('合计')
                    ->state(fn (NodeUserTraffic $record): string => NodeUserTraffic::human($record->totalBytes()))
                    ->alignEnd()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('updated_at')->label('更新于')->since()->toggleable(),
            ])
            ->defaultSort('day', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('node_id')
                    ->label('服务器')
                    ->options(fn (): array => Node::query()
                        ->where('provider_id', Auth::guard('end_user')->user()->provider_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                Tables\Filters\Filter::make('period')
                    ->label('时间范围')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('从'),
                        \Filament\Forms\Components\DatePicker::make('to')->label('到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $d): Builder => $q->where('day', '>=', $d))
                            ->when($data['to'] ?? null, fn (Builder $q, string $d): Builder => $q->where('day', '<=', $d));
                    }),
            ])
            ->emptyStateHeading('还没有用量记录')
            ->emptyStateDescription('连上服务器产生流量后，这里会按天出现记录。');
    }
}
