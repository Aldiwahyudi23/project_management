<?php

namespace App\Models\DirectDB\Inspection;

use Illuminate\Database\Eloquent\Model;

class TemplateRepot extends Model
{
    protected $connection = 'inspection'; // ⬅️ DB vehicle_management
    protected $table = 'report_templates';
}
