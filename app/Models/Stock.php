<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

class Stock extends Model
{
    use HasFactory, Auditable, BelongsToSucursal;

    protected $table = 'stock';

    protected $fillable = [
        'sucursal_id',
        'ingredient_id',
        'quantity',
        'min_quantity',
        'expiry_date',
        'supplier_id',
        'last_updated_by',
    ];

    protected $casts = [
        'sucursal_id'  => 'integer',
        'quantity'     => 'float',
        'min_quantity' => 'float',
        'expiry_date'  => 'date:Y-m-d',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function lastUpdatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }
}
