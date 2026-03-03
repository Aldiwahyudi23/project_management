<?php

namespace App\Models\DirectDB\Inspection;

use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    protected $connection = 'inspection'; // ⬅️ DB vehicle_management
    protected $table = 'inspection_templates';
}
