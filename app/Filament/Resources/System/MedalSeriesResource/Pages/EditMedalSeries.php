<?php

namespace App\Filament\Resources\System\MedalSeriesResource\Pages;

use App\Filament\Resources\System\MedalSeriesResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMedalSeries extends EditRecord
{
    protected static string $resource = MedalSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

