<?php

namespace App\Filament\Resources\ManagementUser\RoleResource\Pages;

use App\Filament\Resources\ManagementUser\RoleResource;
use Filament\Actions;
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
}
