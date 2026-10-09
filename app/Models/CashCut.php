<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\BelongsToSucursal;

class CashCut extends Model
{
    use HasFactory, BelongsToSucursal;

    protected $table = 'cash_cuts';

    protected $fillable = [
        'sucursal_id',
        'user_id',
        'cut_type',
        'cash_declared',
        'expected_cash',
        'received_cash',
        'total_orders',
        'status',
        'confirmed_by',
        'confirmed_at',
        'notes',
    ];

    protected $casts = [
        'sucursal_id'   => 'integer',
        'cut_type'      => 'string',
        'cash_declared' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'received_cash' => 'decimal:2',
        'total_orders'  => 'integer',
        'confirmed_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }
}
