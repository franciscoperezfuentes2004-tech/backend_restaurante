<?php

namespace App\Models;

class Product extends Dish
{
    protected $table = 'dishes';

    public function ingredientes()
    {
        return $this->belongsToMany(Ingredient::class, 'dish_ingredient', 'dish_id', 'ingredient_id')
                    ->withPivot('cantidad_requerida')
                    ->withTimestamps();
    }
}