<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RestaurantSetting extends Model
{
    protected $fillable = [
        'restaurant_name', 'logo_url', 'description',
        'phone', 'address', 'facebook', 'instagram',
        'whatsapp', 'postal_code', 'schedule',
        'cover_images', 'brand_color', 'delivery_fee',
        'free_delivery_over', 'hero_title', 'hero_description',
        'city_state', 'municipio', 'coverage_polygon', 'street_name', 'secondary_color', 'banner_images', 'email', 'tiktok', 'delivery_radius_km', 'delivery_radius_meters',
        'latitude', 'longitude',
        'platillos_seccion',
        'delivery_seccion',
        'banner_descuento',
        'reservaciones_seccion',
        'historia_config',
        'servicios_config',
        'contacto_seccion',
        'hero_image',
        'use_carousel',
        'location_text',
        'hero_slogan',
        'hero_image_url',
        'menu_subtitle',
        'menu_title',
        'delivery_title',
        'delivery_description',
        'contact_phone',
        'contact_email',
        'facebook_url',
        'instagram_url',
        'tiktok_url',
        'cta_menu_text',
        'cta_reservation_text',
        'contact_whatsapp',
        'contact_whatsapp_url',
        'history_title',
        'history_description',
        'history_year',
        'history_image',
        'history_features',
        'featured_categories',
        'featured_dishes',
        'delivery_image_url',
        'delivery_image_description',
        'delivery_image_title',
        'correo',
        'correo_contacto',
        'telefono',
        'telefono_publico',
        'numero_whatsapp',
        'acepta_efectivo',
        'acepta_tarjeta',
        'acepta_transferencia',
        'banco_nombre',
        'banco_clabe',
        'banco_titular',
        'active_notification_platform',
        'discord_settings',
        'telegram_settings',
        'last_financial_report_date',
    ];

    protected $casts = [
        'last_financial_report_date' => 'date',
        'discord_settings'       => 'array',
        'telegram_settings'      => 'array',
        'schedule'              => 'array',
        'cover_images'          => 'array',
        'banner_images'         => 'array',
        'platillos_seccion'     => 'array',
        'delivery_seccion'      => 'array',
        'banner_descuento'      => 'array',
        'reservaciones_seccion' => 'array',
        'historia_config'       => 'array',
        'history_features'      => 'array',
        'featured_categories'   => 'array',
        'featured_dishes'       => 'array',
        'servicios_config'      => 'array',
        'contacto_seccion'      => 'array',
        'coverage_polygon'      => 'array',
        'delivery_radius_km'    => 'float',
        'delivery_radius_meters' => 'integer',
        'history_year'          => 'integer',
        'latitude'              => 'float',
        'longitude'             => 'float',
        'use_carousel'          => 'boolean',
        'acepta_efectivo'       => 'boolean',
        'acepta_tarjeta'        => 'boolean',
        'acepta_transferencia'  => 'boolean',
    ];

    /**
     * Mapeo de nombres de días en español e inglés al estándar Carbon/JS (0 = Domingo, 6 = Sábado).
     *
     * @var array<string, int>
     */
    protected static array $dayToCarbonMap = [
        'domingo'   => 0,
        'sunday'    => 0,
        'dom'       => 0,
        'sun'       => 0,
        '0'         => 0,

        'lunes'     => 1,
        'monday'    => 1,
        'lun'       => 1,
        'mon'       => 1,
        '1'         => 1,

        'martes'    => 2,
        'tuesday'   => 2,
        'mar'       => 2,
        'tue'       => 2,
        '2'         => 2,

        'miércoles' => 3,
        'miercoles' => 3,
        'wednesday' => 3,
        'mie'       => 3,
        'wed'       => 3,
        '3'         => 3,

        'jueves'    => 4,
        'thursday'  => 4,
        'thu'       => 4,
        'jue'       => 4,
        '4'         => 4,

        'viernes'   => 5,
        'friday'    => 5,
        'vie'       => 5,
        'fri'       => 5,
        '5'         => 5,

        'sábado'    => 6,
        'sabado'    => 6,
        'saturday'  => 6,
        'sat'       => 6,
        'sab'       => 6,
        '6'         => 6,
    ];

    /**
     * Obtiene los días de la semana en los que el restaurante está abierto.
     * Retorna un arreglo numérico con valores de 0 (Domingo) a 6 (Sábado).
     *
     * @return int[]
     */
    public static function getActiveOperatingDays(): array
    {
        $settings = static::first();
        $schedule = $settings?->schedule;

        if (!is_array($schedule) || empty($schedule)) {
            // Por defecto: Lunes a Sábado abiertos (1 a 6), Domingo cerrado (0)
            return [1, 2, 3, 4, 5, 6];
        }

        $activeDays = [];

        foreach ($schedule as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Evaluar interruptor booleano de activación ('is_active', 'active', 'abierto', 'is_open')
            $isOpen = false;
            if (array_key_exists('is_active', $item)) {
                $isOpen = filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['is_active'];
            } elseif (array_key_exists('active', $item)) {
                $isOpen = filter_var($item['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['active'];
            } elseif (array_key_exists('abierto', $item)) {
                $isOpen = filter_var($item['abierto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['abierto'];
            } elseif (array_key_exists('is_open', $item)) {
                $isOpen = filter_var($item['is_open'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['is_open'];
            } elseif (array_key_exists('open', $item)) {
                // Si 'open' es un string de hora (ej: "09:00"), no es el toggle booleano
                if (is_string($item['open']) && str_contains($item['open'], ':')) {
                    $isOpen = true;
                } else {
                    $val = filter_var($item['open'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $isOpen = $val !== null ? $val : (bool) $item['open'];
                }
            }

            if ($isOpen) {
                $rawDay = strtolower(trim((string) ($item['day'] ?? $item['dia'] ?? $item['name'] ?? '')));
                if (isset(static::$dayToCarbonMap[$rawDay])) {
                    $activeDays[] = static::$dayToCarbonMap[$rawDay];
                }
            }
        }

        if (empty($activeDays)) {
            return [1, 2, 3, 4, 5, 6];
        }

        $activeDays = array_values(array_unique($activeDays));
        sort($activeDays);

        return $activeDays;
    }

    /**
     * Normaliza cualquier formato de hora (ej: "9:00", "09:00", "09:00:00", "9:00 a. m.") a formato estándar "H:i".
     */
    public static function normalizeTimeString(mixed $time, string $default = '09:00'): string
    {
        if (!is_string($time) || empty(trim($time))) {
            return $default;
        }

        $time = trim($time);

        // Si ya viene en formato "HH:mm" (ej: "09:00", "23:00")
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]);
        }

        // Si viene con segundos (ej: "09:00:00")
        if (preg_match('/^\d{1,2}:\d{2}:\d{2}$/', $time)) {
            $parts = explode(':', $time);
            return sprintf('%02d:%02d', (int) $parts[0], (int) $parts[1]);
        }

        // Si viene con formato 12h (ej: "9:00 a. m.", "11:00 p. m.", "9:00 AM")
        try {
            $cleanTime = str_ireplace(['a. m.', 'a.m.', 'am'], 'AM', $time);
            $cleanTime = str_ireplace(['p. m.', 'p.m.', 'pm'], 'PM', $cleanTime);
            return \Carbon\Carbon::parse($cleanTime)->format('H:i');
        } catch (\Throwable $e) {
            return $default;
        }
    }

    /**
     * Obtiene el diccionario de rangos de horas de apertura y cierre indexados por el día numérico (0-6).
     * Ejemplo:
     * [
     *   "1" => [ "open" => "09:00", "close" => "23:00" ],
     *   "2" => [ "open" => "09:00", "close" => "23:00" ],
     *   ...
     * ]
     *
     * @return array<string, array{open: string, close: string}>
     */
    public static function getOperatingHours(): array
    {
        $settings = static::first();
        $schedule = $settings?->schedule;

        $defaultHours = [
            '1' => ['open' => '09:00', 'close' => '23:00'],
            '2' => ['open' => '09:00', 'close' => '23:00'],
            '3' => ['open' => '09:00', 'close' => '23:00'],
            '4' => ['open' => '09:00', 'close' => '23:00'],
            '5' => ['open' => '09:00', 'close' => '23:00'],
            '6' => ['open' => '09:00', 'close' => '23:00'],
        ];

        if (!is_array($schedule) || empty($schedule)) {
            return $defaultHours;
        }

        $hours = [];

        foreach ($schedule as $item) {
            if (!is_array($item)) {
                continue;
            }

            // Evaluar interruptor booleano de activación ('is_active', 'active', 'abierto', 'is_open')
            $isOpen = false;
            if (array_key_exists('is_active', $item)) {
                $isOpen = filter_var($item['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['is_active'];
            } elseif (array_key_exists('active', $item)) {
                $isOpen = filter_var($item['active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['active'];
            } elseif (array_key_exists('abierto', $item)) {
                $isOpen = filter_var($item['abierto'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['abierto'];
            } elseif (array_key_exists('is_open', $item)) {
                $isOpen = filter_var($item['is_open'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $item['is_open'];
            } elseif (array_key_exists('open', $item)) {
                if (is_string($item['open']) && str_contains($item['open'], ':')) {
                    $isOpen = true;
                } else {
                    $val = filter_var($item['open'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                    $isOpen = $val !== null ? $val : (bool) $item['open'];
                }
            }

            if ($isOpen) {
                $rawDay = strtolower(trim((string) ($item['day'] ?? $item['dia'] ?? $item['name'] ?? '')));
                if (isset(static::$dayToCarbonMap[$rawDay])) {
                    $dayIndex = static::$dayToCarbonMap[$rawDay];

                    $rawOpen = is_string($item['open'] ?? null) && str_contains($item['open'], ':')
                        ? $item['open']
                        : ($item['apertura'] ?? $item['desde'] ?? $item['start'] ?? '09:00');

                    $rawClose = is_string($item['close'] ?? null) && str_contains($item['close'], ':')
                        ? $item['close']
                        : ($item['cierre'] ?? $item['hasta'] ?? $item['end'] ?? '23:00');

                    $hours[(string) $dayIndex] = [
                        'open'  => static::normalizeTimeString($rawOpen, '09:00'),
                        'close' => static::normalizeTimeString($rawClose, '23:00'),
                    ];
                }
            }
        }

        if (empty($hours)) {
            return $defaultHours;
        }

        ksort($hours);

        return $hours;
    }
}
