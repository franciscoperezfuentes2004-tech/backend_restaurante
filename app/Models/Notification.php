<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToSucursal;

class Notification extends Model
{
    use SoftDeletes, BelongsToSucursal;

    protected $table = 'notifications';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'type',
        'title',
        'message',
        'data',
        'read_at',
    ];

    protected $casts = [
        'sucursal_id' => 'integer',
        'data'        => 'array',
        'read_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
