<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryZone extends Model
{
    protected $fillable = ['name', 'shipping_cost', 'active'];

    protected $casts = [
        'active' => 'boolean',
        'shipping_cost' => 'decimal:2'
    ];
}
