<?php

namespace App\Models;

use App\Traits\BelongsToSucursal;

class Product extends Dish
{
    use BelongsToSucursal;

    protected $table = 'dishes';

    public function ingredientes()
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient', 'dish_id', 'ingredient_id')
                    ->withPivot('cantidad_requerida')
                    ->withTimestamps();
    }
}