<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

class Ingredient extends Model
{
    use HasFactory, Auditable, BelongsToSucursal;

    protected $table = 'ingredients';

    protected $fillable = [
        'sucursal_id',
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
        'sucursal_id'  => 'integer',
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
