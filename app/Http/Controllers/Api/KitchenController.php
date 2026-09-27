<?php

namespace App\Http\Controllers\Api;

use App\Events\OrderStatusUpdated;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Mesa;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class KitchenController extends Controller
{
    /**
     * Devuelve las comandas activas para la cocina / KDS.
     */
    public function orders(Request $request): JsonResponse
    {
        return app(OrderController::class)->kitchenOrders($request);
    }

    /**
     * Fuerza la actualización del estado de la orden en la base de datos (PostgreSQL)
     * asegurando respuesta 200 OK sin excepciones 409 Conflict.
     */
    public function updateStatus(Request $request, $id = null): JsonResponse
    {
        $request->validate([
            'status' => 'required|string'
        ]);

        try {
            $routeParam = $id ?? $request->route('order') ?? $request->route('id') ?? $request->input('order_id');
            $cleanId = $routeParam instanceof Order ? $routeParam->id : $routeParam;
            
            $pedido = Order::find($cleanId);

            if (!$pedido && is_numeric($cleanId)) {
                $pedido = Order::where('id', (int) $cleanId)->first();
            }

            if (!$pedido) {
                $pedido = Order::where('folio', (string) $cleanId)->first();
            }

            if (!$pedido) {
                return response()->json(['message' => 'Pedido no encontrado'], 404);
            }

            $rawStatus = (string) $request->input('status');
            $normalized = strtolower(trim(strip_tags($rawStatus)));

            $statusAliases = [
                'pending'          => 'pending',
                'pendiente'        => 'pending',
                'preparing'        => 'preparing',
                'en_preparacion'   => 'preparing',
                'en_cocina'        => 'preparing',
                'preparando'       => 'preparing',
                'en preparacion'   => 'preparing',
                'en preparación'   => 'preparing',
                'ready'            => 'ready',
                'listo'            => 'ready',
                'terminado'        => 'completed',
                'terminada'        => 'completed',
                'completado'       => 'completed',
                'completada'       => 'completed',
                'completed'        => 'completed',
                'finalizado'       => 'completed',
                'finalizada'       => 'completed',
                'servido'          => 'completed',
                'servida'          => 'completed',
                'entregado'        => 'delivered',
                'delivered'        => 'delivered',
                'cancelado'        => 'cancelled',
                'cancelled'        => 'cancelled',
            ];

            $modality = strtolower((string) ($pedido->modality ?? 'local'));
            $isLocal = in_array($modality, ['local', 'mesa', 'comedor', 'dine_in']);

            if (isset($statusAliases[$normalized])) {
                $dbStatus = $statusAliases[$normalized];
                if ($dbStatus === 'ready' && $isLocal) {
                    $dbStatus = 'completed';
                }
            } else {
                $dbStatus = $rawStatus;
            }

            $previousStatus = $pedido->status;
            $pedido->status = $dbStatus;
            $pedido->save();

            // Descuento de inventario automático
            if (in_array($previousStatus, ['pending', 'pendiente']) && in_array($dbStatus, ['preparing', 'in_transit', 'en_ruta', 'ready', 'completed', 'delivered'])) {
                try {
                    InventoryService::descontarInventario($pedido->id);
                } catch (\Throwable $e) {
                    Log::warning('InventoryService deduction error: ' . $e->getMessage());
                }
            }

            // Liberación de mesa si finalizó
            if (in_array($dbStatus, ['completed', 'cancelled', 'delivered'])) {
                try {
                    $mesa = null;
                    if ($pedido->table_id) {
                        $mesa = Mesa::find($pedido->table_id);
                    } elseif ($pedido->table_number) {
                        $mesa = Mesa::where('numero_mesa', (int) $pedido->table_number)->first();
                    }

                    if ($mesa) {
                        $hasOtherActive = Order::where(function ($q) use ($mesa) {
                            $q->where('table_id', $mesa->id)
                              ->orWhere('table_number', (string) $mesa->numero_mesa);
                        })
                        ->where('id', '!=', $pedido->id)
                        ->whereIn('status', ['pending', 'preparing', 'ready', 'open', 'abierto', 'en_preparacion'])
                        ->exists();

                        if (!$hasOtherActive) {
                            $mesa->update(['status' => 'libre']);
                        }
                    }
                } catch (\Throwable $e) {
                    Log::warning('Mesa release error: ' . $e->getMessage());
                }
            }

            // Notificaciones y WebSockets
            try {
                broadcast(new OrderStatusUpdated($pedido->fresh()->load(['items.dish', 'items.extras.extra'])));
            } catch (\Throwable $e) {
                Log::warning('Broadcast error: ' . $e->getMessage());
            }

            return response()->json($pedido->fresh()->load(['items.dish', 'items.extras.extra', 'user']), 200);

        } catch (\Exception $e) {
            Log::error("Error al actualizar pedido KDS: " . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }
}
