<?php

namespace App\Filament\Resources\Inspection\MasterData\CustomerResource\Pages;

use App\Filament\Resources\Inspection\MasterData\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomer extends ViewRecord
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
