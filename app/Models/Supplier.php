<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

use App\Traits\Auditable;
use App\Traits\BelongsToSucursal;

class Supplier extends Model
{
    use HasFactory, Auditable, BelongsToSucursal;

    protected $table = 'suppliers';

    protected $fillable = [
        'sucursal_id',
        'company_name',
        'contact_name',
        'specialty',
        'phone',
        'email',
        'delivery_days',
        'active',
    ];

    protected $casts = [
        'sucursal_id'   => 'integer',
        'delivery_days' => 'array',
        'active'        => 'boolean',
    ];

    public function getNameAttribute(): string
    {
        return $this->company_name ?? '';
    }

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class, 'supplier_id');
    }
}
