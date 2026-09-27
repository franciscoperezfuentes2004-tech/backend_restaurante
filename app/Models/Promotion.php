<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Promotion extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'type',
        'scheme',
        'benefit',
        'products',
        'days',
        'date_start',
        'date_end',
        'time_start',
        'time_end',
        'active',
        'show_on_landing',
        'aplica_en',
        'mensaje_banner',
        'fecha_inicio',
        'fecha_fin',
    ];

    protected $casts = [
        'products'        => 'array',
        'days'            => 'array',
        'active'          => 'boolean',
        'show_on_landing' => 'boolean',
        'date_start'      => 'date',
        'date_end'        => 'date',
        'fecha_inicio'    => 'date',
        'fecha_fin'       => 'date',
    ];
}
