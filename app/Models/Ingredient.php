<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\Auditable;

class Ingredient extends Model
{
    use HasFactory, Auditable;

    protected $table = 'ingredients';

    protected $fillable = [
        'name',
        'category',
        'unit',
        'base_cost',
        'supplier_id',
        'notes',
        'stock_actual',
        'stock_minimo',
    ];

    protected $casts = [
        'base_cost'    => 'float',
        'stock_actual' => 'float',
        'stock_minimo' => 'float',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(IngredientCategory::class, 'category', 'name');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function dishes()
    {
        return $this->belongsToMany(Dish::class, 'dish_ingredient')
                    ->withPivot('cantidad_requerida')
                    ->withTimestamps();
    }

    public function productos()
    {
        return $this->dishes();
    }

    public function stock()
    {
        return $this->hasOne(Stock::class, 'ingredient_id');
    }

    public function getNombreAttribute()
    {
        return $this->name;
    }

    public function setNombreAttribute($value)
    {
        $this->attributes['name'] = $value;
    }
}
