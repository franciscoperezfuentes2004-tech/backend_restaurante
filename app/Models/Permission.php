<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'descripcion',
        'modulo',
        'clave',
    ];

    public function rolePermissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function userPermissions()
    {
        return $this->hasMany(UserPermission::class);
    }
}
