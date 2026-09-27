<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    protected $fillable = [
        'type',
        'periodo',
        'fecha_inicio',
        'fecha_fin',
        'data',
        'generated_by'
    ];

    protected $casts = [
        'data'         => 'array',
        'fecha_inicio' => 'date:Y-m-d',
        'fecha_fin'    => 'date:Y-m-d',
        'created_at'   => 'datetime:Y-m-d H:i:s',
    ];
}
