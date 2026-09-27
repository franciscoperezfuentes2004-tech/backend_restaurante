<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Order extends Model
{
    use Auditable;

    protected $fillable = [
        'folio', 'dispatch_token', 'daily_number', 'user_id', 'waiter_id', 'customer_name', 'customer_phone',
        'customer_address', 'customer_email', 'table_id', 'table_number',
        'status', 'payment_status', 'payment_method',
        'total_amount', 'notes', 'modality'
    ];

    protected $attributes = [
        'status'         => 'pending',
        'payment_status' => 'pending',
    ];

    public static function generateUniqueDispatchToken(): string
    {
        // Caracteres alfanuméricos en mayúsculas sin caracteres ambiguos (0, O, 1, I, L)
        $chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
        $charLen = strlen($chars);

        do {
            $token = '';
            for ($i = 0; $i < 6; $i++) {
                $token .= $chars[random_int(0, $charLen - 1)];
            }
        } while (static::where('dispatch_token', $token)->exists());

        return $token;
    }

    public static function getTenantPrefix(): string
    {
        $prefix = config('app.tenant_prefix');
        if (!empty($prefix)) {
            return strtoupper(trim($prefix));
        }

        try {
            $setting = \App\Models\RestaurantSetting::first() ?? \App\Models\ConfiguracionGeneral::first();
            if ($setting) {
                $name = $setting->restaurant_name ?? $setting->nombre_comercial ?? '';
                if (!empty($name)) {
                    $firstWord = explode(' ', trim($name))[0];
                    $clean = preg_replace('/[^A-Za-z0-9]/', '', $firstWord);
                    if (!empty($clean)) {
                        return strtoupper($clean);
                    }
                }
            }
        } catch (\Throwable $e) {}

        return 'SYS';
    }

    /**
     * Generar un folio único para Pedidos basado en la fecha actual sin guiones (PEDYYYYMMDDXXXX / DELYYYYMMDDXXXX)
     * utilizando una transacción con bloqueo pesimista (lockForUpdate) en PostgreSQL.
     */
    public static function generarFolioPedido(string $prefix = 'PED'): string
    {
        return DB::transaction(function () use ($prefix) {
            $hoy = Carbon::now();

            // 1. Prefijo SIN guiones: PED20260917 / DEL20260917
            $prefijo = $prefix . $hoy->format('Ymd');

            // 2. Búsqueda y Bloqueo (Crucial para evitar folios duplicados en peticiones simultáneas)
            $ultimoPedido = static::where(function ($query) use ($hoy, $prefijo, $prefix) {
                                $query->whereDate('created_at', $hoy->toDateString())
                                      ->orWhere('folio', 'like', $prefijo . '%')
                                      ->orWhere('folio', 'like', $prefix . '-' . $hoy->format('Ymd') . '%');
                            })
                            ->where(function ($query) use ($prefijo, $prefix) {
                                $query->where('folio', 'like', $prefijo . '%')
                                      ->orWhere('folio', 'like', $prefix . '-%')
                                      ->orWhere('folio', 'like', '%' . $prefix . '%');
                            })
                            ->lockForUpdate()
                            ->orderBy('id', 'desc')
                            ->first();

            // 3. Incremento Matemático Seguro
            if (!$ultimoPedido || empty($ultimoPedido->folio)) {
                $siguienteNumero = 1;
            } else {
                // Extrae los últimos 4 dígitos y le suma 1
                $folioLimpio = $ultimoPedido->folio;
                if (str_contains($folioLimpio, '-')) {
                    $partes = explode('-', $folioLimpio);
                    $ultimoNumero = (int) end($partes);
                } else {
                    $ultimoNumero = (int) substr($folioLimpio, -4);
                }
                $siguienteNumero = $ultimoNumero > 0 ? $ultimoNumero + 1 : 1;
            }

            // 4. Retorno Formateado (ej. PED202609170001)
            return $prefijo . str_pad($siguienteNumero, 4, '0', STR_PAD_LEFT);
        });
    }

    /**
     * Alias para compatibilidad con Delivery (DELYYYYMMDDXXXX)
     */
    public static function generarFolioUnico(): string
    {
        return static::generarFolioPedido('DEL');
    }

    protected static function booted()
    {
        static::creating(function ($order) {
            if (!$order->daily_number) {
                $ultimoPedidoHoy = static::whereDate('created_at', Carbon::today())->latest('id')->first();
                $order->daily_number = $ultimoPedidoHoy ? ($ultimoPedidoHoy->daily_number + 1) : 1;
            }

            if (!$order->folio) {
                if ($order->modality === 'delivery') {
                    $order->folio = static::generarFolioPedido('DEL');
                } else {
                    $order->folio = static::generarFolioPedido('PED');
                }
            }

            if (!$order->dispatch_token) {
                $order->dispatch_token = static::generateUniqueDispatchToken();
            }
        });

        static::created(function ($order) {
            if ($order->modality === 'delivery') {
                if (!$order->delivery()->exists()) {
                    $order->delivery()->create([
                        'status' => 'pending',
                    ]);
                }
            }
        });
    }

    protected function serializeDate(\DateTimeInterface $date)
    {
        return $date->setTimezone(new \DateTimeZone('America/Mexico_City'))->format('Y-m-d H:i:s');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function detalles()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function waiter()
    {
        return $this->belongsTo(User::class, 'waiter_id');
    }

    public function table()
    {
        return $this->belongsTo(Mesa::class, 'table_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class);
    }
}
