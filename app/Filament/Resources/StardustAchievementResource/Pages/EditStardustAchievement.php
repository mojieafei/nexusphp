<?php

namespace App\Filament\Resources\StardustAchievementResource\Pages;

use App\Filament\Resources\StardustAchievementResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStardustAchievement extends EditRecord
{
    protected static string $resource = StardustAchievementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

