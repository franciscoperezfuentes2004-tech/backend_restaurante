<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledReport extends Model
{
    protected $fillable = [
        'name',
        'type',
        'frequency',
        'day_of_week',
        'day_of_month',
        'time',
        'emails',
        'format',
        'active',
        'next_run',
    ];

    protected $casts = [
        'emails'   => 'array',
        'active'   => 'boolean',
        'next_run' => 'datetime:Y-m-d H:i:s',
    ];
}
