<?php

namespace App\Models\MasterData\Customer;

use App\Traits\LogsAllActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use SoftDeletes, LogsAllActivity;

    protected $table = 'customers';

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the sellers for the customer.
     */
    public function sellers(): HasMany
    {
        return $this->hasMany(Seller::class);
    }
}