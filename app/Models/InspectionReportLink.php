<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InspectionReportLink extends Model
{
    use HasFactory;

    protected $fillable = [
        'inspection_id',

        'token',
        'code',

        'expired_at',

        'first_opened_at',

        'device_hash',
        'ip_address',
        'user_agent',

        'is_active',

        'access_count',
        'last_accessed_at',
    ];

    protected $casts = [
        'expired_at'       => 'datetime',
        'first_opened_at'  => 'datetime',
        'last_accessed_at' => 'datetime',

        'is_active' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONS
    |--------------------------------------------------------------------------
    */

    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isExpired(): bool
    {
        return now()->greaterThan($this->expired_at);
    }

    public function isAccessible(): bool
    {
        return $this->is_active && !$this->isExpired();
    }

    public function markAsAccessed(
        string $deviceHash,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): void {

        // pertama kali dibuka
        if (!$this->first_opened_at) {

            $this->first_opened_at = now();
        }

        // simpan device pertama
        if (!$this->device_hash) {

            $this->device_hash = $deviceHash;
        }

        // tracking
        $this->ip_address      = $ipAddress;
        $this->user_agent      = $userAgent;
        $this->last_accessed_at = now();

        $this->access_count++;

        $this->save();
    }

    public function isSameDevice(
        string $deviceHash
    ): bool {

        // belum ada device pertama
        if (!$this->device_hash) {
            return true;
        }

        return $this->device_hash === $deviceHash;
    }
}