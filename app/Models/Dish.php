<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

class Dish extends Model
{
    use Auditable, SoftDeletes, BelongsToSucursal;

    protected $fillable = [
        'category_id', 'sucursal_id', 'name', 'slug', 'description',
        'price', 'image_url', 'allergens', 'ingredients',
        'allow_extras', 'allow_observations', 'allow_spice_level',
        'is_available', 'is_active', 'is_sold_out', 'is_featured',
        'limitar_dias', 'dias_disponibilidad'
    ];

    protected $casts = [
        'sucursal_id'         => 'integer',
        'is_available'        => 'boolean',
        'is_active'           => 'boolean',
        'is_sold_out'         => 'boolean',
        'is_featured'         => 'boolean',
        'limitar_dias'        => 'boolean',
        'dias_disponibilidad' => 'array',
        'price'               => 'decimal:2',
        'allergens'           => 'array',
        'ingredients'         => 'array',
        'allow_extras'        => 'boolean',
        'allow_observations'  => 'boolean',
        'allow_spice_level'   => 'boolean'
    ];

    protected static function booted()
    {
        static::saving(function ($dish) {
            // Sincronización transparente entre is_active e is_available
            if ($dish->isDirty('is_active') && !$dish->isDirty('is_available')) {
                $dish->is_available = $dish->is_active;
            } elseif ($dish->isDirty('is_available') && !$dish->isDirty('is_active')) {
                $dish->is_active = $dish->is_available;
            }
        });
    }

    public function getImageUrlAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            return $value;
        }

        $cleanPath = ltrim(str_replace('public/', '', $value), '/');

        return asset('storage/' . $cleanPath);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function extras()
    {
        return $this->belongsToMany(Extra::class, 'dish_extra');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function ingredientes()
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient')
                    ->withPivot('cantidad_requerida')
                    ->withTimestamps();
    }

    public function ingredients()
    {
        return $this->ingredientes();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'dish_id');
    }
}
