<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\OpenTableRequest;
use App\Models\Table;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\Dish;
use App\Models\Extra;
use App\Events\OrderStatusUpdated;
use App\Jobs\SendOrderToN8n;
use App\Services\NotificationService;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OpenTableOrderController extends Controller
{
    /**
     * Abre una mesa de forma atómica previniendo condiciones de carrera (Race Conditions).
     * Emplea bloqueo pesimista (lockForUpdate) en PostgreSQL y actualiza el estado de la mesa a 'ocupada'.
     */
    public function open(OpenTableRequest $request)
    {
        $tableId = (int) ($request->input('table_id') ?? $request->table_id);
        $user = auth()->user() ?? $request->user();
        $waiterId = $user?->id;

        return DB::transaction(function () use ($tableId, $user, $waiterId, $request) {
            // 1. BLOQUEO DE CONCURRENCIA (Pessimistic Locking en PostgreSQL)
            $table = Table::where('id', $tableId)
                ->lockForUpdate()
                ->first();

            if (!$table) {
                return response()->json([
                    'message' => 'La mesa seleccionada no existe.'
                ], 404);
            }

            // Comprobar si la mesa ya fue ocupada o si tiene una comanda activa
            $hasActiveOrder = Order::where(function ($q) use ($table) {
                $q->where('table_id', $table->id)
                  ->orWhere('table_number', (string) $table->numero_mesa);
            })
            ->whereIn('status', ['pending', 'preparing', 'ready', 'open', 'abierto', 'en_preparacion'])
            ->lockForUpdate()
            ->exists();

            if ($table->status === 'ocupada' || $hasActiveOrder) {
                return response()->json([
                    'message' => 'Esta mesa ya fue ocupada por otro mesero.'
                ], 409);
            }

            // 2. ACTUALIZACIÓN ATÓMICA DE ESTADO
            $table->update([
                'status' => 'ocupada',
            ]);

            // 3. PROCESAR PLATILLOS INICIALES (si se enviaron en el request)
            $rawItems = $request->input('items', []);
            $totalAmount = 0.0;
            $orderItemsData = [];

            if (!empty($rawItems) && is_array($rawItems)) {
                foreach ($rawItems as $item) {
                    $dishId = $item['dish_id'] ?? null;
                    if (!$dishId) continue;

                    $dish = Dish::find($dishId);
                    if (!$dish) continue;

                    $qty = max(1, (int) ($item['quantity'] ?? 1));
                    $price = (float) $dish->price;
                    $subtotal = $price * $qty;

                    $extraList = [];
                    $rawExtras = $item['extras'] ?? [];
                    if (!empty($rawExtras) && is_array($rawExtras)) {
                        $extraIds = [];
                        foreach ($rawExtras as $e) {
                            if (is_numeric($e)) $extraIds[] = (int) $e;
                            elseif (is_array($e) && isset($e['id'])) $extraIds[] = (int) $e['id'];
                        }
                        if (!empty($extraIds)) {
                            // Validar pertenencia de extras en dish_extra para este platillo
                            $allowedExtraIds = DB::table('dish_extra')
                                ->where('dish_id', $dish->id)
                                ->whereIn('extra_id', $extraIds)
                                ->pluck('extra_id')
                                ->map(fn($id) => (int) $id)
                                ->all();

                            $disallowedExtras = array_diff($extraIds, $allowedExtraIds);
                            if (!empty($disallowedExtras)) {
                                return response()->json([
                                    'message' => "El extra seleccionado no pertenece al platillo '{$dish->name}'.",
                                    'errors'  => [
                                        'extras' => ["Uno o más extras no están autorizados para el platillo '{$dish->name}'."]
                                    ]
                                ], 422);
                            }

                            $extras = Extra::whereIn('id', $allowedExtraIds)->get();
                            foreach ($extras as $extra) {
                                $subtotal += (float) $extra->price * $qty;
                                $extraList[] = [
                                    'extra_id' => $extra->id,
                                    'price'    => (float) $extra->price,
                                ];
                            }
                        }
                    }

                    $rawNotes = $item['notes'] ?? null;
                    $cleanNotes = null;
                    if ($rawNotes !== null) {
                        $noNotesScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $rawNotes);
                        $cleanNotes = trim(strip_tags($noNotesScripts));
                    }

                    $totalAmount += $subtotal;
                    $orderItemsData[] = [
                        'dish'     => $dish,
                        'quantity' => $qty,
                        'price'    => $price,
                        'notes'    => $cleanNotes,
                        'extras'   => $extraList,
                    ];
                }
            }

            // 4. GENERACIÓN DE FOLIO CORRELATIVO
            $folio = Order::generarFolioPedido('PED');
            $dailyNumber = (int) substr($folio, -4);

            $customerName = $request->filled('customer_name')
                ? $request->input('customer_name')
                : "Mesa {$table->numero_mesa}";

            // 5. CREAR LA ORDEN GARANTIZANDO INTEGRIDAD REFERENCIAL ÚNICA
            $order = Order::create([
                'folio'            => $folio,
                'dispatch_token'   => Order::generateUniqueDispatchToken(),
                'daily_number'     => $dailyNumber,
                'user_id'          => $waiterId,
                'waiter_id'        => $waiterId,
                'customer_name'    => $customerName,
                'customer_phone'   => '',
                'customer_address' => "Mesa {$table->numero_mesa}",
                'table_id'         => $table->id,
                'table_number'     => (string) $table->numero_mesa,
                'modality'         => 'local',
                'payment_method'   => 'cash',
                'payment_status'   => 'pending',
                'status'           => 'pending',
                'total_amount'     => $totalAmount,
                'notes'            => $request->input('notes'),
            ]);

            foreach ($orderItemsData as $itemData) {
                $orderItem = $order->items()->create([
                    'dish_id'  => $itemData['dish']->id,
                    'quantity' => $itemData['quantity'],
                    'price'    => $itemData['price'],
                    'notes'    => $itemData['notes'],
                ]);

                foreach ($itemData['extras'] as $extraItem) {
                    $orderItem->extras()->create([
                        'extra_id' => $extraItem['extra_id'],
                        'price'    => $extraItem['price'],
                    ]);
                }
            }

            // 6. NOTIFICACIONES, BITÁCORA Y BROADCAST
            try {
                AuditLogger::log(
                    'TABLE_OPENED',
                    'Mesas',
                    "Mesa #{$table->numero_mesa} abierta por " . ($user?->name ?? 'Mesero'),
                    $user,
                    'info'
                );
            } catch (\Throwable $e) {}

            try {
                NotificationService::create(
                    'mesa_abierta',
                    'Mesa Abierta',
                    "Mesa #{$table->numero_mesa} abierta por " . ($user?->name ?? 'Mesero') . " (Orden #{$order->folio})",
                    [
                        'table_id'     => $table->id,
                        'table_number' => $table->numero_mesa,
                        'order_id'     => $order->id,
                        'folio'        => $order->folio,
                        'waiter_id'    => $waiterId,
                    ]
                );
            } catch (\Throwable $e) {}

            try {
                broadcast(new OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra', 'table', 'waiter'])));
            } catch (\Throwable $e) {
                Log::warning('Broadcast OrderStatusUpdated in OpenTableOrderController: ' . $e->getMessage());
            }

            // Encolar webhook a n8n en segundo plano
            SendOrderToN8n::dispatch($order);

            return response()->json([
                'message' => "Mesa #{$table->numero_mesa} abierta correctamente.",
                'table'   => $table->fresh(),
                'order'   => $order->fresh()->load(['items.dish', 'items.extras.extra']),
            ], 201);
        });
    }

    /**
     * Invocación directa del controlador
     */
    public function __invoke(OpenTableRequest $request)
    {
        return $this->open($request);
    }
}
