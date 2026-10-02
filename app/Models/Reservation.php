<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class Reservation extends Model
{
    use Auditable;

    protected $fillable = [
        'nombre', 'telefono', 'email', 'fecha', 'hora', 'personas',
        'zona_preferida', 'ocasion_especial', 'nota_especial', 'estado',
        'user_id', 'area_id', 'area', 'table_id', 'customer_name', 'customer_email',
        'customer_phone', 'reservation_date', 'reservation_time',
        'guests_count', 'table_number', 'status', 'special_requests',
        'folio'
    ];

    protected $casts = [
        'personas'     => 'integer',
        'guests_count' => 'integer',
    ];

    protected static function booted()
    {
        static::creating(function ($reservation) {
            // Sincronización hacia nombres en español si vienen en inglés
            if ($reservation->customer_name && !$reservation->nombre) {
                $reservation->nombre = $reservation->customer_name;
            } elseif ($reservation->nombre && !$reservation->customer_name) {
                $reservation->customer_name = $reservation->nombre;
            }

            if ($reservation->customer_phone && !$reservation->telefono) {
                $reservation->telefono = $reservation->customer_phone;
            } elseif ($reservation->telefono && !$reservation->customer_phone) {
                $reservation->customer_phone = $reservation->telefono;
            }

            if ($reservation->customer_email && !$reservation->email) {
                $reservation->email = $reservation->customer_email;
            } elseif ($reservation->email && !$reservation->customer_email) {
                $reservation->customer_email = $reservation->email;
            }

            if ($reservation->reservation_date && !$reservation->fecha) {
                $reservation->fecha = $reservation->reservation_date;
            } elseif ($reservation->fecha && !$reservation->reservation_date) {
                $reservation->reservation_date = $reservation->fecha;
            }

            if ($reservation->reservation_time && !$reservation->hora) {
                $reservation->hora = $reservation->reservation_time;
            } elseif ($reservation->hora && !$reservation->reservation_time) {
                $reservation->reservation_time = $reservation->hora;
            }

            if ($reservation->guests_count && !$reservation->personas) {
                $reservation->personas = (int) $reservation->guests_count;
            } elseif ($reservation->personas && !$reservation->guests_count) {
                $reservation->guests_count = (int) $reservation->personas;
            }

            if ($reservation->special_requests && !$reservation->nota_especial) {
                $reservation->nota_especial = $reservation->special_requests;
            } elseif ($reservation->nota_especial && !$reservation->special_requests) {
                $reservation->special_requests = $reservation->nota_especial;
            }

            if ($reservation->status && !$reservation->estado) {
                $reservation->estado = match($reservation->status) {
                    'pending'   => 'pendiente',
                    'confirmed' => 'confirmada',
                    'cancelled' => 'cancelada',
                    'completed' => 'completada',
                    'rejected'  => 'rechazada',
                    default     => $reservation->status,
                };
            } elseif ($reservation->estado && !$reservation->status) {
                $reservation->status = match($reservation->estado) {
                    'pendiente'  => 'pending',
                    'confirmada' => 'confirmed',
                    'cancelada'  => 'cancelled',
                    'completada' => 'completed',
                    'rechazada'  => 'rejected',
                    default      => $reservation->estado,
                };
            }

            if (empty($reservation->estado)) {
                $reservation->estado = 'pendiente';
            }
            if (empty($reservation->status)) {
                $reservation->status = 'pending';
            }

            // Asegurar que el área predeterminada sea Terraza si viene vacía o 'General'
            if (empty($reservation->area) || $reservation->area === 'General') {
                $reservation->area = (!empty($reservation->zona_preferida) && $reservation->zona_preferida !== 'Sin preferencia' && $reservation->zona_preferida !== 'General')
                    ? $reservation->zona_preferida
                    : 'Terraza';
            }
            if (empty($reservation->zona_preferida) || $reservation->zona_preferida === 'Sin preferencia' || $reservation->zona_preferida === 'General') {
                $reservation->zona_preferida = $reservation->area ?: 'Terraza';
            }
            if (empty($reservation->area_id) && class_exists(Area::class)) {
                $terraza = Area::where('name', 'like', "%{$reservation->area}%")
                    ->orWhere('nombre', 'like', "%{$reservation->area}%")
                    ->first();
                if (!$terraza) {
                    $terraza = Area::where('name', 'Terraza')->orWhere('nombre', 'Terraza')->first();
                }
                if ($terraza) {
                    $reservation->area_id = $terraza->id;
                }
            }

            if (empty($reservation->folio)) {
                $reservation->folio = static::generarFolioUnico();
            }
        });

        static::updating(function ($reservation) {
            if ($reservation->isDirty('nombre') && !$reservation->isDirty('customer_name')) {
                $reservation->customer_name = $reservation->nombre;
            } elseif ($reservation->isDirty('customer_name') && !$reservation->isDirty('nombre')) {
                $reservation->nombre = $reservation->customer_name;
            }

            if ($reservation->isDirty('telefono') && !$reservation->isDirty('customer_phone')) {
                $reservation->customer_phone = $reservation->telefono;
            } elseif ($reservation->isDirty('customer_phone') && !$reservation->isDirty('telefono')) {
                $reservation->telefono = $reservation->customer_phone;
            }

            if ($reservation->isDirty('email') && !$reservation->isDirty('customer_email')) {
                $reservation->customer_email = $reservation->email;
            } elseif ($reservation->isDirty('customer_email') && !$reservation->isDirty('email')) {
                $reservation->email = $reservation->customer_email;
            }

            if ($reservation->isDirty('fecha') && !$reservation->isDirty('reservation_date')) {
                $reservation->reservation_date = $reservation->fecha;
            } elseif ($reservation->isDirty('reservation_date') && !$reservation->isDirty('fecha')) {
                $reservation->fecha = $reservation->reservation_date;
            }

            if ($reservation->isDirty('hora') && !$reservation->isDirty('reservation_time')) {
                $reservation->reservation_time = $reservation->hora;
            } elseif ($reservation->isDirty('reservation_time') && !$reservation->isDirty('hora')) {
                $reservation->hora = $reservation->reservation_time;
            }

            if ($reservation->isDirty('personas') && !$reservation->isDirty('guests_count')) {
                $reservation->guests_count = (int) $reservation->personas;
            } elseif ($reservation->isDirty('guests_count') && !$reservation->isDirty('personas')) {
                $reservation->personas = (int) $reservation->guests_count;
            }

            if ($reservation->isDirty('nota_especial') && !$reservation->isDirty('special_requests')) {
                $reservation->special_requests = $reservation->nota_especial;
            } elseif ($reservation->isDirty('special_requests') && !$reservation->isDirty('nota_especial')) {
                $reservation->nota_especial = $reservation->special_requests;
            }

            if ($reservation->isDirty('estado') && !$reservation->isDirty('status')) {
                $reservation->status = match($reservation->estado) {
                    'pendiente'  => 'pending',
                    'confirmada' => 'confirmed',
                    'cancelada'  => 'cancelled',
                    'completada' => 'completed',
                    'rechazada'  => 'rejected',
                    default      => $reservation->estado,
                };
            } elseif ($reservation->isDirty('status') && !$reservation->isDirty('estado')) {
                $reservation->estado = match($reservation->status) {
                    'pending'   => 'pendiente',
                    'confirmed' => 'confirmada',
                    'cancelled' => 'cancelada',
                    'completed' => 'completada',
                    'rejected'  => 'rechazada',
                    default     => $reservation->status,
                };
            }
        });
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function table()
    {
        return $this->belongsTo(Mesa::class, 'table_id');
    }

    /**
     * Generar un folio único basado en la fecha actual sin guiones (RESYYYYMMDDXXXX)
     * utilizando una transacción con bloqueo pesimista (lockForUpdate) en PostgreSQL.
     */
    public static function generarFolioUnico(): string
    {
        return DB::transaction(function () {
            $hoy = Carbon::now();
            // Formato sin guiones: RES20260915 (código sólido, unificado e irrompible)
            $prefijo = 'RES' . $hoy->format('Ymd');

            // lockForUpdate() es CRÍTICO. 
            // Le dice a PostgreSQL que "congele" la lectura de este registro 
            // hasta que terminemos, evitando que dos usuarios obtengan el mismo número.
            $ultimaReserva = static::where(function ($query) use ($hoy, $prefijo) {
                                $query->whereDate('created_at', $hoy->toDateString())
                                      ->orWhere('folio', 'like', $prefijo . '%')
                                      ->orWhere('folio', 'like', 'RES-' . $hoy->format('Ymd') . '%');
                            })
                            ->where(function ($query) use ($hoy, $prefijo) {
                                $query->where('folio', 'like', $prefijo . '%')
                                      ->orWhere('folio', 'like', 'RES-' . $hoy->format('Ymd') . '%');
                            })
                            ->lockForUpdate()
                            ->orderBy('id', 'desc')
                            ->first();

            if (!$ultimaReserva || empty($ultimaReserva->folio)) {
                // Si es la primera reserva del día, empezamos en 1
                $siguienteNumero = 1;
            } else {
                // Si ya hay reservas hoy, extraemos el último número (con o sin guiones) y le sumamos 1
                $folioLimpio = $ultimaReserva->folio;
                if (str_contains($folioLimpio, '-')) {
                    $partes = explode('-', $folioLimpio);
                    $ultimoNumero = (int) end($partes);
                } else {
                    $ultimoNumero = (int) substr($folioLimpio, -4);
                }
                $siguienteNumero = $ultimoNumero > 0 ? $ultimoNumero + 1 : 1;
            }

            // str_pad rellena con ceros a la izquierda (ej. 1 -> 0001)
            return $prefijo . str_pad($siguienteNumero, 4, '0', STR_PAD_LEFT);
        });
    }

    public function getClienteEmailAttribute(): ?string
    {
        return $this->email ?: $this->customer_email;
    }

    public function setClienteEmailAttribute(?string $value): void
    {
        $this->email = $value;
        $this->customer_email = $value;
    }

    public function getClienteNombreAttribute(): ?string
    {
        return $this->nombre ?: $this->customer_name;
    }

    public function setClienteNombreAttribute(?string $value): void
    {
        $this->nombre = $value;
        $this->customer_name = $value;
    }

    public function getFechaReservaAttribute(): ?string
    {
        return $this->fecha ?: $this->reservation_date;
    }

    public function setFechaReservaAttribute(?string $value): void
    {
        $this->fecha = $value;
        $this->reservation_date = $value;
    }

    public function getHoraReservaAttribute(): ?string
    {
        return $this->hora ?: $this->reservation_time;
    }

    public function setHoraReservaAttribute(?string $value): void
    {
        $this->hora = $value;
        $this->reservation_time = $value;
    }

    public function getCantidadPersonasAttribute(): int
    {
        return (int) ($this->personas ?: ($this->guests_count ?: 1));
    }

    public function setCantidadPersonasAttribute($value): void
    {
        $this->personas = (int) $value;
        $this->guests_count = (int) $value;
    }

    public function getNumeroMesaAttribute(): ?string
    {
        if (!empty($this->table_number)) {
            return $this->table_number;
        }

        if (!empty($this->table_id) && class_exists(Mesa::class)) {
            $mesa = Mesa::find($this->table_id);
            if ($mesa) {
                return 'Mesa ' . $mesa->numero_mesa;
            }
        }

        return null;
    }

    public function setNumeroMesaAttribute($value): void
    {
        $this->table_number = is_numeric($value) ? ('Mesa ' . $value) : $value;
    }

    public function getEstatusAttribute(): ?string
    {
        return $this->estado ?: $this->status;
    }

    public function setEstatusAttribute(?string $value): void
    {
        $this->estado = $value ? strtolower($value) : $value;
        if ($value !== null) {
            $valLower = strtolower($value);
            $this->status = match ($valLower) {
                'confirmada', 'confirmado' => 'confirmed',
                'pendiente'                => 'pending',
                'cancelada', 'cancelado'   => 'cancelled',
                'completada', 'completado' => 'completed',
                'rechazada', 'rechazado'   => 'rejected',
                default                    => $valLower,
            };
        }
    }
}
