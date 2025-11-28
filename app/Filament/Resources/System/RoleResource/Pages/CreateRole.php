<?php

namespace App\Filament\Resources\System\RoleResource\Pages;

use App\Filament\Resources\System\RoleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateRole extends CreateRecord
{
    protected static string $resource = RoleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);
        $this->permissions = $permissions;
        return $data;
    }

    protected function afterCreate(): void
    {
        if (isset($this->permissions) && is_array($this->permissions)) {
            $this->record->setPermissions($this->permissions);
        }
    }
}

