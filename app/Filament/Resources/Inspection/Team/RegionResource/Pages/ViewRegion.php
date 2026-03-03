<?php

namespace App\Filament\Resources\Inspection\Team\RegionResource\Pages;

use App\Filament\Resources\Inspection\Team\RegionResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRegion extends ViewRecord
{
    protected static string $resource = RegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
