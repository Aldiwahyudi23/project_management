<?php

namespace App\Models\MasterData;

use App\Models\User;
use App\Traits\LogsAllActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Region extends Model
{
    use HasFactory, SoftDeletes, LogsAllActivity;

    protected $table = 'regions';

    protected $fillable = [
        'name',
        'code',
        'address',
        'city',
        'province',
        'is_active',
        'settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'settings'  => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function teams()
    {
        return $this->hasMany(RegionTeam::class, 'region_id');
    }

        public function teamMembers()
    {
        return $this->belongsToMany(User::class, 'region_teams')
            ->withPivot('role', 'status')
            ->withTimestamps();
    }
}
     