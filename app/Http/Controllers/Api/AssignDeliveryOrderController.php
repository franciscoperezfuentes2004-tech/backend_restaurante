<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignDeliveryOrderRequest;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\CashCut;
use App\Events\OrderStatusUpdated;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AssignDeliveryOrderController extends Controller
{
    /**
     * Asigna un pedido a domicilio al repartidor autenticado tras superar los
     * filtros estrictos de seguridad, modalidad y máquina de estados con bloqueo en PostgreSQL.
     */
    public function assign(AssignDeliveryOrderRequest $request)
    {
        $user = $request->user();

        // CANDADO POST-CORTE: Si el repartidor tiene un corte de caja pendiente de confirmación,
        // no puede tomar nuevos pedidos hasta que el administrador/encargado confirme su corte.
        $hasPendingCut = CashCut::where('user_id', $user->id)
            ->where('status', 'pendiente')
            ->exists();

        if ($hasPendingCut) {
            return response()->json([
                'message' => 'No puedes tomar nuevos pedidos porque tu turno está cerrado/notificado. Espera a que el encargado confirme tu corte.'
            ], 409);
        }

        $rawToken = $request->input('token') ?? $request->route('token') ?? $request->input('code') ?? $request->input('folio');
        $token = strtoupper(trim(strip_tags((string) $rawToken)));

        // Candado de Seguridad y Concurrencia mediante Transacción con Bloqueo de Fila
        return DB::transaction(function () use ($token, $user) {
            // Búsqueda del pedido por dispatch_token, folio, ID o daily_number con lockForUpdate
            $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
                ->where('dispatch_token', $token)
                ->orWhere('folio', $token)
                ->when(is_numeric($token), function ($q) use ($token) {
                    $q->orWhere('id', (int) $token)
                      ->orWhere('daily_number', (int) $token);
                })
                ->lockForUpdate()
                ->first();

            if (!$order) {
                $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
                    ->whereRaw('UPPER(folio) = ?', [$token])
                    ->orWhereRaw('UPPER(dispatch_token) = ?', [$token])
                    ->lockForUpdate()
                    ->first();
            }

            if (!$order) {
                return response()->json([
                    'message' => 'Folio o token no encontrado.'
                ], 404);
            }

            // FILTRO 1: Filtro de Tipo de Pedido (Modality)
            // Si escanean uno de 'para_llevar' o 'comedor' / 'local', rechazar con 400.
            $modality = strtolower(trim((string) $order->modality));
            if ($modality !== 'delivery') {
                return response()->json([
                    'message' => 'Este código pertenece a un pedido que no es para envío a domicilio.'
                ], 400);
            }

            // FILTRO 2: Filtro de Estado de Cocina (State Machine)
            // El pedido DEBE estar en estado listo (esperando repartidor).
            // Si intentan escanear uno 'pendiente' o 'en_preparacion', rechazar con 400.
            $orderStatus = strtolower(trim((string) $order->status));
            if (in_array($orderStatus, ['pending', 'pendiente', 'preparing', 'en_preparacion'], true)) {
                return response()->json([
                    'message' => 'El pedido aún no está listo en cocina.'
                ], 400);
            }

            if (in_array($orderStatus, ['completed', 'cancelled', 'delivered'], true)) {
                return response()->json([
                    'message' => "La orden #{$order->folio} ya se encuentra finalizada o cancelada."
                ], 400);
            }

            // Obtener o registrar perfil de repartidor para el usuario autenticado
            $driver = DeliveryDriver::where('user_id', $user->id)->first();
            if (!$driver) {
                $driver = DeliveryDriver::where('email', $user->email)->first();
                if ($driver && !$driver->user_id) {
                    $driver->update(['user_id' => $user->id]);
                }
            }

            if (!$driver) {
                try {
                    $driver = DeliveryDriver::create([
                        'user_id'      => $user->id,
                        'name'         => $user->name,
                        'phone'        => $user->phone ?? '0000000000',
                        'email'        => $user->email,
                        'status'       => 'active',
                        'active'       => true,
                        'vehicle_type' => 'motorcycle',
                    ]);
                } catch (\Throwable $e) {
                    Log::warning('DeliveryDriver auto-create fallback: ' . $e->getMessage());
                    $driver = DeliveryDriver::where('user_id', $user->id)->first();
                }
            }

            // FILTRO 3: Filtro de Asignación / Condición de Carrera (Race Condition)
            // Si el pedido ya tiene un driver_id asignado a otro repartidor o está 'en_camino' / 'in_transit' / 'on_the_way',
            // abortar con código 409 Conflict.
            $delivery = Delivery::where('order_id', $order->id)->lockForUpdate()->first();

            $isAlreadyAssignedToOther = $delivery
                && $delivery->driver_id !== null
                && $driver
                && (int) $delivery->driver_id !== (int) $driver->id;

            $isInTransit = in_array($orderStatus, ['on_the_way', 'in_transit', 'en_camino'], true)
                || ($delivery && in_array($delivery->status, ['in_transit', 'delivered'], true));

            if ($isAlreadyAssignedToOther || ($isInTransit && $delivery && (int) $delivery->driver_id !== (int) $driver?->id)) {
                return response()->json([
                    'message' => 'Este pedido ya fue tomado por otro repartidor.'
                ], 409);
            }

            // Actualización atómica de la orden y la entrega
            $order->update([
                'status' => 'on_the_way'
            ]);

            if ($delivery) {
                $delivery->update([
                    'driver_id' => $driver?->id,
                    'status'    => 'in_transit',
                ]);
            } else {
                $delivery = Delivery::create([
                    'order_id'  => $order->id,
                    'driver_id' => $driver?->id,
                    'status'    => 'in_transit',
                ]);
            }

            // Notificación en tiempo real y bitácora
            try {
                NotificationService::create(
                    'delivery_scan',
                    'Pedido en Camino',
                    "El pedido #{$order->folio} fue asignado a " . ($driver?->name ?? 'Repartidor') . " y va en camino",
                    [
                        'order_id'  => $order->id,
                        'folio'     => $order->folio,
                        'driver_id' => $driver?->id,
                        'driver'    => $driver?->name
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('NotificationService error: ' . $e->getMessage());
            }

            try {
                broadcast(new OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra'])));
            } catch (\Throwable $e) {
                Log::warning('Broadcast error: ' . $e->getMessage());
            }

            $itemsFormatted = $order->items->map(function ($item) {
                return [
                    'id'        => $item->id,
                    'dish_name' => $item->dish?->name ?? 'Platillo',
                    'name'      => $item->dish?->name ?? 'Platillo',
                    'quantity'  => (int) $item->quantity,
                    'price'     => (float) $item->price,
                    'notes'     => $item->notes ?? '',
                    'extras'    => $item->extras->map(fn($e) => [
                        'id'    => $e->extra_id,
                        'name'  => $e->extra?->name ?? 'Extra',
                        'price' => (float) $e->price
                    ])->toArray()
                ];
            })->toArray();

            return response()->json([
                'message'          => 'Pedido asignado y puesto en ruta correctamente.',
                'id'               => $order->id,
                'folio'            => $order->folio ?? "#{$order->id}",
                'estado'           => 'en_camino',
                'status'           => 'on_the_way',
                'cliente'          => $order->customer_name ?: 'Cliente general',
                'customer_name'    => $order->customer_name ?: 'Cliente general',
                'telefono'         => $order->customer_phone ?: '',
                'customer_phone'   => $order->customer_phone ?: '',
                'direccion'        => $order->customer_address ?: '',
                'customer_address' => $order->customer_address ?: '',
                'referencias'      => $order->notes ?: '',
                'notes'            => $order->notes ?: '',
                'total'            => (float) $order->total_amount,
                'total_amount'     => (float) $order->total_amount,
                'payment_method'   => $order->payment_method ?: 'cash',
                'metodoPago'       => $order->payment_method ?: 'cash',
                'payment_status'   => $order->payment_status ?: 'pending',
                'asignadoHace'     => 0,
                'coordenadas'      => ['lat' => 16.85, 'lng' => -99.82],
                'platillos'        => $itemsFormatted,
                'items'            => $itemsFormatted,
                'driver'           => $driver ? [
                    'id'    => $driver->id,
                    'name'  => $driver->name,
                    'phone' => $driver->phone,
                ] : null,
                'created_at'       => $order->created_at?->format('Y-m-d H:i:s'),
            ], 200);
        });
    }
}
