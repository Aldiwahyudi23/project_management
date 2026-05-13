<?php

namespace App\Models\MasterData;

use App\Traits\LogsAllActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class RegionTeam extends Model
{
    use HasFactory, SoftDeletes, LogsAllActivity;

    protected $table = 'region_teams';

    protected $fillable = [
        'region_id',
        'user_id',
        'status',
        'description',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function inspectionTemplates()
    {
        return $this->hasMany(
            UserInspectionTemplate::class,
            'user_id',
            'user_id'
        );
    }
}
