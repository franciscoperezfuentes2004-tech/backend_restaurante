<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Extra extends Model
{
    use Auditable;

    protected $fillable = ['name', 'price', 'is_free', 'active'];

    protected $casts = [
        'active'  => 'boolean',
        'is_free' => 'boolean',
        'price'   => 'float'
    ];

    public function dishes()
    {
        return $this->belongsToMany(Dish::class, 'dish_extra');
    }
}
