<?php

namespace App\Filament\Resources\StardustFarmResource\Pages;

use App\Filament\Resources\StardustFarmResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListStardustFarms extends ListRecords
{
    protected static string $resource = StardustFarmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

