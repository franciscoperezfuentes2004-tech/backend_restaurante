<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSucursal;

class OrderItem extends Model
{
    use BelongsToSucursal;

    protected $fillable = ['order_id', 'sucursal_id', 'dish_id', 'quantity', 'price', 'notes'];

    protected $casts = [
        'sucursal_id' => 'integer',
        'quantity'    => 'integer',
        'price'       => 'decimal:2',
    ];

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
