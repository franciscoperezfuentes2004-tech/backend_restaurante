<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToSucursal;

class Area extends Model
{
    use HasFactory, SoftDeletes, BelongsToSucursal;

    protected $table = 'areas';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'name',
        'capacidad_personas',
        'capacity',
        'numero_mesas',
        'tables_count',
        'capacity_per_table',
        'is_active',
        'active',
    ];

    protected $casts = [
        'sucursal_id'        => 'integer',
        'is_active'          => 'boolean',
        'active'             => 'boolean',
        'capacidad_personas' => 'integer',
        'numero_mesas'       => 'integer',
    ];

    public function mesas()
    {
        return $this->hasMany(Mesa::class, 'area_id');
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class, 'area_id');
    }
}
