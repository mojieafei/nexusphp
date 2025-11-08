<?php

namespace App\Filament\Resources\StardustStatsResource\Pages;

use App\Filament\Resources\StardustStatsResource;
use App\Models\StardustFarm;
use App\Models\StardustLand;
use App\Models\StardustInventory;
use App\Models\StardustInteraction;
use Filament\Resources\Pages\Page;
use Filament\Support\Enums\IconPosition;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class StardustStatsPage extends Page
{
    protected static string $resource = StardustStatsResource::class;

    protected static string $view = 'filament.resources.stardust-stats-resource.pages.stardust-stats-page';

    protected static ?string $title = '星尘农场 - 游戏统计';

    public function getStats(): array
    {
        $totalFarms = StardustFarm::count();
        $totalStardust = StardustFarm::sum('stardust');
        $avgLevel = round(StardustFarm::avg('level'), 2);
        
        $totalLands = StardustLand::count();
        $growingLands = StardustLand::where('status', 'growing')->count();
        $matureLands = StardustLand::where('status', 'mature')->count();
        
        $totalFragments = StardustInventory::where('item_type', 'fragment')->sum('quantity');
        $totalPlanets = StardustInventory::where('item_type', 'planet')->sum('quantity');
        
        $todayInteractions = StardustInteraction::whereDate('created_at', today())->count();
        $todayWater = StardustInteraction::whereDate('created_at', today())->where('action', 'water')->count();
        $todaySteal = StardustInteraction::whereDate('created_at', today())->where('action', 'steal')->count();
        
        return [
            [
                'label' => '总农场数',
                'value' => number_format($totalFarms),
                'icon' => 'heroicon-o-home',
                'color' => 'success',
            ],
            [
                'label' => '总星尘数',
                'value' => number_format($totalStardust),
                'icon' => 'heroicon-o-star',
                'color' => 'warning',
            ],
            [
                'label' => '平均等级',
                'value' => $avgLevel,
                'icon' => 'heroicon-o-arrow-trending-up',
                'color' => 'info',
            ],
            [
                'label' => '生长中土地',
                'value' => number_format($growingLands) . ' / ' . number_format($totalLands),
                'icon' => 'heroicon-o-map',
                'color' => 'primary',
            ],
            [
                'label' => '已成熟土地',
                'value' => number_format($matureLands),
                'icon' => 'heroicon-o-check-circle',
                'color' => 'success',
            ],
            [
                'label' => '总碎片数',
                'value' => number_format($totalFragments),
                'icon' => 'heroicon-o-cube',
                'color' => 'info',
            ],
            [
                'label' => '完整行星数',
                'value' => number_format($totalPlanets),
                'icon' => 'heroicon-o-globe-asia-australia',
                'color' => 'danger',
            ],
            [
                'label' => '今日互动',
                'value' => number_format($todayInteractions),
                'icon' => 'heroicon-o-user-group',
                'color' => 'success',
            ],
            [
                'label' => '今日浇水',
                'value' => number_format($todayWater),
                'icon' => 'heroicon-o-beaker',
                'color' => 'info',
            ],
            [
                'label' => '今日偷取',
                'value' => number_format($todaySteal),
                'icon' => 'heroicon-o-shield-exclamation',
                'color' => 'warning',
            ],
        ];
    }

    public function getTopFarms(): array
    {
        return StardustFarm::with('user')
            ->orderBy('stardust', 'desc')
            ->limit(10)
            ->get()
            ->map(fn ($farm) => [
                'username' => $farm->user->username,
                'stardust' => number_format($farm->stardust),
                'level' => $farm->level,
            ])
            ->toArray();
    }

    public function getRecentActivities(): array
    {
        return StardustInteraction::with(['fromUser', 'toUser'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(fn ($interaction) => [
                'from' => $interaction->fromUser->username ?? '未知',
                'to' => $interaction->toUser->username ?? '未知',
                'action' => match($interaction->action) {
                    'water' => '浇水',
                    'steal' => '偷取',
                    'visit' => '访问',
                    default => $interaction->action,
                },
                'result' => $interaction->result,
                'time' => $interaction->created_at->diffForHumans(),
            ])
            ->toArray();
    }
}

