<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

class Category extends Model
{
    use Auditable, BelongsToSucursal;

    protected $fillable = [
        'sucursal_id', 'name', 'slug', 'description', 'image_url',
        'time_start', 'time_end', 'days', 'active', 'is_active',
        'limitar_dias', 'dias_disponibilidad'
    ];

    protected $casts = [
        'sucursal_id'         => 'integer',
        'active'              => 'boolean',
        'limitar_dias'        => 'boolean',
        'days'                => 'array',
        'dias_disponibilidad' => 'array'
    ];

    public function setIsActiveAttribute($value): void
    {
        $this->attributes['active'] = (bool) $value;
    }

    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->attributes['active'] ?? true);
    }

    public function dishes()
    {
        return $this->hasMany(Dish::class);
    }
}
