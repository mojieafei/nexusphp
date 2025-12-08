<?php

namespace App\Filament\Resources\System\BonusProductResource\Pages;

use App\Filament\Resources\System\BonusProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBonusProducts extends ListRecords
{
    protected static string $resource = BonusProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

