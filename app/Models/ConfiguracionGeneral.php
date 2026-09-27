<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConfiguracionGeneral extends Model
{
    use HasFactory;

    protected $table = 'configuracion_general';

    protected $fillable = [
        'nombre_comercial',
        'logotipo',
        'fondo_sistema',
        'modo_fondo',
        'color_primario',
        'color_apoyo',
        'color_apoyo_activo',
        'delivery_activo',
        'costo_envio_fijo',
        'envio_gratis_desde',
    ];

    protected $casts = [
        'color_apoyo_activo' => 'boolean',
        'delivery_activo'    => 'boolean',
        'costo_envio_fijo'   => 'float',
        'envio_gratis_desde' => 'float',
    ];

    protected $appends = [
        'business_name',
        'logo',
        'theme',
        'primary_color',
        'is_delivery_active',
        'fixed_delivery_fee',
        'free_delivery_threshold',
    ];

    public function getBusinessNameAttribute(): ?string
    {
        return $this->nombre_comercial;
    }

    public function getLogoAttribute(): ?string
    {
        return $this->logotipo;
    }

    public function getThemeAttribute(): string
    {
        return $this->modo_fondo === 'oscuro' ? 'dark' : 'light';
    }

    public function getPrimaryColorAttribute(): ?string
    {
        return $this->color_primario;
    }

    public function getIsDeliveryActiveAttribute(): bool
    {
        return (bool) $this->delivery_activo;
    }

    public function getFixedDeliveryFeeAttribute(): float
    {
        return (float) $this->costo_envio_fijo;
    }

    public function getFreeDeliveryThresholdAttribute(): float
    {
        return (float) $this->envio_gratis_desde;
    }

    /**
     * Get single record instance, creating with defaults if non-existent
     */
    public static function instance(): self
    {
        $config = self::first();

        if (!$config) {
            $config = self::create([
                'nombre_comercial'   => 'Aurum Restaurant',
                'logotipo'           => null,
                'fondo_sistema'      => '#1C1917',
                'modo_fondo'         => 'oscuro',
                'color_primario'     => '#7c3aed',
                'color_apoyo'        => null,
                'color_apoyo_activo' => false,
                'delivery_activo'    => true,
                'costo_envio_fijo'   => 0,
                'envio_gratis_desde' => 0,
            ]);
        }

        return $config;
    }
}
