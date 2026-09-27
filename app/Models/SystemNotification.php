<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemNotification extends Model
{
    protected $fillable = [
        'type',
        'message',
        'is_read',
        'metadata',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'metadata' => 'array',
    ];
}
