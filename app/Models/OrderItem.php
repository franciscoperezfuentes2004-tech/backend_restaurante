<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'dish_id', 'quantity', 'price', 'notes'];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function dish()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function producto()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function platillo()
    {
        return $this->belongsTo(Dish::class, 'dish_id');
    }

    public function getCantidadAttribute()
    {
        return $this->quantity;
    }

    public function extras()
    {
        return $this->hasMany(OrderItemExtra::class);
    }
}
