<?php

namespace App\Filament\Resources\StardustLandResource\Pages;

use App\Filament\Resources\StardustLandResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditStardustLand extends EditRecord
{
    protected static string $resource = StardustLandResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

