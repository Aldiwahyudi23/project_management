<?php

namespace App\Filament\Resources\Inspection\MasterData\CustomerResource\Pages;

use App\Filament\Resources\Inspection\MasterData\CustomerResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;
}
