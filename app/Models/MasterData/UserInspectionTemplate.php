<?php

namespace App\Models\MasterData;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserInspectionTemplate extends Model
{
    protected $table = 'user_inspection_templates';

    protected $fillable = [
        'user_id',
        'template_id',
        'template_type',
        'name',
        'is_default',
        'is_active',
        'config',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'config' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | CONSTANTS
    |--------------------------------------------------------------------------
    */
    const TYPE_REPORT = 'report';
    const TYPE_FORM = 'form';

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('template_type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /*
    |--------------------------------------------------------------------------
    | RELATION (OPTIONAL - kalau 1 DB)
    |--------------------------------------------------------------------------
    */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    // Set sebagai default (auto unset yang lain)
    public function setAsDefault()
    {
        self::where('user_id', $this->user_id)
            ->where('template_type', $this->template_type)
            ->update(['is_default' => false]);

        $this->is_default = true;
        $this->save();
    }

    // Activate template
    public function activate()
    {
        $this->is_active = true;
        $this->save();
    }

    // Deactivate template
    public function deactivate()
    {
        $this->is_active = false;
        $this->save();
    }

    // Update config (merge, bukan replace total)
    public function updateConfig(array $newConfig)
    {
        $current = $this->config ?? [];
        $this->config = array_replace_recursive($current, $newConfig);
        $this->save();
    }

    /*
    |--------------------------------------------------------------------------
    | STATIC HELPERS
    |--------------------------------------------------------------------------
    */

    // Ambil default template user berdasarkan type
    public static function getDefault($userId, $type)
    {
        return self::where('user_id', $userId)
            ->where('template_type', $type)
            ->where('is_default', true)
            ->first();
    }
}