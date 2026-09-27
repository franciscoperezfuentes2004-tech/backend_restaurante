<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Models\Order;
use App\Models\Mesa;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\InventoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateOrderStatusController extends Controller
{
    /**
     * Invocación directa del controlador.
     */
    public function __invoke(UpdateOrderStatusRequest $request, $orderParam = null): JsonResponse
    {
        return $this->update($request, $orderParam);
    }

    /**
     * Actualiza el estado de un pedido garantizando idempotencia, concurrencia estricta (PostgreSQL) y máquina de estados flexible.
     */
    public function update(UpdateOrderStatusRequest $request, $orderParam = null): JsonResponse
    {
        $orderId = (int) ($request->input('order_id'));
        $targetStatus = (string) ($request->input('status')); // 'pendiente', 'en_preparacion', 'listo', 'entregado', 'cancelado'

        // Transacción con bloqueo pesimista en PostgreSQL para mitigar condiciones de carrera (Race Conditions)
        return DB::transaction(function () use ($orderId, $targetStatus, $request) {
            // PostgreSQL bloquea la fila exclusivamente durante la transacción
            $order = Order::where('id', $orderId)->lockForUpdate()->first();

            if (!$order) {
                return response()->json(['message' => 'Pedido no encontrado.'], 404);
            }

            $currentStatus = strtolower((string) $order->status);
            $modality = strtolower((string) ($order->modality ?? 'local'));
            $isLocal = in_array($modality, ['local', 'mesa', 'comedor', 'dine_in']);

            // 1. IDEMPOTENCIA Y NORMALIZACIÓN DE ESTADO OBJETIVO
            // Mapeamos el estado actual y el objetivo a representaciones estandarizadas en DB:
            // 'pending', 'preparing', 'ready', 'completed', 'delivered', 'cancelled'
            
            $dbStatus = null;
            $alreadyMatches = false;

            if ($targetStatus === 'en_preparacion') {
                $dbStatus = 'preparing';
                // Si ya está en preparación, responde 200 OK (idempotente)
                if (in_array($currentStatus, ['preparing', 'en_preparacion'])) {
                    $alreadyMatches = true;
                }
            } elseif ($targetStatus === 'listo') {
                $dbStatus = $isLocal ? 'completed' : 'ready';
                // Si el pedido ya está en listo/ready, completado/completed, terminado o entregado: responde 200 OK (idempotente)
                if (in_array($currentStatus, ['ready', 'completed', 'listo', 'completado', 'terminado', 'delivered', 'entregado'])) {
                    $alreadyMatches = true;
                }
            } elseif ($targetStatus === 'pendiente') {
                $dbStatus = 'pending';
                if (in_array($currentStatus, ['pending', 'pendiente'])) {
                    $alreadyMatches = true;
                }
            } elseif ($targetStatus === 'entregado') {
                $dbStatus = $isLocal ? 'completed' : 'delivered';
                if (in_array($currentStatus, ['delivered', 'completed', 'entregado', 'completado'])) {
                    $alreadyMatches = true;
                }
            } elseif ($targetStatus === 'cancelado') {
                $dbStatus = 'cancelled';
                if (in_array($currentStatus, ['cancelled', 'cancelado'])) {
                    $alreadyMatches = true;
                }
            } else {
                $dbStatus = $targetStatus;
            }

            // IDEMPOTENCIA: Si el pedido ya tiene el estado solicitado (o ya fue terminado previamente),
            // respondemos con 200 OK inmediatamente sin arrojar 409 Conflict.
            if ($alreadyMatches) {
                return response()->json([
                    'message'        => 'El pedido ya se encuentra en el estado solicitado.',
                    'order'          => $order->fresh()->load(['items.dish', 'items.extras.extra', 'user']),
                    'status'         => $order->status,
                    'current_status' => $order->status,
                ], 200);
            }

            // Si el pedido fue cancelado y se intenta mover a otro estado (que no sea cancelado):
            if (in_array($currentStatus, ['cancelled', 'cancelado']) && $targetStatus !== 'cancelado') {
                return response()->json([
                    'message'        => 'El pedido está cancelado y no puede cambiar de estado.',
                    'current_status' => $order->status,
                ], 409);
            }

            $previousStatus = $order->status;
            $order->status = $dbStatus;
            $order->save();

            // 2. DESCUENTO AUTOMÁTICO DE INVENTARIO
            // Al pasar de pendiente a preparación o listo directamente
            if (in_array($previousStatus, ['pending', 'pendiente']) && in_array($dbStatus, ['preparing', 'in_transit', 'en_ruta', 'ready', 'completed', 'delivered'])) {
                try {
                    InventoryService::descontarInventario($order->id);
                } catch (\Throwable $e) {
                    Log::warning('InventoryService deduction error: ' . $e->getMessage());
                }
            }

            // 3. LIBERACIÓN AUTOMÁTICA DE MESA
            if (in_array($dbStatus, ['completed', 'cancelled', 'delivered'])) {
                $mesa = null;
                if ($order->table_id) {
                    $mesa = Mesa::find($order->table_id);
                } elseif ($order->table_number) {
                    $mesa = Mesa::where('numero_mesa', (int) $order->table_number)->first();
                }

                if ($mesa) {
                    $hasOtherActive = Order::where(function ($q) use ($mesa) {
                        $q->where('table_id', $mesa->id)
                          ->orWhere('table_number', (string) $mesa->numero_mesa);
                    })
                    ->where('id', '!=', $order->id)
                    ->whereIn('status', ['pending', 'preparing', 'ready', 'open', 'abierto', 'en_preparacion'])
                    ->exists();

                    if (!$hasOtherActive) {
                        $mesa->update(['status' => 'libre']);
                    }
                }
            }

            // 4. AUDITORÍA DEL SISTEMA
            try {
                $user = $request->user();
                $userName = $user ? $user->name : 'Sistema';
                AuditLogger::log(
                    'ORDER_STATUS_UPDATED',
                    'orders',
                    "Estado del pedido #{$order->folio} actualizado de '{$previousStatus}' a '{$order->status}' por {$userName}",
                    $order,
                    'info'
                );
            } catch (\Throwable $e) {
                Log::warning('AuditLogger error: ' . $e->getMessage());
            }

            // 5. NOTIFICACIONES INTERNAS
            $typeMap = [
                'cancelled'  => 'pedido_cancelado',
                'completed'  => 'pedido_completado',
                'preparing'  => 'pedido_actualizado',
                'pending'    => 'pedido_actualizado',
                'ready'      => 'pedido_listo',
                'on_the_way' => 'delivery_scan',
                'delivered'  => 'pedido_entregado',
            ];
            $titleMap = [
                'cancelled'  => 'Pedido Cancelado',
                'completed'  => 'Pedido Completado',
                'preparing'  => 'Pedido en Preparación',
                'pending'    => 'Pedido Pendiente',
                'ready'      => 'Pedido Listo para Entrega',
                'on_the_way' => 'Pedido en Camino',
                'delivered'  => 'Pedido Entregado',
            ];
            $type = $typeMap[$order->status] ?? 'pedido_actualizado';
            $title = $titleMap[$order->status] ?? 'Pedido Actualizado';
            $msg = "El pedido #{$order->folio} cambió a estado: {$title}";

            try {
                NotificationService::create($type, $title, $msg, [
                    'order_id' => $order->id,
                    'status'   => $order->status,
                    'folio'    => $order->folio
                ]);
            } catch (\Throwable $e) {
                Log::warning('NotificationService error: ' . $e->getMessage());
            }

            // 6. DIFUSIÓN POR WEBSOCKET (REAL-TIME KDS / COCINA / PANEL)
            try {
                broadcast(new OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra'])));
            } catch (\Throwable $e) {
                Log::warning('Broadcast error: ' . $e->getMessage());
            }

            return response()->json([
                'message'        => 'Estado del pedido actualizado exitosamente.',
                'order'          => $order->fresh()->load(['items.dish', 'items.extras.extra', 'user']),
                'status'         => $order->status,
                'current_status' => $order->status,
            ], 200);
        });
    }
}
