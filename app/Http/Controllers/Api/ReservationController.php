<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReservationRequest;
use App\Http\Requests\UpdateReservationRequest;
use App\Models\Reservation;
use App\Models\Mesa;
use App\Services\NotificationService;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ReservationController extends Controller
{
    private function formatReservation(Reservation $res): array
    {
        $res->loadMissing('area');

        return [
            'id'               => $res->id,
            'folio'            => $res->folio ?: ('RES-' . str_pad($res->id, 4, '0', STR_PAD_LEFT)),
            'nombre'           => $res->nombre ?: ($res->customer_name ?? ''),
            'customer_name'    => $res->customer_name ?: ($res->nombre ?? ''),
            'client_name'      => $res->customer_name ?: ($res->nombre ?? ''),
            'telefono'         => $res->telefono ?: ($res->customer_phone ?? ''),
            'customer_phone'   => $res->customer_phone ?: ($res->telefono ?? ''),
            'phone'            => $res->customer_phone ?: ($res->telefono ?? ''),
            'email'            => $res->email ?: $res->customer_email,
            'customer_email'   => $res->customer_email ?: $res->email,
            'personas'         => (int) ($res->personas ?: $res->guests_count),
            'guests_count'     => (int) ($res->guests_count ?: $res->personas),
            'people_count'     => (int) ($res->guests_count ?: $res->personas),
            'fecha'            => $res->fecha ?: ($res->reservation_date ? date('Y-m-d', strtotime($res->reservation_date)) : date('Y-m-d')),
            'reservation_date' => $res->reservation_date ? date('Y-m-d', strtotime($res->reservation_date)) : ($res->fecha ?: date('Y-m-d')),
            'date'             => $res->reservation_date ? date('Y-m-d', strtotime($res->reservation_date)) : ($res->fecha ?: date('Y-m-d')),
            'hora'             => $res->hora ?: ($res->reservation_time ? date('H:i', strtotime($res->reservation_time)) : '20:00'),
            'reservation_time' => $res->reservation_time ? date('H:i', strtotime($res->reservation_time)) : ($res->hora ?: '20:00'),
            'time'             => $res->reservation_time ? date('H:i', strtotime($res->reservation_time)) : ($res->hora ?: '20:00'),
            'zona_preferida'   => $res->zona_preferida,
            'ocasion_especial' => $res->ocasion_especial,
            'nota_especial'    => $res->nota_especial ?: $res->special_requests,
            'special_requests' => $res->special_requests ?: $res->nota_especial,
            'notes'            => $res->special_requests ?: $res->nota_especial,
            'estado'           => $res->estado ?: 'pendiente',
            'status'           => $res->status ?? 'pending',
            'area_id'          => $res->area_id,
            'area_name'        => ($res->relationLoaded('area') && is_object($res->getRelation('area'))) ? $res->getRelation('area')->name : (is_string($res->area) && !empty($res->area) && $res->area !== 'General' ? $res->area : ($res->zona_preferida && $res->zona_preferida !== 'General' && $res->zona_preferida !== 'Sin preferencia' ? $res->zona_preferida : 'Terraza')),
            'area'             => ($res->relationLoaded('area') && is_object($res->getRelation('area'))) ? $res->getRelation('area')->name : (is_string($res->area) && !empty($res->area) && $res->area !== 'General' ? $res->area : ($res->zona_preferida && $res->zona_preferida !== 'General' && $res->zona_preferida !== 'Sin preferencia' ? $res->zona_preferida : 'Terraza')),
            'table_id'         => $res->table_id,
            'table_number'     => $res->table_number,
        ];
    }

    public function index(Request $request)
    {
        $query = Reservation::with('area');

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('folio', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        if ($request->filled('estado') && !in_array($request->estado, ['all', 'todas', 'todos'], true)) {
            $query->where('status', $request->estado);
        }

        $fechaFilter = $request->input('fecha') ?? $request->input('date');
        if (!empty($fechaFilter)) {
            $query->where(function ($q) use ($fechaFilter) {
                $q->whereDate('reservation_date', $fechaFilter)
                  ->orWhereDate('fecha', $fechaFilter);
            });
        }

        $reservations = $query->latest('id')->get();
        $mappedReservations = $reservations->map(fn($r) => $this->formatReservation($r));

        // Resumen for today's date
        $todayStr = date('Y-m-d');
        $todayReservations = Reservation::whereDate('reservation_date', $todayStr)->get();

        $reservasHoy = $todayReservations->count();
        $personasHoy = (int) $todayReservations->sum('guests_count');
        $pendientesHoy = $todayReservations->where('status', 'pending')->count();

        // Conteos por estado across all reservations in database
        $allStatusCounts = Reservation::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        $totalTodas = (int) Reservation::count();

        $conteosPorEstado = [
            'todas'     => $totalTodas,
            'pending'   => (int) ($allStatusCounts['pending'] ?? 0),
            'confirmed' => (int) ($allStatusCounts['confirmed'] ?? 0),
            'rejected'  => (int) ($allStatusCounts['rejected'] ?? 0),
            'cancelled' => (int) ($allStatusCounts['cancelled'] ?? 0),
            'completed' => (int) ($allStatusCounts['completed'] ?? 0),
            'no_show'   => (int) ($allStatusCounts['no_show'] ?? 0),
        ];

        return response()->json([
            'reservaciones'      => $mappedReservations,
            'resumen'            => [
                'reservas_hoy'   => $reservasHoy,
                'personas_hoy'   => $personasHoy,
                'pendientes_hoy' => $pendientesHoy,
            ],
            'conteos_por_estado' => $conteosPorEstado,
        ]);
    }

    public function store(StoreReservationRequest $request)
    {
        $data = $request->validated();

        $tableId = isset($data['table_id']) && !empty($data['table_id']) ? (int) $data['table_id'] : null;
        $areaId  = isset($data['area_id']) && !empty($data['area_id']) ? (int) $data['area_id'] : null;
        $date    = $data['fecha'] ?? $data['date'] ?? null;
        $time    = $data['hora'] ?? $data['time'] ?? null;

        $mesa = null;
        if ($tableId) {
            $mesa = Mesa::find($tableId);
            // Seguridad de Negocio 1: si se indica área, confirmar que la mesa pertenece al área
            if ($mesa && $areaId && (int) $mesa->area_id !== $areaId) {
                return response()->json([
                    'message' => 'La mesa seleccionada no pertenece al área indicada.',
                    'errors'  => [
                        'table_id' => ['La mesa seleccionada no pertenece al área indicada.'],
                    ],
                ], 422);
            }

            // Seguridad de Negocio 2: si se especifica mesa, confirmar conflicto horario
            if ($mesa && $date && $time) {
                $timeObj = Carbon::createFromFormat('H:i', substr($time, 0, 5));
                $windowStart = $timeObj->copy()->subHours(1)->format('H:i:s');
                $windowEnd   = $timeObj->copy()->addHours(1)->format('H:i:s');

                $hasConflict = Reservation::whereDate('reservation_date', $date)
                    ->where(function ($tq) use ($mesa) {
                        $tq->where('table_id', $mesa->id)
                           ->orWhere('table_number', (string) $mesa->numero_mesa)
                           ->orWhere('table_number', 'Mesa ' . $mesa->numero_mesa);
                    })
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($tmeQ) use ($time, $windowStart, $windowEnd) {
                        $tmeQ->where('reservation_time', $time)
                             ->orWhereBetween('reservation_time', [$windowStart, $windowEnd]);
                    })
                    ->exists();

                if ($hasConflict) {
                    return response()->json([
                        'message' => 'La mesa seleccionada ya tiene otra reservación activa en ese mismo rango de fecha y hora.',
                        'errors'  => [
                            'table_id' => ['La mesa seleccionada ya tiene otra reservación activa en ese mismo rango de fecha y hora.'],
                        ],
                    ], 422);
                }
            }
        }

        // Sanitización XSS de notas
        $notes = $data['nota_especial'] ?? $data['notes'] ?? null;
        if ($notes !== null) {
            $notes = trim(strip_tags($notes));
        }

        // FORZAR EL ÁREA:
        // Opción A: Si envían área la toma, de lo contrario por defecto 'Terraza'
        $areaName = $request->input('area', $data['area'] ?? ($data['zona_preferida'] ?? ($data['zona'] ?? 'Terraza')));
        if (empty($areaName) || $areaName === 'Sin preferencia' || $areaName === 'General') {
            $areaName = 'Terraza';
        }

        if (!$areaId && class_exists(Area::class)) {
            $terrazaModel = Area::where('name', 'like', "%{$areaName}%")
                ->orWhere('nombre', 'like', "%{$areaName}%")
                ->first();
            if (!$terrazaModel) {
                $terrazaModel = Area::where('name', 'Terraza')->orWhere('nombre', 'Terraza')->first();
            }
            $areaId = $terrazaModel?->id;
        }

        $reservationData = [
            'folio'            => Reservation::generarFolioUnico(),
            'area'             => $areaName,
            'area_id'          => $areaId,
            'nombre'           => $data['nombre'] ?? $data['client_name'] ?? $data['customer_name'] ?? '',
            'telefono'         => $data['telefono'] ?? $data['phone'] ?? $data['customer_phone'] ?? '',
            'email'            => $data['email'] ?? $data['customer_email'] ?? null,
            'fecha'            => $date,
            'hora'             => $time,
            'personas'         => (int) ($data['personas'] ?? $data['people_count'] ?? $data['guests_count'] ?? 1),
            'zona_preferida'   => $areaName,
            'ocasion_especial' => $data['ocasion_especial'] ?? $data['ocasion'] ?? null,
            'nota_especial'    => $notes,
            'estado'           => $data['estado'] ?? 'pendiente',
            'table_id'         => $mesa?->id,
            'table_number'     => $mesa ? ('Mesa ' . $mesa->numero_mesa) : null,
            'status'           => $data['status'] ?? 'pending',
        ];

        $reservation = Reservation::create($reservationData);

        // Notificación en Tiempo Real (WebSockets Reverb/Echo)
        try {
            event(new \App\Events\NewReservationReceived($reservation));
        } catch (\Throwable $e) {
            \Log::error('Error emitiendo WebSocket NewReservationReceived: ' . $e->getMessage());
        }

        NotificationService::create(
            'nueva_reservacion',
            'Nueva Reservación Recibida',
            "Se recibió una reservación #{$reservation->folio} para {$reservation->nombre}",
            ['reservation_id' => $reservation->id]
        );

        $formatted = $this->formatReservation($reservation);

        return response()->json(array_merge($formatted, [
            'message'     => 'Reserva solicitada con éxito',
            'mensaje'     => 'Reserva creada con éxito',
            'folio'       => $reservation->folio,
            'reservation' => $formatted,
        ]), 201);
    }

    public function show($id)
    {
        $reservation = Reservation::findOrFail($id);
        return response()->json($this->formatReservation($reservation));
    }

    public function update(UpdateReservationRequest $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $data = $request->validated();

        $tableId = isset($data['table_id']) ? (int) $data['table_id'] : $reservation->table_id;
        $areaId  = isset($data['area_id']) ? (int) $data['area_id'] : $reservation->area_id;
        $date    = $data['date'] ?? $reservation->reservation_date;
        $time    = $data['time'] ?? $reservation->reservation_time;

        if ($tableId && $areaId) {
            $mesa = Mesa::find($tableId);
            if (!$mesa || (int) $mesa->area_id !== (int) $areaId) {
                return response()->json([
                    'message' => 'La mesa seleccionada no pertenece al área indicada.',
                    'errors'  => [
                        'table_id' => ['La mesa seleccionada no pertenece al área indicada.'],
                    ],
                ], 422);
            }

            if ($date && $time) {
                $timeObj = Carbon::createFromFormat('H:i', substr($time, 0, 5));
                $windowStart = $timeObj->copy()->subHours(1)->format('H:i:s');
                $windowEnd   = $timeObj->copy()->addHours(1)->format('H:i:s');

                $hasConflict = Reservation::where('id', '!=', $reservation->id)
                    ->whereDate('reservation_date', $date)
                    ->where(function ($tq) use ($mesa) {
                        $tq->where('table_id', $mesa->id)
                           ->orWhere('table_number', (string) $mesa->numero_mesa)
                           ->orWhere('table_number', 'Mesa ' . $mesa->numero_mesa);
                    })
                    ->whereIn('status', ['pending', 'confirmed'])
                    ->where(function ($tmeQ) use ($time, $windowStart, $windowEnd) {
                        $tmeQ->where('reservation_time', $time)
                             ->orWhereBetween('reservation_time', [$windowStart, $windowEnd]);
                    })
                    ->exists();

                if ($hasConflict) {
                    return response()->json([
                        'message' => 'La mesa seleccionada ya tiene otra reservación activa en ese mismo rango de fecha y hora.',
                        'errors'  => [
                            'table_id' => ['La mesa seleccionada ya tiene otra reservación activa en ese mismo rango de fecha y hora.'],
                        ],
                    ], 422);
                }
            }
        }

        $updateData = [];
        if (isset($data['client_name']))   $updateData['customer_name'] = $data['client_name'];
        if (isset($data['phone']))         $updateData['customer_phone'] = $data['phone'];
        if (array_key_exists('email', $data)) $updateData['customer_email'] = $data['email'];
        if (isset($data['people_count']))  $updateData['guests_count'] = (int) $data['people_count'];
        if (isset($data['date']))          $updateData['reservation_date'] = $data['date'];
        if (isset($data['time']))          $updateData['reservation_time'] = $data['time'];
        if (isset($data['area_id']))       $updateData['area_id'] = (int) $data['area_id'];
        if (isset($mesa)) {
            $updateData['table_id']     = $mesa->id;
            $updateData['table_number'] = 'Mesa ' . $mesa->numero_mesa;
        }
        if (isset($data['status']))        $updateData['status'] = $data['status'];
        if (array_key_exists('notes', $data)) $updateData['special_requests'] = $data['notes'] ? trim(strip_tags($data['notes'])) : null;

        $reservation->update($updateData);

        if ($reservation->status === 'confirmed') {
            $this->dispararWebhookConfirmacion($reservation);
        }

        NotificationService::create(
            'reservacion_actualizada',
            'Reservación Actualizada',
            "La reservación #{$reservation->folio} fue actualizada",
            ['reservation_id' => $reservation->id]
        );

        return response()->json($this->formatReservation($reservation));
    }

    public function updateStatus(Request $request, $id)
    {
        $reservation = Reservation::findOrFail($id);

        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,rejected,cancelled,completed,no_show',
        ], [
            'status.required' => 'El estado es obligatorio.',
            'status.in'       => 'El estado seleccionado no es válido.',
        ]);

        $reservation->update(['status' => $data['status']]);

        if ($reservation->status === 'confirmed') {
            $this->dispararWebhookConfirmacion($reservation);
        }

        $statusLabel = [
            'confirmed' => 'Confirmada',
            'cancelled' => 'Cancelada',
            'completed' => 'Completada',
            'no_show'   => 'No Asistió',
            'rejected'  => 'Rechazada',
            'pending'   => 'Pendiente',
        ][$reservation->status] ?? ucfirst($reservation->status);

        $type = $reservation->status === 'confirmed' ? 'reservacion_confirmada' : ($reservation->status === 'cancelled' ? 'reservacion_cancelada' : 'reservacion_actualizada');

        NotificationService::create(
            $type,
            "Reservación {$statusLabel}",
            "La reservación #{$reservation->folio} ha sido {$statusLabel}",
            ['reservation_id' => $reservation->id, 'status' => $reservation->status]
        );

        return response()->json($this->formatReservation($reservation));
    }

    public function destroy($id)
    {
        $reservation = Reservation::findOrFail($id);
        $folio = $reservation->folio;
        $reservation->delete();

        NotificationService::create(
            'reservacion_eliminada',
            'Reservación Eliminada',
            "La reservación #{$folio} fue eliminada",
            ['reservation_id' => $id]
        );

        return response()->json(['message' => 'Reservación eliminada correctamente']);
    }

    public function asignarMesa(Request $request, $id) 
    {
        // 1. Encuentras la reservación en PostgreSQL y la actualizas
        $reservacion = Reservation::findOrFail($id);
        $reservacion->estatus = 'Confirmada';
        $reservacion->status  = 'confirmed';

        if ($request->filled('numero_mesa') || $request->filled('mesa')) {
            $reservacion->numero_mesa = $request->numero_mesa ?? $request->mesa;
        }

        if ($request->filled('table_id')) {
            $reservacion->table_id = $request->table_id;
            $mesaObj = Mesa::find($request->table_id);
            if ($mesaObj) {
                $reservacion->table_number = 'Mesa ' . $mesaObj->numero_mesa;
            }
        }

        $reservacion->save();

        // 2. ¡AQUÍ ENTRA LA MAGIA DE n8n! 
        // Disparamos el webhook enviando los datos reales de la base de datos
        // (Asegúrate de usar tu Production URL de n8n aquí)
        $this->dispararWebhookConfirmacion($reservacion);

        // 3. Devuelves al gerente a la pantalla con un mensaje de éxito
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'      => 'success',
                'message'     => 'Mesa asignada y correo de confirmación enviado al cliente.',
                'reservation' => $this->formatReservation($reservacion),
            ]);
        }

        return redirect()->back()->with('success', 'Mesa asignada y correo de confirmación enviado al cliente.');
    }

    public function dispararWebhookConfirmacion(Reservation $reservacion): void
    {
        try {
            $setting = RestaurantSetting::first();
            $sucursal = $setting?->restaurant_name ?? 'Sucursal Centro';
            $activePlatform = $setting?->active_notification_platform ?? 'none';
            $discordWebhook = $setting?->discord_webhook_url;
            $telegramBotToken = $setting?->telegram_bot_token;
            $telegramChatId = $setting?->telegram_chat_id;

            $fechaFormateada = $reservacion->fecha
                ? Carbon::parse($reservacion->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
                : ($reservacion->reservation_date ? Carbon::parse($reservacion->reservation_date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : date('d/m/Y'));

            $horaFormateada = $reservacion->hora
                ? Carbon::parse($reservacion->hora)->format('h:i A')
                : ($reservacion->reservation_time ? Carbon::parse($reservacion->reservation_time)->format('h:i A') : '08:00 PM');

            $mesa = $reservacion->numero_mesa 
                ?: ($reservacion->table_number ?: 'Mesa asignada');

            // Leemos la URL base de n8n desde el archivo .env
            // Si no existe, usamos localhost por defecto
            $n8nBaseUrl = rtrim(env('N8N_URL', 'http://localhost:5678'), '/');

            // Concatenamos la URL base con el path del webhook
            Http::post($n8nBaseUrl . '/webhook/nueva-reservacion', [
                'cliente_email'     => $reservacion->cliente_email ?? $reservacion->email ?? $reservacion->customer_email,
                'cliente_nombre'    => $reservacion->cliente_nombre ?? $reservacion->nombre ?? $reservacion->customer_name,
                'folio'             => $reservacion->folio ?: ('RES-' . str_pad($reservacion->id, 4, '0', STR_PAD_LEFT)),
                'fecha'             => $fechaFormateada,
                'fecha_reserva'     => $reservacion->fecha_reserva,
                'hora'              => $horaFormateada,
                'hora_reserva'      => $reservacion->hora_reserva,
                'personas'          => (int) $reservacion->cantidad_personas,
                'cantidad_personas' => (int) $reservacion->cantidad_personas,
                'sucursal'          => $sucursal,
                'mesa'              => $mesa ?? 'Mesa asignada',
                'numero_mesa'       => $mesa ?? 'Mesa asignada',
                'active_notification_platform' => $activePlatform,
                'discord_webhook_url'          => $discordWebhook,
                'telegram_bot_token'           => $telegramBotToken,
                'telegram_chat_id'             => $telegramChatId,
                'notification_settings'        => [
                    'platform'            => $activePlatform,
                    'discord_webhook_url' => $discordWebhook,
                    'telegram_bot_token'  => $telegramBotToken,
                    'telegram_chat_id'    => $telegramChatId,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al disparar webhook de reservación n8n: ' . $e->getMessage());
        }
    }
}
