<?php

namespace App\Models\MasterData\Customer;

use App\Models\Inspection;
use App\Traits\LogsAllActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Seller extends Model
{
    use SoftDeletes, LogsAllActivity;

    protected $table = 'sellers';

    protected $fillable = [
        'customer_id',
        'inspection_id',
        'inspection_area',
        'inspection_address',
        'link_maps',
        'unit_holder_name',
        'unit_holder_phone',
        'settings',
    ];

    protected $casts = [
        'settings' => 'json',
        'status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the customer that owns the seller.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the inspection that owns the seller.
     */
    public function inspection()
    {
        return $this->belongsTo(Inspection::class);
    }
}