<?php

namespace App\Filament\Resources\System\RoleResource\Pages;

use App\Filament\Resources\System\RoleResource;
use Filament\Pages\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRole extends EditRecord
{
    protected static string $resource = RoleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $permissions = $data['permissions'] ?? [];
        unset($data['permissions']);
        $this->permissions = $permissions;
        return $data;
    }

    protected function afterSave(): void
    {
        if (isset($this->permissions) && is_array($this->permissions)) {
            $this->record->setPermissions($this->permissions);
        }
    }
}

