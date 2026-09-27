<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'user_id',
        'user_name',
        'role',
        'modulo',
        'accion',
        'descripcion',
        'valores_antes',
        'valores_despues',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'valores_antes' => 'array',
        'valores_despues' => 'array',
        'created_at' => 'datetime:Y-m-d H:i:s',
    ];

    /**
     * Immutability enforcement at Eloquent model level.
     * Prevents any update or delete operation on AuditLog records.
     */
    protected static function booted(): void
    {
        static::updating(function ($model) {
            throw new RuntimeException('Los registros de la bitácora de auditoría son inalterables y no se pueden modificar.');
        });

        static::deleting(function ($model) {
            throw new RuntimeException('Los registros de la bitácora de auditoría son inalterables y no se pueden eliminar.');
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
