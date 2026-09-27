<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSession extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'session_id',
        'ip_address',
        'last_ip',
        'user_agent',
        'browser',
        'platform',
        'last_activity',
        'last_seen_at',
        'ended_at',
        'created_at',
    ];

    protected $casts = [
        'last_activity' => 'datetime',
        'last_seen_at'  => 'datetime',
        'ended_at'      => 'datetime',
        'created_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
