<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryDriver extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'user_id',
        'status',
        'active',
        'vehicle_type',
        'license_plate',
        'avatar',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deliveries()
    {
        return $this->hasMany(Delivery::class, 'driver_id');
    }
}
