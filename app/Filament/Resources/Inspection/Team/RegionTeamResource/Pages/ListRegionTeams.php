<?php

namespace App\Filament\Resources\Inspection\Team\RegionTeamResource\Pages;

use App\Filament\Resources\Inspection\Team\RegionTeamResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListRegionTeams extends ListRecords
{
    protected static string $resource = RegionTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
