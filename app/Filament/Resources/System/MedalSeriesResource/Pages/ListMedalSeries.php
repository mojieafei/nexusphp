<?php

namespace App\Filament\Resources\System\MedalSeriesResource\Pages;

use App\Filament\Resources\System\MedalSeriesResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMedalSeries extends ListRecords
{
    protected static string $resource = MedalSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

