<?php

namespace App\Filament\Resources\Inspection\MasterData\CustomerResource\Pages;

use App\Filament\Resources\Inspection\MasterData\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
