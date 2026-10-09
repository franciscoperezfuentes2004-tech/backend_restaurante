<?php

namespace App\Models;

use App\Traits\BelongsToSucursal;

class Table extends Mesa
{
    use BelongsToSucursal;

    protected $table = 'mesas';
}
