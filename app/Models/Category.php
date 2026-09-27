<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Category extends Model
{
    use Auditable;

    protected $fillable = [
        'name', 'slug', 'description', 'image_url',
        'time_start', 'time_end', 'days', 'active', 'is_active',
        'limitar_dias', 'dias_disponibilidad'
    ];

    protected $casts = [
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
