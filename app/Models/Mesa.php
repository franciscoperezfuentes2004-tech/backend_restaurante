<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Mesa extends Model
{
    use HasFactory;

    protected $table = 'mesas';

    protected $fillable = [
        'area_id',
        'numero_mesa',
        'capacidad',
        'is_active',
        'status',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'numero_mesa' => 'integer',
        'capacidad'   => 'integer',
        'status'      => 'string',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'table_id');
    }

    public function activeOrder()
    {
        return $this->hasOne(Order::class, 'table_id')
            ->whereIn('status', ['pending', 'preparing', 'ready', 'open', 'abierto', 'en_preparacion']);
    }
}
