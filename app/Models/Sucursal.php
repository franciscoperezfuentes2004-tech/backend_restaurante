<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sucursal extends Model
{
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'codigo',
        'direccion',
        'telefono',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'sucursal_id');
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class, 'sucursal_id');
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, 'sucursal_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'sucursal_id');
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class, 'sucursal_id');
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'sucursal_id');
    }

    public function mesas(): HasMany
    {
        return $this->hasMany(Mesa::class, 'sucursal_id');
    }

    public function cashCuts(): HasMany
    {
        return $this->hasMany(CashCut::class, 'sucursal_id');
    }
}
