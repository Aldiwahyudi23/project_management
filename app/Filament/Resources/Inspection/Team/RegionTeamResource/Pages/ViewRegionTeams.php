<?php

namespace App\Filament\Resources\Inspection\Team\RegionTeamResource\Pages;

use App\Filament\Resources\Inspection\Team\RegionTeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewRegionTeams extends ViewRecord
{
    protected static string $resource = RegionTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
