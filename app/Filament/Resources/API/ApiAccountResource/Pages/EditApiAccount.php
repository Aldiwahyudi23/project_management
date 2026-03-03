<?php

namespace App\Filament\Resources\API\ApiAccountResource\Pages;

use App\Filament\Resources\API\ApiAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiAccount extends EditRecord
{
    protected static string $resource = ApiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
