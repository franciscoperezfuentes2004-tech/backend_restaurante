<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    protected $table = 'stock_movements';

    protected $fillable = [
        'ingredient_id',
        'type',
        'quantity',
        'cost_per_unit',
        'supplier_id',
        'expiry_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'quantity'      => 'float',
        'cost_per_unit' => 'float',
        'expiry_date'   => 'date:Y-m-d',
    ];

    public function ingredient(): BelongsTo
    {
        return $this->belongsTo(Ingredient::class, 'ingredient_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
