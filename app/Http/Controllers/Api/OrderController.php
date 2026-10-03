<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCustomerOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Jobs\SendKitchenNotification;
use App\Jobs\SendOrderToN8n;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function kitchenOrders(Request $request)
    {
        $attendedToday = Order::whereDate('updated_at', today())->whereIn('status', ['ready', 'completed'])->count();

        $query = Order::with('items.dish', 'items.extras.extra', 'user');

        // Filtrar por status
        if ($request->filled('status')) {
            $statuses = explode(',', $request->status);
            if (in_array('completed', $statuses) && !in_array('ready', $statuses) && count($statuses) === 1) {
                $statuses[] = 'ready';
            }
            $query->whereIn('status', $statuses);

            if ($request->filled('date')) {
                if (in_array('completed', $statuses) || in_array('ready', $statuses)) {
                    $query->whereDate('updated_at', $request->date);
                } else {
                    $query->whereDate('created_at', $request->date);
                }
            }
        } else {
            // Solo pedidos activos que necesitan atención en cocina (excluye ready, completed y cancelled)
            $query->whereIn('status', ['pending', 'preparing']);

            // Solo órdenes del día actual o pendientes
            if ($request->filled('date')) {
                $query->whereDate('created_at', $request->date);
            } else {
                $query->where(function($q) {
                    $q->whereDate('created_at', now()->toDateString())
                      ->orWhere('status', 'pending');
                });
            }
        }

        // Filtrar por mesa
        if ($request->filled('table_number')) {
            $query->where('table_number', $request->table_number);
        }

        $orders = $query->latest('id')->get();

        return response()->json($orders->map(function ($order) {
            return [
                'id'             => $order->id,
                'daily_number'   => $order->daily_number,
                'folio'          => $order->folio ?? 'PED-' . str_pad($order->id, 4, '0', STR_PAD_LEFT),
                'dispatch_token' => $order->dispatch_token,
                'status'         => $order->status,
                'modality'       => $order->modality ?? 'local',
                'customer'       => $order->customer_name ?? 'Cliente local',
                'phone'          => $order->customer_phone ?? '',
                'address'        => $order->customer_address ?? '',
                'table'          => $order->table_number ?? null,
                'total'          => (float) $order->total_amount,
                'notes'          => $order->notes ?? '',
                'created_at' => $order->created_at?->format('Y-m-d H:i:s'),
                'items'      => $order->items->map(fn($item) => [
                    'id'       => $item->id,
                    'name'     => $item->dish?->name ?? '',
                    'quantity' => $item->quantity,
                    'price'    => (float) $item->price,
                    'notes'    => $item->notes ?? '',
                    'extras'   => $item->extras->map(fn($e) => [
                        'id'    => $e->extra_id,
                        'name'  => $e->extra?->name ?? '',
                        'price' => (float) $e->price,
                    ])->toArray(),
                ])->toArray(),
            ];
        }));
    }

    public function index(Request $request)
    {
        $query = Order::with('user', 'items.dish');

        // Filter: Search
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Filter: Canal / Modality
        if ($request->filled('canal') && !in_array($request->canal, ['all', 'todos'], true)) {
            $canal = strtolower(trim($request->canal));
            if ($canal === 'domicilio') {
                $query->whereIn('modality', ['delivery', 'domicilio']);
            } elseif ($canal === 'pickup') {
                $query->where('modality', 'pickup');
            } elseif ($canal === 'local') {
                $query->whereIn('modality', ['local', 'mesa', 'table', 'dine_in']);
            } else {
                $query->where('modality', $canal);
            }
        }

        // Filter: Estado
        if ($request->filled('estado') && !in_array($request->estado, ['all', 'todos'], true)) {
            $estado = strtolower(trim($request->estado));
            if ($estado === 'completed') {
                $query->whereIn('status', ['completed', 'delivered']);
            } else {
                $query->where('status', $estado);
            }
        }

        // Filter: Método de pago
        if ($request->filled('metodo_pago') && !in_array($request->metodo_pago, ['all', 'todos'], true)) {
            $pago = strtolower(trim($request->metodo_pago));
            if ($pago === 'cash') {
                $query->whereIn('payment_method', ['cash', 'efectivo']);
            } elseif ($pago === 'terminal' || $pago === 'card') {
                $query->whereIn('payment_method', ['terminal', 'card', 'tarjeta']);
            } elseif ($pago === 'transfer') {
                $query->whereIn('payment_method', ['transfer', 'transferencia']);
            } else {
                $query->where('payment_method', $pago);
            }
        }

        // Filter: Fecha Desde
        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->fecha_desde);
        }

        // Filter: Fecha Hasta
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->fecha_hasta);
        }

        $orders = $query->latest('id')->get();

        $canalMap = [
            'delivery'  => 'domicilio',
            'domicilio' => 'domicilio',
            'pickup'    => 'pick-up',
            'local'     => 'local',
            'table'     => 'local',
            'dine_in'   => 'local',
            'mesa'      => 'local',
        ];

        $pagoMap = [
            'efectivo'     => 'cash',
            'cash'         => 'cash',
            'terminal'     => 'terminal',
            'card'         => 'terminal',
            'tarjeta'      => 'terminal',
            'transferencia'=> 'transfer',
            'transfer'     => 'transfer',
        ];

        $mappedPedidos = $orders->map(function ($order) use ($canalMap, $pagoMap) {
            $canal = $canalMap[$order->modality] ?? ($order->modality ?: 'local');
            $metodoPago = $pagoMap[$order->payment_method] ?? ($order->payment_method ?: 'cash');
            $numPedido = 'PED-' . str_pad($order->id, 4, '0', STR_PAD_LEFT);

            return [
                'id'            => $order->id,
                'numero_pedido' => $numPedido,
                'cliente'       => $order->customer_name ?: ($order->user ? $order->user->name : 'Cliente General'),
                'canal'         => $canal,
                'metodo_pago'   => $metodoPago,
                'total'         => (float) $order->total_amount,
                'estado'        => $order->status ?: 'pending',
                'fecha'         => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '',
            ];
        });

        $totalPedidos = $mappedPedidos->count();
        $totalGeneral = (float) $mappedPedidos->sum('total');

        $completadosList = $mappedPedidos->filter(fn($p) => in_array($p['estado'], ['completed', 'delivered']));
        $ingresosCompletados = (float) $completadosList->sum('total');
        $completadosCount = $completadosList->count();

        $canceladosCount = $mappedPedidos->filter(fn($p) => $p['estado'] === 'cancelled')->count();
        $porcentajeCancelados = $totalPedidos > 0 ? round(($canceladosCount / $totalPedidos) * 100, 1) : 0;

        return response()->json([
            'pedidos' => $mappedPedidos,
            'resumen' => [
                'total_pedidos'        => $totalPedidos,
                'total_general'        => $totalGeneral,
                'ingresos_completados' => $ingresosCompletados,
                'completados'          => $completadosCount,
                'cancelados'           => $canceladosCount,
                'porcentaje_cancelados'=> $porcentajeCancelados,
            ]
        ]);
    }

    public function show($id)
    {
        $cleanId = trim((string) $id);
        $order = Order::with(['items.dish', 'items.extras.extra', 'user', 'delivery.driver'])
            ->where(function ($query) use ($cleanId) {
                if (is_numeric($cleanId)) {
                    $query->where('id', (int) $cleanId);
                }
                $query->orWhere('folio', $cleanId)
                      ->orWhere('dispatch_token', strtoupper($cleanId));
            })
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        return response()->json($order);
    }

    public function getByDispatchToken($token)
    {
        $cleanToken = strtoupper(trim((string) $token));
        $order = Order::with(['items.dish', 'items.extras.extra', 'user', 'delivery.driver'])
            ->where('dispatch_token', $cleanToken)
            ->orWhere('folio', $cleanToken)
            ->first();

        if (!$order) {
            return response()->json([
                'message' => "No se encontró ningún pedido con el token de despacho '{$cleanToken}'."
            ], 404);
        }

        return response()->json($order);
    }

    public function updateStatus(Request $request, $order = null)
    {
        return app(\App\Http\Controllers\Api\KitchenController::class)->updateStatus($request, $order);
    }

    public function waiterUpdateStatus(Request $request, Order $order)
    {
        $data = $request->validate([
            'status' => 'required|string|in:completed,entregado,completada,delivered',
        ]);

        $order->update(['status' => 'completed']);
        return response()->json($order);
    }

    public function storeOnline(StoreCustomerOrderRequest $request)
    {
        return $this->processOrderCreation($request, true);
    }

    public function store(Request $request)
    {
        return $this->processOrderCreation($request, false);
    }

    private function processOrderCreation(Request $request, bool $isOnline = false)
    {
        // 1. Extraer y normalizar items / carrito
        $rawItems = $request->input('items') ?? $request->input('cart') ?? $request->input('carrito') ?? $request->input('platillos') ?? [];
        if (!is_array($rawItems) || empty($rawItems)) {
            return response()->json(['message' => 'El carrito de compras o la lista de platillos no puede estar vacía.'], 422);
        }

        // 2. Normalizar modalidad
        $rawModality = strtolower((string) ($request->input('order_type') ?? $request->input('modality') ?? $request->input('modalidad') ?? $request->input('tipo') ?? ($isOnline ? 'delivery' : 'local')));
        if (in_array($rawModality, ['pickup', 'llevar', 'para_llevar'])) {
            $modality = 'pickup';
        } elseif (in_array($rawModality, ['delivery', 'domicilio', 'a_domicilio'])) {
            $modality = 'delivery';
        } else {
            $modality = 'local';
        }

        // 3. Normalizar método de pago
        $rawPayment = strtolower((string) ($request->input('payment_method') ?? $request->input('metodo_pago') ?? $request->input('metodoPago') ?? $request->input('pago') ?? 'cash'));
        if (in_array($rawPayment, ['card', 'tarjeta', 'stripe', 'terminal'])) {
            $paymentMethod = 'card';
        } elseif (in_array($rawPayment, ['transfer', 'transferencia', 'spei', 'banco'])) {
            $paymentMethod = 'transfer';
        } else {
            // cash, efectivo, whatsapp
            $paymentMethod = 'cash';
        }

        // 4. Validación y desglose de dirección
        if ($request instanceof StoreCustomerOrderRequest) {
            $addressData = [
                'calle'       => $request->input('calle'),
                'num_ext'     => $request->input('no_exterior') ?? $request->input('num_ext'),
                'num_int'     => $request->input('no_interior') ?? $request->input('num_int'),
                'cp'          => $request->input('codigo_postal') ?? $request->input('cp'),
                'colonia'     => $request->input('colonia'),
                'referencias' => $request->input('referencias'),
            ];
        } elseif ($modality === 'delivery') {
            $addressData = $request->validate([
                'calle'       => 'required|string|max:255',
                'num_ext'     => 'nullable|string|max:50',
                'no_exterior' => 'nullable|string|max:50',
                'num_int'     => 'nullable|string|max:50',
                'no_interior' => 'nullable|string|max:50',
                'cp'          => 'nullable|string|max:10',
                'codigo_postal'=> 'nullable|string|max:10',
                'colonia'     => 'required|string|max:255',
                'referencias' => 'nullable|string',
            ], [
                'calle.required'       => 'La calle es obligatoria para envíos a domicilio.',
                'colonia.required'     => 'La colonia es obligatoria.',
            ]);
        } else {
            // Para pickup o local estos campos son opcionales / nulos
            $addressData = $request->validate([
                'calle'       => 'nullable|string|max:255',
                'num_ext'     => 'nullable|string|max:50',
                'num_int'     => 'nullable|string|max:50',
                'cp'          => 'nullable|string|max:10',
                'colonia'     => 'nullable|string|max:255',
                'referencias' => 'nullable|string',
            ]);
        }

        // 5. Datos del cliente y armado de dirección
        $customerName = trim((string) ($request->input('nombre_completo') ?? $request->input('customer_name') ?? $request->input('nombre') ?? $request->input('name') ?? $request->input('cliente') ?? ($isOnline ? 'Cliente en línea' : 'Cliente local')));
        $customerPhone = trim((string) ($request->input('telefono') ?? $request->input('customer_phone') ?? $request->input('phone') ?? $request->input('celular') ?? ''));
        $rawNotes = $request->input('nota_especial') ?? $request->input('notes') ?? $request->input('notas') ?? $request->input('observaciones') ?? null;
        $notes = null;
        if ($rawNotes !== null) {
            $noScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $rawNotes);
            $notes = trim(strip_tags($noScripts));
        }

        $calle = trim((string) ($addressData['calle'] ?? $request->input('calle') ?? ''));
        $numExt = trim((string) ($addressData['num_ext'] ?? $request->input('no_exterior') ?? $request->input('num_ext') ?? ''));
        $numInt = trim((string) ($addressData['num_int'] ?? $request->input('no_interior') ?? $request->input('num_int') ?? ''));
        $cp = trim((string) ($addressData['cp'] ?? $request->input('codigo_postal') ?? $request->input('cp') ?? ''));
        $colonia = trim((string) ($addressData['colonia'] ?? $request->input('colonia') ?? ''));
        $referencias = trim((string) ($addressData['referencias'] ?? $request->input('referencias') ?? ''));

        if (!empty($calle) && !empty($numExt)) {
            $customerAddress = "Calle {$calle} #{$numExt}" . ($numInt ? " Int. {$numInt}" : "") . ", Col. {$colonia}, C.P. {$cp}";
            if (!empty($referencias)) {
                $notes = trim(($notes ? $notes . " | " : "") . "Referencias: {$referencias}");
            }
        } else {
            $customerAddress = trim((string) ($request->input('customer_address') ?? $request->input('direccion') ?? $request->input('address') ?? ''));
        }

        $customerEmail = $request->input('customer_email') ?? $request->input('email');
        $tableNumber = $request->input('table_number') ?? $request->input('mesa') ?? $request->input('table');

        if ($modality !== 'local' && empty($customerName)) {
            $customerName = $isOnline ? 'Cliente en línea' : 'Cliente';
        }

        // 6. Procesar platillos y extras calculando subtotales reales
        $total = 0;
        $orderItems = [];

        foreach ($rawItems as $item) {
            $dishId = $item['dish_id'] ?? $item['id'] ?? $item['platillo_id'] ?? null;
            if (!$dishId) continue;

            $dish = \App\Models\Dish::find($dishId);
            if (!$dish) {
                return response()->json(['message' => "El platillo con ID {$dishId} no fue encontrado."], 404);
            }

            $quantity = max(1, (int) ($item['quantity'] ?? $item['cantidad'] ?? 1));
            $dishPrice = (float) $dish->price;
            $itemTotal = $dishPrice * $quantity;

            $extraPrices = [];
            $rawExtras = $item['extras'] ?? [];
            if (!empty($rawExtras) && is_array($rawExtras)) {
                $extraIds = [];
                foreach ($rawExtras as $e) {
                    if (is_numeric($e)) $extraIds[] = (int) $e;
                    elseif (is_array($e) && isset($e['id'])) $extraIds[] = (int) $e['id'];
                    elseif (is_array($e) && isset($e['extra_id'])) $extraIds[] = (int) $e['extra_id'];
                }

                if (!empty($extraIds)) {
                    // Validar pertenencia estricta de extras en dish_extra para este platillo
                    $allowedExtraIds = \DB::table('dish_extra')
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

                    $extras = \App\Models\Extra::whereIn('id', $allowedExtraIds)->get();
                    foreach ($extras as $extra) {
                        $itemTotal += (float) $extra->price * $quantity;
                        $extraPrices[] = [
                            'extra_id' => $extra->id,
                            'price'    => (float) $extra->price
                        ];
                    }
                }
            }

            $rawItemNotes = $item['notes'] ?? $item['notas'] ?? null;
            $cleanItemNotes = null;
            if ($rawItemNotes !== null) {
                $noItemScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $rawItemNotes);
                $cleanItemNotes = trim(strip_tags($noItemScripts));
            }

            $total += $itemTotal;
            $orderItems[] = [
                'dish_id'  => $dish->id,
                'quantity' => $quantity,
                'price'    => $dishPrice,
                'notes'    => $cleanItemNotes,
                'extras'   => $extraPrices,
            ];
        }

        if (empty($orderItems)) {
            return response()->json(['message' => 'No se encontraron platillos válidos en la orden.'], 422);
        }

        // 6. Generación de Folio seguro y correlativo
        if ($modality === 'delivery') {
            $folio = Order::generarFolioPedido('DEL');
            $dailyNumber = (int) substr($folio, -4);
        } else {
            $folio = Order::generarFolioPedido('PED');
            $dailyNumber = (int) substr($folio, -4);
        }

        // 7. Crear la Orden en estado "pending" con dispatch_token único
        $order = Order::create([
            'folio'            => $folio,
            'dispatch_token'   => Order::generateUniqueDispatchToken(),
            'daily_number'     => $dailyNumber,
            'user_id'          => auth()->id() ?? $request->user()?->id ?? null,
            'customer_name'    => $customerName,
            'customer_phone'   => $customerPhone,
            'customer_address' => $customerAddress,
            'customer_email'   => $customerEmail,
            'table_number'     => $tableNumber,
            'modality'         => $modality,
            'payment_method'   => $paymentMethod,
            'payment_status'   => 'pending',
            'status'           => 'pending',
            'total_amount'     => $total,
            'notes'            => $notes,
        ]);

        // 8. Insertar items y extras asociados
        foreach ($orderItems as $item) {
            $extras = $item['extras'];
            unset($item['extras']);
            $orderItem = $order->items()->create($item);
            foreach ($extras as $extra) {
                $orderItem->extras()->create($extra);
            }
        }

        // 9. Delegar notificación a la cocina y WebSockets a la cola en segundo plano
        SendKitchenNotification::dispatch($order, $isOnline);

        // 10. Delegar notificación multi-canal (Discord/Telegram) al QueueWorker (proceso asíncrono)
        SendOrderToN8n::dispatch($order);

        return response()->json($order->load('items.dish', 'items.extras.extra'), 201);
    }

    public function updatePayment(Request $request, Order $order)
    {
        $data = $request->validate([
            'payment_method' => 'required|in:cash,card,transfer',
            'payment_status' => 'required|in:paid,partial',
        ]);

        $order->update([
            'payment_method' => $data['payment_method'],
            'payment_status' => $data['payment_status'],
            'status'         => $data['payment_status'] === 'paid' ? 'completed' : $order->status,
        ]);

        return response()->json($order->fresh());
    }
}
