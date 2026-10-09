<?php

namespace App\Models;

use App\Traits\BelongsToSucursal;

class Payment extends Order
{
    use BelongsToSucursal;

    protected $table = 'orders';
    // Alias model for payments
}