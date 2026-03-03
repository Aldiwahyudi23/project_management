<?php

namespace App\Models\DirectDB\VehicleData;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $connection = 'vehicle'; // ⬅️ DB vehicle_management
    protected $table = 'vehicle_brands';
}
