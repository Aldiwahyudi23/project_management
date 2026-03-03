<?php

namespace App\Models\DirectDB;

use App\Models\DirectDB\Inspection\RepairEstimation;
use App\Models\DirectDB\Inspection\Template;
use App\Models\DirectDB\Vehicle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DirectDBInspection  extends Model
{
    use HasFactory, SoftDeletes;

    protected $connection = 'inspection'; // ⬅️ DB vehicle_management
    protected $table = 'inspections';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'template_id',
        'vehicle_id',
        'vehicle_name',
        'license_plate',
        'mileage',
        'color',
        'chassis_number',
        'engine_number',
        'inspection_date',
        'status',
        'settings',
        'notes',
        'document_path',
        'inspection_code',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'inspection_date' => 'datetime',
        'mileage' => 'integer',
        'settings' => 'array',
        'vehicle_id' => 'integer',
    ];

    /**
     * Get the template used for this inspection.
     */
    public function template()
    {
        return $this->belongsTo(Template::class, 'template_id');
    }


    public function repairEstimations()
    {
        return $this->hasMany(RepairEstimation::class);
    }
    
    /**
     * Get the vehicle associated with this inspection.
     */     
    // Data Vehicle dari DirectDB eksternal connection

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
    
    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active inspections.
     */
    public function scopeActive($query)
    {
        return $query->whereNotIn('status', ['completed', 'cancelled', 'rejected']);
    }

    /**
     * Scope a query to only include completed inspections.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope a query to filter by vehicle ID.
     */
    public function scopeByVehicle($query, $vehicleId)
    {
        return $query->where('vehicle_id', $vehicleId);
    }

    /**
     * Scope a query to filter by license plate.
     */
    public function scopeByLicensePlate($query, $licensePlate)
    {
        return $query->where('license_plate', 'LIKE', "%{$licensePlate}%");
    }

    /**
     * Scope a query to filter by date range.
     */
    public function scopeDateRange($query, $startDate, $endDate = null)
    {
        $endDate = $endDate ?: $startDate;
        return $query->whereBetween('inspection_date', [$startDate, $endDate]);
    }

    /**
     * Scope a query to order by inspection date (newest first).
     */
    public function scopeLatestFirst($query)
    {
        return $query->orderBy('inspection_date', 'desc');
    }

    /**
     * Check if inspection is in draft status.
     */
    public function isDraft()
    {
        return $this->status === 'draft';
    }

    /**
     * Check if inspection is in progress.
     */
    public function isInProgress()
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if inspection is under review.
     */
    public function isUnderReview()
    {
        return $this->status === 'under_review';
    }

    /**
     * Check if inspection is approved.
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }

    /**
     * Check if inspection is completed.
     */
    public function isCompleted()
    {
        return $this->status === 'completed';
    }

    /**
     * Check if inspection can be edited.
     */
    public function canBeEdited()
    {
        return in_array($this->status, ['draft', 'in_progress', 'pending', 'revision']);
    }

    /**
     * Check if inspection can be reviewed.
     */
    public function canBeReviewed()
    {
        return in_array($this->status, ['under_review', 'pending']);
    }

    /**
     * Get a specific setting value.
     */
    public function getSetting($key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Get the document URL if exists.
     */
    public function getDocumentUrlAttribute()
    {
        if (!$this->document_path) {
            return null;
        }

        return asset('storage/' . $this->document_path);
    }

    /**
     * Get the vehicle display name.
     */
    public function getVehicleDisplayAttribute()
    {
        if ($this->vehicle_name && $this->license_plate) {
            return $this->vehicle_name . ' (' . $this->license_plate . ')';
        }
        
        return $this->vehicle_name ?: $this->license_plate ?: 'Vehicle #' . $this->vehicle_id;
    }

    /**
     * Get progress percentage based on completed items.
     */
    public function getProgressPercentageAttribute()
    {
        $totalItems = $this->sectionItems()->count();
        $completedItems = $this->results()->count();
        
        if ($totalItems === 0) {
            return 0;
        }
        
        return round(($completedItems / $totalItems) * 100);
    }

    /**
     * Get damage section results.
     */
    public function damageResults()
    {
        return $this->results()->whereHas('sectionItem', function ($query) {
            $query->whereHas('menuSection', function ($q) {
                $q->where('section_type', 'damage');
            });
        });
    }
}