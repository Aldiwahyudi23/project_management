<?php

namespace App\Filament\Resources\API\ApiAccountResource\Pages;

use App\Filament\Resources\API\ApiAccountResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiAccounts extends ListRecords
{
    protected static string $resource = ApiAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
