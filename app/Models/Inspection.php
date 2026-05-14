<?php

namespace App\Models;

use App\Models\DirectDB\DirectDBInspection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\MasterData\Customer\Customer;
use App\Models\MasterData\Customer\Seller;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Inspection extends Model
{
    use SoftDeletes, LogsActivity;

    protected $table = 'inspections';

    protected $fillable = [
        'uuid',
        'inspection_id', // ID dari sistem eksternal
        'customer_id',
        'inspector_id',
        'submitted_by',
        'status',
        'inspection_date',
        'notes',
        'reference',
        'settings'
    ];

    protected $casts = [
        'inspection_date' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'settings' => 'array',
    ];

    //Untuk mengenare id 
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->uuid) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }


       /**
     * Konfigurasi Activity Log
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            // FIELD YANG AKAN DI-LOG
            ->logOnly([
                'status',
                'inspector_id',
                'inspection_date',
                'customer_id',
                'reference',
                'notes'
            ])
            // LOG JUGA RELASI (otomatis ambil nama)
            ->logOnlyDirty() // Hanya record perubahan
            ->dontSubmitEmptyLogs() // Jangan log jika tidak ada perubahan
            ->useLogName('inspection') // Nama log
            ->setDescriptionForEvent(fn(string $eventName) => match ($eventName) {
                'created' => 'Inspeksi baru dibuat',
                'updated' => 'Data inspeksi diperbarui',
                'deleted' => 'Inspeksi dihapus',
                'restored' => 'Inspeksi dipulihkan',
                default => "Inspeksi {$eventName}",
            });
    }

    /**
     * Get the customer that owns the inspection.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the inspector (user) assigned to this inspection.
     */
    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * get report link
     */
    public function reportLink()
    {
        return $this->hasOne(InspectionReportLink::class);
    }

    /**
     * Get the user who submitted this inspection.
     */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /**
     * Get the sellers for this inspection.
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }

    public function seller()
    {
        return $this->hasOne(Seller::class, 'inspection_id', 'id');
    }


    /**
     * Get the external inspection data from DirectDBInspection.
     */
    public function externalInspection()
    {
        // Asumsi: DirectDBInspection adalah model untuk tabel eksternal
        return $this->belongsTo(DirectDBInspection::class, 'inspection_id', 'id');
    }

    /**
     * Scope untuk filter berdasarkan status.
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan tanggal inspeksi.
     */
    public function scopeInspectionDateBetween($query, $startDate, $endDate)
    {
        return $query->whereBetween('inspection_date', [$startDate, $endDate]);
    }

    /**
     * Scope untuk filter inspeksi yang sudah lewat.
     */
    public function scopePast($query)
    {
        return $query->where('inspection_date', '<', now());
    }

    /**
     * Scope untuk filter inspeksi yang akan datang.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('inspection_date', '>', now());
    }

    /**
     * Scope untuk filter inspeksi hari ini.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('inspection_date', today());
    }

    /**
     * Get status label attribute.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'accepted' => 'Diterima',
            'on_the_way' => 'Menuju Lokasi',
            'arrived' => 'Sampai Lokasi',
            'in_progress' => 'Sedang Berjalan',
            'paused' => 'Ditunda Sementara',
            'pending' => 'Tertunda',
            'under_review' => 'Dalam Review',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'revision' => 'Perlu Revisi',
            'completed' => 'Selesai',
            'cancelled' => 'Dibatalkan',
            default => ucfirst($this->status),
        };
    }

    /**
     * Get status color attribute for badges.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'gray',
            'accepted' => 'info',
            'on_the_way' => 'warning',
            'pending' => 'warning',
            'arrived' => 'success',
            'in_progress' => 'primary',
            'paused' => 'warning',
            'under_review' => 'info',
            'approved' => 'success',
            'rejected' => 'danger',
            'revision' => 'warning',
            'completed' => 'success',
            'cancelled' => 'danger',
            default => 'gray',
        };
    }

    /**
     * Get status icon attribute.
     */
    public function getStatusIconAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'heroicon-o-pencil',
            'accepted' => 'heroicon-o-check-circle',
            'on_the_way' => 'heroicon-o-truck',
            'pending' => 'heroicon-o-clock',
            'arrived' => 'heroicon-o-location-marker',
            'in_progress' => 'heroicon-o-cog',
            'paused' => 'heroicon-o-clock',
            'under_review' => 'heroicon-o-eye',
            'approved' => 'heroicon-o-check-badge',
            'rejected' => 'heroicon-o-x-circle',
            'revision' => 'heroicon-o-arrow-path',
            'completed' => 'heroicon-o-check',
            'cancelled' => 'heroicon-o-ban',
            default => 'heroicon-o-document',
        };
    }

}