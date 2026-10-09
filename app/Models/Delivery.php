<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\BelongsToSucursal;

class Delivery extends Model
{
    use BelongsToSucursal;

    protected $fillable = ['sucursal_id', 'order_id', 'driver_id', 'delivery_zone_id', 'status'];

    protected $casts = [
        'sucursal_id' => 'integer',
    ];

    public static function generarFolioUnico(): string
    {
        return Order::generarFolioUnico();
    }

    public static function descontarInventario($pedidoId): bool
    {
        return \App\Services\InventoryService::descontarInventario($pedidoId);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function detalles()
    {
        return $this->hasManyThrough(OrderItem::class, Order::class, 'id', 'order_id', 'order_id', 'id');
    }

    public function items()
    {
        return $this->detalles();
    }

    public function driver()
    {
        return $this->belongsTo(DeliveryDriver::class, 'driver_id');
    }

    public function zone()
    {
        return $this->belongsTo(DeliveryZone::class, 'delivery_zone_id');
    }
}
