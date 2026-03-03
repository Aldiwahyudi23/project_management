<?php

namespace App\Models\DirectDB\VehicleData;

use Illuminate\Database\Eloquent\Model;

class Origin extends Model
{
    protected $connection = 'vehicle'; // ⬅️ DB vehicle_management
    protected $table = 'vehicle_origins';
}
