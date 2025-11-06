<?php

namespace App\Filament\Resources\System\BannerResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\BannerResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;

class ListBanners extends PageList
{
    protected static string $resource = BannerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

