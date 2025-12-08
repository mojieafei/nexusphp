<?php

namespace App\Filament\Resources\System\SpecialPermissionResource\Pages;

use App\Filament\Resources\System\SpecialPermissionResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSpecialPermissions extends ListRecords
{
    protected static string $resource = SpecialPermissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

