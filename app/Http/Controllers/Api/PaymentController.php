<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    /**
     * GET /api/admin/payments
     */
    public function index(Request $request)
    {
        $likeOp = DB::connection()->getDriverName() === 'pgsql' ? 'ilike' : 'like';

        $query = Payment::query();

        // 1. Filtro de Búsqueda de Texto (Folio o Cliente)
        $busqueda = $request->input('busqueda', $request->input('search'));
        $query->when(!empty($busqueda), function ($q) use ($busqueda, $likeOp) {
            $search = trim($busqueda);
            $cleanId = ltrim(preg_replace('/[^0-9]/', '', $search), '0');

            $q->where(function ($subQ) use ($search, $cleanId, $likeOp) {
                $subQ->where('folio', $likeOp, '%' . $search . '%')
                     ->orWhere('customer_name', $likeOp, '%' . $search . '%');

                if (!empty($cleanId)) {
                    $subQ->orWhere('id', (int) $cleanId);
                    if (strlen($cleanId) >= 8) {
                        $lastDigits = (int) substr($cleanId, 8);
                        if ($lastDigits > 0) {
                            $subQ->orWhere('id', $lastDigits);
                        }
                    }
                }
            });
        });

        // 2. Filtro de Tiempo (hoy, 30dias, 60dias)
        $tiempo = strtolower($request->input('tiempo', $request->input('filtro_tiempo', '')));
        $query->when(!empty($tiempo) && $tiempo !== 'todos' && $tiempo !== 'all', function ($q) use ($tiempo) {
            if ($tiempo === 'hoy' || $tiempo === 'today') {
                $q->whereDate('created_at', Carbon::today());
            } elseif ($tiempo === '30dias' || $tiempo === '30_dias' || $tiempo === '30days') {
                $q->where('created_at', '>=', Carbon::now()->subDays(30));
            } elseif ($tiempo === '60dias' || $tiempo === '60_dias' || $tiempo === '60days') {
                $q->where('created_at', '>=', Carbon::now()->subDays(60));
            }
        });

        // Rango de fechas explícito si viene fecha_inicio / fecha_fin y no se especificó tiempo predefinido
        $query->when(empty($tiempo) && $request->filled('fecha_inicio') && $request->filled('fecha_fin'), function ($q) use ($request) {
            $q->whereBetween('created_at', [
                Carbon::parse($request->fecha_inicio)->startOfDay(),
                Carbon::parse($request->fecha_fin)->endOfDay()
            ]);
        })->when(empty($tiempo) && $request->filled('fecha_inicio') && !$request->filled('fecha_fin'), function ($q) use ($request) {
            $q->where('created_at', '>=', Carbon::parse($request->fecha_inicio)->startOfDay());
        })->when(empty($tiempo) && !$request->filled('fecha_inicio') && $request->filled('fecha_fin'), function ($q) use ($request) {
            $q->where('created_at', '<=', Carbon::parse($request->fecha_fin)->endOfDay());
        });

        // 3. Filtro de Método de Pago
        $metodo = strtolower($request->input('metodo', $request->input('payment_method', '')));
        $query->when(!empty($metodo) && $metodo !== 'todos' && $metodo !== 'all', function ($q) use ($metodo) {
            if ($metodo === 'cash' || $metodo === 'efectivo') {
                $q->whereIn('payment_method', ['cash', 'efectivo']);
            } elseif ($metodo === 'terminal' || $metodo === 'card' || $metodo === 'tarjeta') {
                $q->whereIn('payment_method', ['terminal', 'card', 'tarjeta']);
            } elseif ($metodo === 'transfer' || $metodo === 'transferencia') {
                $q->whereIn('payment_method', ['transfer', 'transferencia']);
            } else {
                $q->where('payment_method', $metodo);
            }
        });

        // 4. Filtro de Estado
        $estado = strtolower($request->input('estado', $request->input('payment_status', '')));
        $query->when(!empty($estado) && $estado !== 'todos' && $estado !== 'all', function ($q) use ($estado) {
            if ($estado === 'paid' || $estado === 'pagado') {
                $q->where(function ($sub) {
                    $sub->where('payment_status', 'paid')
                        ->orWhere('status', 'completed');
                });
            } elseif ($estado === 'pending' || $estado === 'pendiente') {
                $q->where('payment_status', 'pending');
            } elseif ($estado === 'cancelled' || $estado === 'cancelado' || $estado === 'failed') {
                $q->where(function ($sub) {
                    $sub->whereIn('payment_status', ['cancelled', 'failed'])
                        ->orWhere('status', 'cancelled');
                });
            } else {
                $q->where('payment_status', $estado);
            }
        });

        // Clonamos la consulta para calcular los totales exactos de lo que se filtró
        $totalRegistros = (clone $query)->count();
        $sumaTotal = (clone $query)->where(function ($q) {
            $q->whereNotIn('payment_status', ['cancelled', 'failed'])
              ->where('status', '!=', 'cancelled');
        })->sum('total_amount');

        // Desglose por método de pago para tarjetas KPI
        $efectivo = (clone $query)->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })->whereIn('payment_method', ['cash', 'efectivo'])->sum('total_amount');

        $terminal = (clone $query)->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })->whereIn('payment_method', ['terminal', 'card', 'tarjeta'])->sum('total_amount');

        $transferencia = (clone $query)->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })->whereIn('payment_method', ['transfer', 'transferencia'])->sum('total_amount');

        $totalCobrado = (clone $query)->where(function ($q) {
            $q->where('payment_status', 'paid')->orWhere('status', 'completed');
        })->sum('total_amount');

        $perPage = (int) $request->input('per_page', 8);
        $pagosPaginados = $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate($perPage);

        // Mapeo de la colección de la paginación con folio de pago y compatibilidad de campos
        $pagosPaginados->getCollection()->transform(function ($order) {
            $methodRaw = strtolower($order->payment_method ?: 'cash');
            if (in_array($methodRaw, ['cash', 'efectivo'])) {
                $methodNormalized = 'cash';
            } elseif (in_array($methodRaw, ['terminal', 'card', 'tarjeta'])) {
                $methodNormalized = 'terminal';
            } elseif (in_array($methodRaw, ['transfer', 'transferencia'])) {
                $methodNormalized = 'transfer';
            } else {
                $methodNormalized = 'cash';
            }

            $statusRaw = strtolower($order->payment_status ?: 'pending');
            if ($statusRaw === 'paid' || strtolower($order->status) === 'completed') {
                $statusNormalized = 'paid';
            } elseif (in_array($statusRaw, ['cancelled', 'failed']) || strtolower($order->status) === 'cancelled') {
                $statusNormalized = 'cancelled';
            } else {
                $statusNormalized = 'pending';
            }

            $amount = (float) $order->total_amount;
            $folioPago = self::generarFolioPago($order);

            return [
                'id'             => $order->id,
                'folio'          => $folioPago,
                'customer_name'  => $order->customer_name ?: 'Cliente General',
                'cliente_nombre' => $order->customer_name ?: 'Cliente General',
                'payment_method' => $methodNormalized,
                'metodo'         => $methodNormalized,
                'total_amount'   => round($amount, 2),
                'monto'          => round($amount, 2),
                'payment_status' => $statusNormalized,
                'estado'         => $statusNormalized,
                'created_at'     => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'pagos' => $pagosPaginados,
            'metricas' => [
                'total_registros' => (int) $totalRegistros,
                'suma_total'      => round((float) $sumaTotal, 2),
            ],
            'resumen' => [
                'total_cobrado'   => round((float) $totalCobrado, 2),
                'efectivo'        => round((float) $efectivo, 2),
                'terminal'        => round((float) $terminal, 2),
                'transferencia'   => round((float) $transferencia, 2),
                'total_registros' => (int) $totalRegistros,
                'suma_total'      => round((float) $sumaTotal, 2),
            ]
        ]);
    }

    /**
     * GET /api/admin/payments/{id}
     */
    public function show($id)
    {
        $cleanId = trim((string) $id);
        $order = null;

        if (is_numeric($cleanId)) {
            $order = Order::with(['items.dish', 'items.extras.extra'])->find((int) $cleanId);
        }

        if (!$order && str_starts_with(strtoupper($cleanId), 'PAG')) {
            $onlyNums = preg_replace('/[^0-9]/', '', $cleanId);
            if (strlen($onlyNums) >= 8) {
                $targetId = (int) substr($onlyNums, 8);
                if ($targetId > 0) {
                    $order = Order::with(['items.dish', 'items.extras.extra'])->find($targetId);
                }
            }
        }

        if (!$order) {
            $order = Order::with(['items.dish', 'items.extras.extra'])
                ->where('folio', $cleanId)
                ->orWhere('dispatch_token', strtoupper($cleanId))
                ->first();
        }

        if (!$order) {
            return response()->json(['message' => 'Pago / Pedido no encontrado'], 404);
        }

        $methodRaw = strtolower($order->payment_method ?: 'cash');
        if (in_array($methodRaw, ['cash', 'efectivo'])) {
            $methodNormalized = 'cash';
        } elseif (in_array($methodRaw, ['terminal', 'card', 'tarjeta'])) {
            $methodNormalized = 'terminal';
        } elseif (in_array($methodRaw, ['transfer', 'transferencia'])) {
            $methodNormalized = 'transfer';
        } else {
            $methodNormalized = 'cash';
        }

        $statusRaw = strtolower($order->payment_status ?: 'pending');
        if ($statusRaw === 'paid' || strtolower($order->status) === 'completed') {
            $statusNormalized = 'paid';
        } elseif (in_array($statusRaw, ['cancelled', 'failed']) || strtolower($order->status) === 'cancelled') {
            $statusNormalized = 'cancelled';
        } else {
            $statusNormalized = 'pending';
        }

        $items = $order->items->map(function ($item) {
            $extrasList = $item->extras->map(function ($e) {
                return [
                    'name'  => $e->extra ? $e->extra->name : 'Extra',
                    'price' => round((float) $e->price, 2),
                ];
            })->values()->toArray();

            return [
                'dish_name' => $item->dish ? $item->dish->name : 'Platillo',
                'quantity'  => (int) $item->quantity,
                'price'     => round((float) $item->price, 2),
                'extras'    => $extrasList,
            ];
        })->values()->toArray();

        $subtotal = round((float) $order->total_amount, 2);
        $descuentos = 0.00;
        $total = $subtotal - $descuentos;

        return response()->json([
            'id'               => $order->id,
            'folio'            => self::generarFolioPago($order),
            'customer_name'    => $order->customer_name ?: 'Cliente General',
            'customer_phone'   => $order->customer_phone ?: null,
            'customer_address' => $order->customer_address ?: null,
            'payment_method'   => $methodNormalized,
            'payment_status'   => $statusNormalized,
            'total_amount'     => round((float) $order->total_amount, 2),
            'created_at'       => $order->created_at ? $order->created_at->format('Y-m-d H:i:s') : null,
            'items'            => $items,
            'subtotal'         => $subtotal,
            'descuentos'       => $descuentos,
            'total'            => round($total, 2),
            'modality'         => $order->modality ?: 'delivery',
        ]);
    }

    /**
     * Generar folio de pago compacto sin guiones: PAGYYYYMMDDXXXX (ej. PAG202609170063)
     */
    public static function generarFolioPago($order): string
    {
        $fecha = ($order->created_at ? Carbon::parse($order->created_at) : Carbon::now())->format('Ymd');
        $numeroFormateado = str_pad($order->id ?? 1, 4, '0', STR_PAD_LEFT);
        return 'PAG' . $fecha . $numeroFormateado;
    }
}
