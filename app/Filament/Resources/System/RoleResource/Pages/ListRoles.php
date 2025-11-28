<?php

namespace App\Filament\Resources\System\RoleResource\Pages;

use App\Filament\PageList;
use App\Filament\Resources\System\RoleResource;
use App\Models\Role;
use Filament\Pages\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListRoles extends PageList
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    protected function getTableQuery(): Builder
    {
        return Role::query()->withCount(['permissions', 'users']);
    }
}

