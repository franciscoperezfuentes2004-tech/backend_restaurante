<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItemExtra extends Model
{
    protected $fillable = ['order_item_id', 'extra_id', 'price'];

    public function extra()
    {
        return $this->belongsTo(Extra::class);
    }
}
