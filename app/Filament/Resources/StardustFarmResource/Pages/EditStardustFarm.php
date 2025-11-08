<?php

namespace App\Filament\Resources\StardustFarmResource\Pages;

use App\Filament\Resources\StardustFarmResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStardustFarm extends EditRecord
{
    protected static string $resource = StardustFarmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

