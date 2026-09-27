<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddDishToOrderRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemExtra;
use App\Models\Dish;
use App\Models\Extra;
use App\Services\AuditLogger;
use App\Events\OrderStatusUpdated;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AddDishToOrderController extends Controller
{
    /**
     * Agrega un platillo con sus extras autorizados a una comanda u orden activa.
     * 
     * Implementa:
     * 1. Zero-Trust Financiero: Ignora precios y subtotales enviados por el frontend, calculándolos exclusivamente en base de datos.
     * 2. Validación de Pertenencia de Extras: Asegurada en AddDishToOrderRequest mediante Rule::exists('dish_extra', ...).
     * 3. Sanitización de Notas: Asegurada con strip_tags() en AddDishToOrderRequest.
     * 4. Bloqueo Pesimista y Máquina de Estados: lockForUpdate() y rechazo 409 si la comanda ya está cerrada.
     */
    public function add(AddDishToOrderRequest $request)
    {
        return DB::transaction(function () use ($request) {
            // 1. Bloqueo pesimista de la comanda
            $order = Order::where('id', $request->order_id)
                ->lockForUpdate()
                ->firstOrFail();

            // 2. Validación de Máquina de Estados: No se pueden agregar ítems a órdenes cerradas
            if (in_array($order->status, ['completed', 'delivered', 'cancelled'], true)) {
                return response()->json([
                    'message' => 'No se pueden agregar platillos a una comanda completada, entregada o cancelada.'
                ], 409);
            }

            // 3. Obtener Platillo con precio real de la base de datos (Zero-Trust)
            $dish = Dish::findOrFail($request->dish_id);
            $unitPrice = (float) $dish->price;
            $quantity = (int) $request->quantity;

            // 4. Obtener Extras autorizados y sus precios reales desde PostgreSQL (Zero-Trust)
            $extraIds = $request->input('extras', []);
            $extras = !empty($extraIds)
                ? Extra::whereIn('id', $extraIds)->get()
                : collect();

            $extrasUnitPrice = 0.0;
            foreach ($extras as $extra) {
                $extrasUnitPrice += (float) $extra->price;
            }

            // Cálculo matemático puro en el servidor
            $itemSubtotal = ($unitPrice + $extrasUnitPrice) * $quantity;

            // 5. Crear la partida en order_items
            $orderItem = OrderItem::create([
                'order_id' => $order->id,
                'dish_id'  => $dish->id,
                'quantity' => $quantity,
                'price'    => $unitPrice,
                'notes'    => $request->notes,
            ]);

            // 6. Registrar los extras en order_item_extras
            $savedExtras = [];
            foreach ($extras as $extra) {
                $itemExtra = OrderItemExtra::create([
                    'order_item_id' => $orderItem->id,
                    'extra_id'      => $extra->id,
                    'price'         => (float) $extra->price,
                ]);

                $savedExtras[] = [
                    'id'       => $extra->id,
                    'name'     => $extra->name,
                    'price'    => (float) $extra->price,
                    'is_free'  => (bool) $extra->is_free,
                ];
            }

            // 7. Recálculo estricto del total acumulado de la orden en la base de datos
            $order->load(['items.extras']);
            $recalculatedTotal = 0.0;

            foreach ($order->items as $item) {
                $baseItemPrice = (float) $item->price;
                $extrasTotalForItem = (float) $item->extras->sum('price');
                $recalculatedTotal += ($baseItemPrice + $extrasTotalForItem) * (int) $item->quantity;
            }

            $order->update([
                'total_amount' => $recalculatedTotal,
            ]);

            // 8. Auditoría y difusión WebSocket en tiempo real para KDS y meseros
            try {
                AuditLogger::log(
                    'ORDER_DISH_ADDED',
                    'Órdenes',
                    "Platillo '{$dish->name}' (x{$quantity}) agregado a la orden #{$order->id} ({$order->folio}) por " . (auth()->user()?->name ?? 'Usuario'),
                    auth()->user(),
                    'info'
                );
            } catch (\Throwable $e) {
                Log::warning('AuditLogger error en AddDishToOrderController: ' . $e->getMessage());
            }

            try {
                broadcast(new OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra', 'table', 'waiter'])));
            } catch (\Throwable $e) {
                Log::warning('Broadcast OrderStatusUpdated error en AddDishToOrderController: ' . $e->getMessage());
            }

            return response()->json([
                'message' => "Platillo '{$dish->name}' agregado correctamente a la orden.",
                'order_item' => [
                    'id'         => $orderItem->id,
                    'dish_id'    => $dish->id,
                    'name'       => $dish->name,
                    'quantity'   => $orderItem->quantity,
                    'unit_price' => $unitPrice,
                    'subtotal'   => $itemSubtotal,
                    'notes'      => $orderItem->notes,
                    'extras'     => $savedExtras,
                ],
                'order' => [
                    'id'           => $order->id,
                    'folio'        => $order->folio,
                    'status'       => $order->status,
                    'total_amount' => (float) $order->total_amount,
                ],
            ], 201);
        });
    }

    /**
     * Invocación directa
     */
    public function __invoke(AddDishToOrderRequest $request)
    {
        return $this->add($request);
    }
}
