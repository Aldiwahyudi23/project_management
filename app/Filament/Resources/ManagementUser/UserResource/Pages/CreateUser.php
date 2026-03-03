<?php

namespace App\Filament\Resources\ManagementUser\UserResource\Pages;

use App\Filament\Resources\ManagementUser\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
