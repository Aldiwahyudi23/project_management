<?php

namespace App\Models\DirectDB;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $connection = 'vehicle'; // ⬅️ DB vehicle_management
    protected $table = 'vehicle_details';

    
    protected $fillable = [
        'brand_id', 'model_id', 'type_id', 'year', 'cc', 'fuel_type',
        'transmission_id', 'engine_type', 'origin_id', 'generation',
        'market_period', 'description', 'image_path','is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

        protected $appends = ['display_name'];

    public function getDisplayNameAttribute(): string
    {
        $brand = $this->brand?->name ?? '';
        $model = $this->model?->name ?? '';
        $type  = $this->type?->name ?? '';
        $year  = $this->year ?? '';
        $cc    = $this->formatCc($this->cc);
        $trans = $this->transmission?->code ?? '';

        return trim("{$brand} {$model} {$type} {$cc} {$year} {$trans}");
    }

    protected function formatCc(?int $cc): string
    {
        if (!$cc) return '';

        // 1298 → 1.3 | 998 → 1.0
        return number_format($cc / 1000, 1);
    }
    
    public function brand()
    {
        return $this->belongsTo(VehicleData\Brand::class, 'brand_id');
    }

    public function model()
    {
        return $this->belongsTo(VehicleData\VehicleModel::class, 'model_id');
    }


    public function type()
    {
        return $this->belongsTo(VehicleData\VehicleType::class, 'type_id');
    }

    public function transmission()
    {
        return $this->belongsTo(VehicleData\Transmission::class, 'transmission_id');
    }

    public function origin()
    {
        return $this->belongsTo(VehicleData\Origin::class, 'origin_id');
    }
}
