<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\Order;
use App\Models\CashCut;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DeliveryController extends Controller
{
    private function normalizeStatus(?string $status): string
    {
        $st = strtolower(trim($status ?? 'pending'));
        if (in_array($st, ['pending', 'pendiente'], true)) {
            return 'pending';
        }
        if (in_array($st, ['en_ruta', 'in_transit', 'preparing', 'ready', 'en ruta'], true)) {
            return 'en_ruta';
        }
        if (in_array($st, ['entregada', 'entregado', 'delivered', 'completed', 'completada'], true)) {
            return 'entregada';
        }
        if (in_array($st, ['cancelada', 'cancelled'], true)) {
            return 'cancelada';
        }
        return 'pending';
    }

    private function dbDeliveryStatusFromNormalized(string $normStatus): string
    {
        return match ($normStatus) {
            'en_ruta'   => 'in_transit',
            'entregada' => 'delivered',
            'cancelada' => 'cancelled',
            default     => 'pending',
        };
    }

    private function dbOrderStatusFromNormalized(string $normStatus): string
    {
        return match ($normStatus) {
            'en_ruta'   => 'preparing',
            'entregada' => 'completed',
            'cancelada' => 'cancelled',
            default     => 'pending',
        };
    }

    public function index(Request $request)
    {
        $ordersWithoutDelivery = Order::where('modality', 'delivery')
            ->doesntHave('delivery')
            ->get();
        foreach ($ordersWithoutDelivery as $order) {
            $order->delivery()->create(['status' => 'pending']);
        }

        $query = Delivery::with(['order.items.dish', 'order.items.extras.extra', 'driver', 'zone']);

        if ($request->filled('status') && $request->status !== 'all') {
            $dbStatus = $this->dbDeliveryStatusFromNormalized($this->normalizeStatus($request->status));
            $query->where('status', $dbStatus);
        }

        if ($request->filled('start_date')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->start_date);
            });
        }

        if ($request->filled('end_date')) {
            $query->whereHas('order', function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->end_date);
            });
        }

        return response()->json($query->latest()->get());
    }

    public function update(Request $request, Delivery $delivery)
    {
        $data = $request->validate([
            'driver_id'        => 'nullable|exists:delivery_drivers,id',
            'delivery_zone_id' => 'nullable|exists:delivery_zones,id',
            'status'           => 'required|string',
        ]);

        $normStatus = $this->normalizeStatus($data['status']);
        $dbDeliveryStatus = $this->dbDeliveryStatusFromNormalized($normStatus);
        $dbOrderStatus = $this->dbOrderStatusFromNormalized($normStatus);

        $data['status'] = $dbDeliveryStatus;
        $delivery->update($data);

        if ($delivery->order) {
            $prevStatus = $delivery->order->status;
            $delivery->order->update(['status' => $dbOrderStatus]);

            if (in_array($prevStatus, ['pending', 'pendiente']) && in_array($dbOrderStatus, ['preparing', 'in_transit', 'en_ruta', 'ready', 'completed'])) {
                \App\Services\InventoryService::descontarInventario($delivery->order->id);
            }
        }

        return response()->json($delivery->load(['order', 'driver', 'zone']));
    }

    public function getOrders(Request $request)
    {
        $ordersWithoutDelivery = Order::where('modality', 'delivery')
            ->doesntHave('delivery')
            ->get();
        foreach ($ordersWithoutDelivery as $ord) {
            $ord->delivery()->create(['status' => 'pending']);
        }

        $query = Order::where('modality', 'delivery')->with(['delivery.driver', 'items.dish', 'items.extras.extra']);

        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanSearch = ltrim($search, '#');
            $query->where(function ($q) use ($search, $cleanSearch) {
                $q->where('id', 'like', "%{$cleanSearch}%")
                  ->orWhere('folio', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_phone', 'like', "%{$search}%")
                  ->orWhereHas('delivery.driver', function ($dq) use ($search) {
                      $dq->where('name', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('status') && !in_array($request->status, ['all', 'todas', 'todos'], true)) {
            $st = $this->normalizeStatus($request->status);
            if ($st === 'pending') {
                $query->whereIn('status', ['pending', 'pendiente']);
            } elseif ($st === 'en_ruta') {
                $query->whereIn('status', ['en_ruta', 'in_transit', 'preparing', 'ready']);
            } elseif ($st === 'entregada') {
                $query->whereIn('status', ['entregada', 'entregado', 'delivered', 'completed']);
            } elseif ($st === 'cancelada') {
                $query->whereIn('status', ['cancelada', 'cancelled']);
            }
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }
        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $orders = $query->latest('id')->get();

        $mappedEntregas = $orders->map(function ($order) {
            $normStatus = $this->normalizeStatus($order->status);
            $driver = $order->delivery ? $order->delivery->driver : null;

            return [
                'id'               => $order->id,
                'folio'            => $order->folio ?: "#{$order->id}",
                'dispatch_token'   => $order->dispatch_token ?: $order->folio ?: "AURUM-DEL-{$order->id}",
                'customer_name'    => $order->customer_name ?: 'Cliente General',
                'customer_phone'   => $order->customer_phone ?: '',
                'customer_address' => $order->customer_address ?: '',
                'total_amount'     => (float) $order->total_amount,
                'status'           => $normStatus,
                'driver_name'      => $driver ? $driver->name : null,
                'driver_id'        => $driver ? $driver->id : null,
                'created_at'       => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '',
                'items'            => $order->items->map(function ($item) {
                    return [
                        'dish_name' => $item->dish ? $item->dish->name : 'Platillo',
                        'quantity'  => (int) $item->quantity,
                        'price'     => (float) $item->price,
                        'extras'    => $item->extras->map(fn($e) => [
                            'name'  => $e->extra ? $e->extra->name : 'Extra',
                            'price' => (float) $e->price
                        ])->toArray()
                    ];
                })->toArray(),
            ];
        });

        $allDeliveryOrders = Order::where('modality', 'delivery')->get();

        $pendientesCount = 0;
        $enRutaCount = 0;
        $entregadasCount = 0;
        $canceladasCount = 0;
        $ventasDelivery = 0.0;

        foreach ($allDeliveryOrders as $ord) {
            $st = $this->normalizeStatus($ord->status);
            if ($st === 'pending') $pendientesCount++;
            elseif ($st === 'en_ruta') $enRutaCount++;
            elseif ($st === 'entregada') {
                $entregadasCount++;
                $ventasDelivery += (float) $ord->total_amount;
            }
            elseif ($st === 'cancelada') $canceladasCount++;
        }

        return response()->json([
            'entregas' => $mappedEntregas,
            'resumen'  => [
                'pendientes'      => $pendientesCount,
                'en_ruta'         => $enRutaCount,
                'entregadas'      => $entregadasCount,
                'canceladas'      => $canceladasCount,
                'ventas_delivery' => round($ventasDelivery, 2),
                'total_pedidos'   => $allDeliveryOrders->count(),
            ]
        ]);
    }

    public function getOrderDetails($id)
    {
        $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])->findOrFail($id);
        $normStatus = $this->normalizeStatus($order->status);
        $driver = $order->delivery ? $order->delivery->driver : null;

        return response()->json([
            'id'               => $order->id,
            'folio'            => $order->folio ?: "#{$order->id}",
            'dispatch_token'   => $order->dispatch_token ?: $order->folio ?: "AURUM-DEL-{$order->id}",
            'customer_name'    => $order->customer_name ?: 'Cliente General',
            'customer_phone'   => $order->customer_phone ?: '',
            'customer_address' => $order->customer_address ?: '',
            'total_amount'     => (float) $order->total_amount,
            'status'           => $normStatus,
            'payment_method'   => $order->payment_method ?: 'cash',
            'payment_status'   => $order->payment_status ?: 'pending',
            'driver_name'      => $driver ? $driver->name : null,
            'driver_id'        => $driver ? $driver->id : null,
            'created_at'       => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : '',
            'items'            => $order->items->map(function ($item) {
                return [
                    'dish_name' => $item->dish ? $item->dish->name : 'Platillo',
                    'quantity'  => (int) $item->quantity,
                    'price'     => (float) $item->price,
                    'extras'    => $item->extras->map(fn($e) => [
                        'name'  => $e->extra ? $e->extra->name : 'Extra',
                        'price' => (float) $e->price
                    ])->toArray()
                ];
            })->toArray(),
            'special_notes'    => $order->notes,
        ]);
    }

    public function generateQr($orderId)
    {
        $order = Order::findOrFail($orderId);
        $token = 'DELIVERY-QR-' . $order->id . '-' . md5("order-secret-{$order->id}");

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 200 200">'
             . '<rect width="200" height="200" fill="#ffffff" rx="16"/>'
             . '<rect x="20" y="20" width="160" height="160" fill="none" stroke="#18181b" stroke-width="4"/>'
             . '<text x="100" y="90" font-size="14" font-weight="bold" fill="#18181b" text-anchor="middle">PEDIDO #' . ($order->daily_number ?? $order->id) . '</text>'
             . '<text x="100" y="115" font-size="10" fill="#71717a" text-anchor="middle">Escanear para Asignar</text>'
             . '</svg>';

        $base64Qr = 'data:image/svg+xml;base64,' . base64_encode($svg);

        return response()->json([
            'order_id'       => $order->id,
            'daily_number'   => $order->daily_number,
            'dispatch_token' => $order->dispatch_token,
            'qr_token'       => $token,
            'qr_code'        => $base64Qr,
        ]);
    }

    public function acceptOrderByToken(Request $request, $token)
    {
        $cleanToken = strtoupper(trim(str_replace(['-', ' ', '#'], '', (string) $token)));

        $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
            ->where('dispatch_token', $cleanToken)
            ->orWhere('folio', $token)
            ->orWhere('folio', $cleanToken)
            ->first();

        if (!$order && is_numeric($token)) {
            $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])->find((int) $token);
        }

        if (!$order) {
            return response()->json(['message' => "Pedido no encontrado para el token '{$cleanToken}'."], 404);
        }

        $request->merge([
            'order_id'       => $order->id,
            'id'             => $order->id,
            'folio'          => $order->folio,
            'dispatch_token' => $order->dispatch_token,
        ]);

        return $this->assignToDelivery($request);
    }

    public function scanQr(Request $request)
    {
        return $this->assignToDelivery($request);
    }

    public function assignByFolio(Request $request)
    {
        $folio = trim((string) ($request->input('folio') ?? $request->input('code') ?? ''));

        if (!$folio) {
            return response()->json(['message' => 'Folio no proporcionado.'], 422);
        }

        $order = Order::where('dispatch_token', strtoupper($folio))
            ->orWhere('folio', $folio)
            ->first();

        if (!$order) {
            $order = Order::whereRaw('LOWER(folio) = ?', [strtolower($folio)])->first();
        }

        if (!$order) {
            return response()->json(['message' => 'Folio no encontrado.'], 404);
        }

        $request->merge(['folio' => $order->folio, 'order_id' => $order->id]);
        return $this->assignToDelivery($request);
    }

    public function assignToDelivery(Request $request)
    {
        $data = $request->validate([
            'folio'    => 'nullable|string',
            'order_id' => 'nullable',
            'id'       => 'nullable',
            'qr_token' => 'nullable|string',
            'token'    => 'nullable|string',
            'code'     => 'nullable|string',
        ]);

        $folio = trim((string) ($data['folio'] ?? ''));
        $orderId = $data['order_id'] ?? $data['id'] ?? null;

        if (!$folio && !$orderId) {
            $tokenStr = trim((string) ($data['qr_token'] ?? $data['token'] ?? $data['code'] ?? ''));
            if (preg_match('/DELIVERY-QR-(\d+)-/', $tokenStr, $matches)) {
                $orderId = (int) $matches[1];
            } elseif (preg_match('/DELIVERY-QR-([A-Za-z0-9\-]+)/', $tokenStr, $matches)) {
                $folio = $matches[1];
            } elseif (is_numeric($tokenStr)) {
                $orderId = (int) $tokenStr;
            } else {
                $folio = $tokenStr;
            }
        }

        $order = null;
        if ($folio) {
            $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
                ->where('dispatch_token', strtoupper($folio))
                ->orWhere('folio', $folio)
                ->first();

            if (!$order) {
                $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
                    ->whereRaw('LOWER(folio) = ?', [strtolower($folio)])
                    ->first();
            }
        } elseif ($orderId) {
            if (is_numeric($orderId)) {
                $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])->find($orderId);
            }
            if (!$order) {
                $order = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
                    ->where('dispatch_token', strtoupper((string) $orderId))
                    ->orWhere('folio', (string) $orderId)
                    ->first();
            }
        }

        if (!$order) {
            return response()->json(['message' => 'Folio o token no encontrado'], 404);
        }

        // Validar que la orden esté lista para entrega o pendiente de despacho
        if (in_array($order->status, ['completed', 'cancelled', 'delivered'])) {
            return response()->json([
                'message' => "La orden #{$order->folio} ya se encuentra finalizada o cancelada."
            ], 422);
        }

        // Obtener el repartidor autenticado
        $user = auth()->user() ?? $request->user();
        $driver = null;
        if ($user) {
            $driver = DeliveryDriver::where('user_id', $user->id)->first();
            if (!$driver) {
                $driver = DeliveryDriver::where('email', $user->email)
                    ->orWhere('name', $user->name)
                    ->first();
                if ($driver && !$driver->user_id) {
                    $driver->update(['user_id' => $user->id]);
                }
            }
            if (!$driver && in_array($user->role, ['repartidor', 'driver', 'admin', 'super_admin'])) {
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
                    \Illuminate\Support\Facades\Log::warning('DeliveryDriver auto-create: ' . $e->getMessage());
                }
            }
        }
        if (!$driver) {
            $driver = DeliveryDriver::where('status', 'active')->first();
        }

        // Validar que no esté asignada a otro repartidor
        $delivery = $order->delivery;
        if ($delivery && $delivery->driver_id && $driver && (int) $delivery->driver_id !== (int) $driver->id) {
            if (in_array($delivery->status, ['in_transit', 'delivered'])) {
                return response()->json([
                    'message' => "La orden #{$order->folio} ya está asignada al repartidor: " . ($delivery->driver?->name ?? 'otro repartidor')
                ], 422);
            }
        }

        // Actualizar estado de la orden y entrega
        $order->update([
            'status' => 'on_the_way'
        ]);

        if (!$delivery) {
            $delivery = $order->delivery()->create([
                'driver_id' => $driver?->id,
                'status'    => 'in_transit',
            ]);
        } else {
            $delivery->update([
                'driver_id' => $driver?->id,
                'status'    => 'in_transit',
            ]);
        }

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
            \Illuminate\Support\Facades\Log::warning('NotificationService error: ' . $e->getMessage());
        }

        try {
            broadcast(new \App\Events\OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra'])));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast error: ' . $e->getMessage());
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
        ]);
    }

    public function myActiveDelivery(Request $request)
    {
        $user = auth()->user() ?? $request->user();
        if (!$user) {
            return response()->json(null);
        }

        $driver = DeliveryDriver::where('user_id', $user->id)->first();
        if (!$driver) {
            $driver = DeliveryDriver::where('email', $user->email)->first();
        }

        if (!$driver) {
            return response()->json(null);
        }

        $activeDelivery = Delivery::with(['order.items.dish', 'order.items.extras.extra', 'driver'])
            ->where('driver_id', $driver->id)
            ->where('status', 'in_transit')
            ->latest('updated_at')
            ->first();

        if (!$activeDelivery || !$activeDelivery->order) {
            return response()->json(null);
        }

        $order = $activeDelivery->order;
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
            'coordenadas'      => ['lat' => 16.85, 'lng' => -99.82],
            'platillos'        => $itemsFormatted,
            'items'            => $itemsFormatted,
            'driver'           => [
                'id'    => $driver->id,
                'name'  => $driver->name,
                'phone' => $driver->phone,
            ],
            'created_at'       => $order->created_at?->format('Y-m-d H:i:s'),
        ]);
    }

    public function updateOrderStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status'         => 'required|string',
            'payment_method' => 'nullable|string',
        ]);

        $requestedStatus = strtolower(trim($data['status']));
        $normStatus = $this->normalizeStatus($requestedStatus);
        $dbDeliveryStatus = $this->dbDeliveryStatusFromNormalized($normStatus);
        $dbOrderStatus = $this->dbOrderStatusFromNormalized($normStatus);

        $order = Order::findOrFail($id);
        $updateFields = ['status' => $dbOrderStatus];
        if (!empty($data['payment_method'])) {
            $updateFields['payment_method'] = $data['payment_method'];
        }
        if ($normStatus === 'entregada') {
            $updateFields['payment_status'] = 'paid';
        }
        $order->update($updateFields);

        // Obtener repartidor autenticado si no estaba asignado en la entrega
        $user = auth()->user() ?? $request->user();
        $driver = null;
        if ($user) {
            $driver = DeliveryDriver::where('user_id', $user->id)->first();
            if (!$driver) {
                $driver = DeliveryDriver::where('email', $user->email)->orWhere('name', $user->name)->first();
            }
        }

        if ($order->delivery) {
            $deliveryUpdates = ['status' => $dbDeliveryStatus];
            if ($driver && !$order->delivery->driver_id) {
                $deliveryUpdates['driver_id'] = $driver->id;
            }
            $order->delivery->update($deliveryUpdates);
        } else {
            $order->delivery()->create([
                'driver_id' => $driver?->id,
                'status'    => $dbDeliveryStatus,
            ]);
        }

        try {
            NotificationService::create(
                'delivery_status',
                'Estado de Entrega Actualizado',
                "El pedido #{$order->folio} cambió a estado '{$normStatus}'",
                ['order_id' => $order->id, 'folio' => $order->folio, 'status' => $normStatus]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NotificationService error: ' . $e->getMessage());
        }

        try {
            broadcast(new \App\Events\OrderStatusUpdated($order->fresh()->load(['items.dish', 'items.extras.extra'])));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast OrderStatusUpdated error: ' . $e->getMessage());
        }

        return response()->json([
            'id'     => $order->id,
            'folio'  => $order->folio ?? "#{$order->id}",
            'status' => $normStatus,
            'order'  => $order->fresh()->load(['delivery.driver', 'items.dish', 'items.extras.extra']),
        ]);
    }

    public function getMyCut(Request $request)
    {
        $user = auth()->user() ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $driver = DeliveryDriver::where('user_id', $user->id)->first();
        if (!$driver) {
            $driver = DeliveryDriver::where('email', $user->email)->orWhere('name', $user->name)->first();
        }

        $todayMexico = Carbon::today('America/Mexico_City');
        $startOfDay = $todayMexico->copy()->startOfDay();
        $endOfDay   = $todayMexico->copy()->endOfDay();

        // Rangos para timezone UTC en caso de persistencia con UTC en Postgres
        $startUtc = $startOfDay->copy()->setTimezone('UTC');
        $endUtc   = $endOfDay->copy()->setTimezone('UTC');

        $driverId = $driver ? $driver->id : null;
        $userId = $user->id;

        $query = Order::with(['delivery.driver', 'items.dish', 'items.extras.extra'])
            ->where(function ($stQ) {
                $stQ->whereIn('status', ['completed', 'delivered', 'entregada', 'entregado'])
                    ->orWhereHas('delivery', function ($delQ) {
                        $delQ->whereIn('status', ['delivered', 'completed', 'entregada']);
                    });
            })
            ->where(function ($dateQ) use ($startOfDay, $endOfDay, $startUtc, $endUtc, $todayMexico) {
                $dateQ->whereBetween('updated_at', [$startOfDay, $endOfDay])
                      ->orWhereBetween('updated_at', [$startUtc, $endUtc])
                      ->orWhereDate('updated_at', $todayMexico)
                      ->orWhereDate('created_at', $todayMexico);
            });

        $query->where(function ($q) use ($driverId, $userId) {
            if ($driverId) {
                $q->whereHas('delivery', function ($dq) use ($driverId) {
                    $dq->where('driver_id', $driverId);
                });
            }
            if ($userId) {
                $q->orWhere('user_id', $userId);
            }
        });

        $orders = $query->latest('updated_at')->get();

        $totalCash = 0.0;
        $totalCard = 0.0;
        $totalTransfer = 0.0;
        $totalAmount = 0.0;
        $deliveries = [];

        foreach ($orders as $order) {
            $amount = (float) $order->total_amount;
            $pm = strtolower(trim((string) ($order->payment_method ?? 'cash')));

            if (in_array($pm, ['card', 'tarjeta', 'terminal', 'stripe', 'tarjeta_debito', 'tarjeta_credito'])) {
                $totalCard += $amount;
                $metodoStr = 'terminal';
            } elseif (in_array($pm, ['transfer', 'transferencia', 'spei', 'banco'])) {
                $totalTransfer += $amount;
                $metodoStr = 'transferencia';
            } else {
                $totalCash += $amount;
                $metodoStr = 'efectivo';
            }

            $totalAmount += $amount;

            $updatedAt = $order->updated_at ? $order->updated_at->setTimezone(new \DateTimeZone('America/Mexico_City')) : Carbon::now('America/Mexico_City');
            $createdAt = $order->created_at ? $order->created_at->setTimezone(new \DateTimeZone('America/Mexico_City')) : $updatedAt;

            $diffMinutes = max(1, (int) round($updatedAt->diffInMinutes($createdAt)));

            $deliveries[] = [
                'id'             => $order->id,
                'order_id'       => $order->id,
                'folio'          => $order->folio ?? "#{$order->id}",
                'dispatch_token' => $order->dispatch_token,
                'cliente'        => $order->customer_name ?: 'Cliente General',
                'customer_name'  => $order->customer_name ?: 'Cliente General',
                'telefono'       => $order->customer_phone ?: '',
                'customer_phone' => $order->customer_phone ?: '',
                'direccion'      => $order->customer_address ?: '',
                'customer_address' => $order->customer_address ?: '',
                'metodo_pago'    => $order->payment_method ?: 'cash',
                'metodoPago'     => $metodoStr,
                'payment_method' => $order->payment_method ?: 'cash',
                'payment_status' => $order->payment_status ?: 'paid',
                'monto'          => $amount,
                'total'          => $amount,
                'total_amount'   => $amount,
                'fecha'          => $updatedAt->format('d/m/Y'),
                'hora'           => $updatedAt->format('H:i'),
                'tiempo_min'     => $diffMinutes,
                'tiempoMin'      => $diffMinutes,
                'status'         => $order->status,
            ];
        }

        $completedCount = $orders->count();
        $nowMexico = Carbon::now('America/Mexico_City');

        $hasPendingCut = CashCut::where('user_id', auth()->id() ?? $user->id)
            ->where('status', 'pendiente')
            ->exists();

        $hasConfirmedCut = CashCut::where('user_id', auth()->id() ?? $user->id)
            ->where('status', 'confirmado')
            ->whereDate('created_at', $todayMexico)
            ->exists();

        return response()->json([
            'has_pending_cut'     => $hasPendingCut,
            'has_confirmed_cut'   => $hasConfirmedCut,
            'total_orders'        => $completedCount,
            'cash_total'          => round($totalCash, 2),
            'terminal_total'      => round($totalCard, 2),
            'transfer_total'      => round($totalTransfer, 2),
            'total_amount'        => round($totalAmount, 2),
            'total_cash'          => round($totalCash, 2),
            'total_card'          => round($totalCard, 2),
            'total_transfer'      => round($totalTransfer, 2),
            'total_efectivo'      => round($totalCash, 2),
            'total_terminal'      => round($totalCard, 2),
            'total_transferencia' => round($totalTransfer, 2),
            'completed_count'     => $completedCount,
            'total_completadas'   => $completedCount,
            'fecha_corte'         => $nowMexico->format('d/m/Y'),
            'fechaCorte'          => $nowMexico->format('d/m/Y'),
            'hora_corte'          => $nowMexico->format('H:i'),
            'driver'              => [
                'id'    => $driver?->id ?? $user->id,
                'name'  => $driver?->name ?? $user->name,
                'phone' => $driver?->phone ?? $user->phone ?? '',
            ],
            'orders'              => $deliveries,
            'deliveries'          => $deliveries,
            'entregas'            => $deliveries,
        ]);
    }

    public function notifyCut(Request $request)
    {
        return app(CloseDriverShiftController::class)->close($request);
    }

    public function getPerformance(Request $request)
    {
        $filtro = strtolower(trim((string) $request->input('filtro', 'mes')));

        $ordersQuery = Order::where('modality', 'delivery')->with('delivery.driver');

        // Aplicamos el filtro de tiempo exactamente igual que en los reportes
        switch ($filtro) {
            case 'hoy':
            case 'today':
                $ordersQuery->whereBetween('created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            case 'semana':
            case 'week':
                $ordersQuery->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'mes':
            case 'month':
                $ordersQuery->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3meses':
            case '3_meses':
            case '3months':
            case 'trimestre':
                $ordersQuery->whereBetween('created_at', [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            case 'ano':
            case 'año':
            case 'year':
                $ordersQuery->whereBetween('created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
                break;
            default:
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $ordersQuery->whereBetween('created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay()
                    ]);
                } else {
                    $ordersQuery->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                }
                break;
        }

        $orders = $ordersQuery->get();
        $totalDeliveryOrders = $orders->count();

        $deliveredOrders = $orders->filter(fn($o) => $this->normalizeStatus($o->status) === 'entregada');
        $cancelledOrders = $orders->filter(fn($o) => $this->normalizeStatus($o->status) === 'cancelada');

        // Compute minutes for each delivered order
        $timesInMinutes = $deliveredOrders->map(function ($o) {
            $created = $o->created_at;
            $updated = $o->updated_at ?: $o->created_at;
            $diffMs = $updated->timestamp - $created->timestamp;
            $mins = max(1, (int) round($diffMs / 60));
            return $mins;
        });

        $tiempoPromedioMinutos = $timesInMinutes->count() > 0 ? (int) round($timesInMinutes->avg()) : 0;
        $onTimeCount = $timesInMinutes->filter(fn($t) => $t <= 40)->count();
        $pedidosATiempoPorcentaje = $timesInMinutes->count() > 0 ? (int) round(($onTimeCount / $timesInMinutes->count()) * 100) : 0;

        // Today's delivered orders
        $deliveredToday = $deliveredOrders->filter(fn($o) => $o->created_at && $o->created_at->isToday());
        $todayTimes = $deliveredToday->map(function ($o) {
            $diffMs = ($o->updated_at ?: $o->created_at)->timestamp - $o->created_at->timestamp;
            return max(1, (int) round($diffMs / 60));
        });
        $todayOnTime = $todayTimes->filter(fn($t) => $t <= 40)->count();
        $cumplimientoHoyPorcentaje = $todayTimes->count() > 0 ? (int) round(($todayOnTime / $todayTimes->count()) * 100) : 0;

        $pedidosConDemora = $timesInMinutes->filter(fn($t) => $t > 40)->count();
        $totalConProblema = $pedidosConDemora + $cancelledOrders->count();

        $finishedCount = $deliveredOrders->count() + $cancelledOrders->count();
        $entregasCompletadasPorcentaje = $finishedCount > 0 ? (int) round(($deliveredOrders->count() / $finishedCount) * 100) : 0;

        // Service status
        if ($totalDeliveryOrders === 0) {
            $estadoServicio = "Sin datos disponibles";
        } elseif ($pedidosATiempoPorcentaje > 90) {
            $estadoServicio = "Óptimo";
        } elseif ($pedidosATiempoPorcentaje >= 70) {
            $estadoServicio = "Regular";
        } else {
            $estadoServicio = "Crítico";
        }

        // Weekly compliance (Lun to Dom of current week)
        $startOfWeek = now()->startOfWeek(); // Monday
        $daysMap = [
            0 => 'Lun',
            1 => 'Mar',
            2 => 'Mié',
            3 => 'Jue',
            4 => 'Vie',
            5 => 'Sáb',
            6 => 'Dom',
        ];

        $cumplimientoSemanal = [];
        for ($i = 0; $i < 7; $i++) {
            $currentDay = $startOfWeek->copy()->addDays($i);
            $dayOrders = $deliveredOrders->filter(fn($o) => $o->created_at && $o->created_at->isSameDay($currentDay));
            $dayTimes = $dayOrders->map(function ($o) {
                $diffMs = ($o->updated_at ?: $o->created_at)->timestamp - $o->created_at->timestamp;
                return max(1, (int) round($diffMs / 60));
            });
            $dayOnTime = $dayTimes->filter(fn($t) => $t <= 40)->count();
            $pct = $dayTimes->count() > 0 ? (int) round(($dayOnTime / $dayTimes->count()) * 100) : 0;

            $cumplimientoSemanal[] = [
                'dia'        => $daysMap[$i],
                'porcentaje' => $pct,
            ];
        }

        // Repartidores destacados
        $drivers = DeliveryDriver::with(['deliveries.order'])->get();
        $driversData = [];

        foreach ($drivers as $drv) {
            $drvOrders = $orders->filter(function ($o) use ($drv) {
                return $o->delivery && $o->delivery->driver_id == $drv->id;
            });

            $drvDelivered = $drvOrders->filter(fn($o) => $this->normalizeStatus($o->status) === 'entregada');
            $drvCancelled = $drvOrders->filter(fn($o) => $this->normalizeStatus($o->status) === 'cancelada');

            $drvTimes = $drvDelivered->map(function ($o) {
                $diffMs = ($o->updated_at ?: $o->created_at)->timestamp - $o->created_at->timestamp;
                return max(1, (int) round($diffMs / 60));
            });

            $drvAvgTime = $drvTimes->count() > 0 ? (int) round($drvTimes->avg()) : 0;
            $entregasCompletadas = $drvDelivered->count();
            $canceladosCount = $drvCancelled->count();

            $score = ($entregasCompletadas * 2) - ($canceladosCount * 3) - ($drvAvgTime / 10);
            $score = round($score, 1);

            $driversData[] = [
                'id'                   => $drv->id,
                'name'                 => $drv->name,
                'entregas_completadas' => $entregasCompletadas,
                'tiempo_promedio'      => $drvAvgTime,
                'cancelados'           => $canceladosCount,
                'score'                => $score,
            ];
        }

        usort($driversData, fn($a, $b) => $b['score'] <=> $a['score']);
        $top3Drivers = array_slice($driversData, 0, 3);

        return response()->json([
            'tiempo_promedio'          => $tiempoPromedioMinutos,
            'tiempo_promedio_minutos'  => $tiempoPromedioMinutos,
            'porcentaje_exito'         => $pedidosATiempoPorcentaje,
            'pedidos_demorados'        => $pedidosConDemora,
            'pedidos_con_demora'       => $pedidosConDemora,
            'total_entregas'           => $deliveredOrders->count(),
            'total_pedidos'            => $totalDeliveryOrders,
            'meta_minutos'             => 40,
            'meta_puntualidad'         => 90,
            'tiempos_puntualidad' => [
                'tiempo_promedio_minutos'     => $tiempoPromedioMinutos,
                'pedidos_a_tiempo_porcentaje' => $pedidosATiempoPorcentaje,
                'meta_minutos'                => 40,
                'meta_puntualidad'            => 90,
                'cumplimiento_hoy_porcentaje' => $cumplimientoHoyPorcentaje,
            ],
            'incidencias' => [
                'entregas_completadas_porcentaje' => $entregasCompletadasPorcentaje,
                'pedidos_con_demora'              => $pedidosConDemora,
                'total_con_problema'              => $totalConProblema,
                'estado_servicio'                 => $estadoServicio,
            ],
            'cumplimiento_semanal'   => $cumplimientoSemanal,
            'repartidores_destacados' => $top3Drivers,
        ]);
    }

    /**
     * KPIs de rendimiento de delivery filtradas por temporalidad.
     */
    public function kpisRendimiento(Request $request)
    {
        return app(ReportController::class)->kpisRendimiento($request);
    }

    /**
     * Métricas filtradas de rendimiento de delivery (KPIs de tiempo).
     */
    public function metricasRendimiento(Request $request)
    {
        return app(ReportController::class)->metricasRendimiento($request);
    }
}
