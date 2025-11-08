<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StardustStatsResource\Pages;
use App\Models\StardustFarm;
use Filament\Resources\Resource;

class StardustStatsResource extends Resource
{
    protected static ?string $model = StardustFarm::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = '游戏统计';

    protected static ?string $modelLabel = '统计';

    protected static ?string $pluralModelLabel = '游戏统计';

    protected static ?string $navigationGroup = '游戏管理';

    protected static ?int $navigationSort = 3;

    public static function getPages(): array
    {
        return [
            'index' => Pages\StardustStatsPage::route('/'),
        ];
    }
}

