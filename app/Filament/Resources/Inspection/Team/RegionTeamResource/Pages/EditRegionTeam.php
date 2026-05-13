<?php

namespace App\Filament\Resources\Inspection\Team\RegionTeamResource\Pages;

use App\Filament\Resources\Inspection\Team\RegionTeamResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditRegionTeam extends EditRecord
{
    protected static string $resource = RegionTeamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
