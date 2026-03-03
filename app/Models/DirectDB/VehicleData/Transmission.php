<?php

namespace App\Models\DirectDB\VehicleData;

use Illuminate\Database\Eloquent\Model;

class Transmission extends Model
{
    protected $connection = 'vehicle'; // ⬅️ DB vehicle_management
    protected $table = 'transmission_types';
}
