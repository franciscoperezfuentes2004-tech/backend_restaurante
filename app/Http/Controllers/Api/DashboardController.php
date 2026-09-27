<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Dish;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            // Support optional date parameters (from, to, date_range) safely
            $fromDate = null;
            $toDate = null;

            if ($request->filled('filter') && $request->query('filter') === 'this_week') {
                $fromDate = Carbon::now('America/Mexico_City')->startOfWeek();
                $toDate = Carbon::now('America/Mexico_City')->endOfWeek();
            } elseif ($request->filled('from') && $request->filled('to')) {
                try {
                    $fromDate = Carbon::parse($request->query('from'), 'America/Mexico_City')->startOfDay();
                    $toDate = Carbon::parse($request->query('to'), 'America/Mexico_City')->endOfDay();
                } catch (\Throwable $e) {
                    $fromDate = null;
                    $toDate = null;
                }
            }

            // 1. platillos_en_menu — count de platillos donde is_available = true
            $platillosEnMenu = Dish::where('is_available', true)->count();

            // 2. pedidos_recientes — count de pedidos de las últimas 24 horas (o rango filtrado)
            $pedidosQuery = Order::query();
            if ($fromDate && $toDate) {
                $pedidosQuery->whereBetween('created_at', [$fromDate, $toDate]);
            } else {
                $pedidosQuery->where('created_at', '>=', now()->subHours(24));
            }
            $pedidosRecientes = $pedidosQuery->count();

            // 3. proximas_reservaciones — count de reservaciones activas
            $todayStr = now()->toDateString();
            $reservacionesQuery = Reservation::query();
            if ($fromDate && $toDate) {
                $reservacionesQuery->whereBetween('reservation_date', [$fromDate->toDateString(), $toDate->toDateString()]);
            } else {
                $reservacionesQuery->whereDate('reservation_date', '>=', $todayStr);
            }
            $proximasReservaciones = $reservacionesQuery->whereIn('status', ['pending', 'confirmed'])->count();

            // 4. ingresos_del_dia — suma de total_amount de pedidos de hoy (o rango filtrado) con payment_status = paid
            $ingresosQuery = Order::query()->where('payment_status', 'paid');
            if ($fromDate && $toDate) {
                $ingresosQuery->whereBetween('created_at', [$fromDate, $toDate]);
            } else {
                $ingresosQuery->whereDate('created_at', $todayStr);
            }
            $ingresosDelDia = (float) ($ingresosQuery->sum('total_amount') ?? 0);

            // 5. ventas_semana — suma de total_amount por día de los últimos 7 días
            $dayNamesEs = [
                1 => 'Lun',
                2 => 'Mar',
                3 => 'Mié',
                4 => 'Jue',
                5 => 'Vie',
                6 => 'Sáb',
                7 => 'Dom',
            ];

            $ventasSemana = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dateStr = $date->toDateString();
                $dayNum = $date->dayOfWeekIso; // 1 (Mon) to 7 (Sun)
                $dayLabel = $dayNamesEs[$dayNum] ?? 'Día';

                $totalDay = (float) (Order::whereDate('created_at', $dateStr)
                    ->where('status', '!=', 'cancelled')
                    ->sum('total_amount') ?? 0);

                $ventasSemana[] = [
                    'dia'   => $dayLabel,
                    'total' => round($totalDay, 2),
                ];
            }

            // 6. pedidos_por_modalidad — count de pedidos agrupados por modality filtrado por rango de fecha
            $modalityQuery = Order::select('modality', DB::raw('COUNT(*) as total'))
                ->where('status', '!=', 'cancelled')
                ->groupBy('modality');

            if ($fromDate && $toDate) {
                $modalityQuery->whereBetween('created_at', [$fromDate, $toDate]);
            } else {
                $modalityQuery->where('created_at', '>=', Carbon::now('America/Mexico_City')->subDays(6)->startOfDay());
            }

            $modalityCountsRaw = $modalityQuery->get()
                ->pluck('total', 'modality')
                ->toArray();

            $modalityMap = [
                'delivery' => 'Delivery',
                'pick-up'  => 'Pick-up',
                'pickup'   => 'Pick-up',
                'mesa'     => 'Mesa',
                'dine-in'  => 'Mesa',
                'local'    => 'Mesa',
            ];

            $countsByNormalizedModality = [
                'Delivery' => 0,
                'Pick-up'  => 0,
                'Mesa'     => 0,
            ];

            foreach ($modalityCountsRaw as $mod => $count) {
                $normKey = $modalityMap[strtolower($mod ?? '')] ?? ucfirst(strtolower($mod ?? 'Mesa'));
                if (isset($countsByNormalizedModality[$normKey])) {
                    $countsByNormalizedModality[$normKey] += (int) $count;
                } else {
                    $countsByNormalizedModality[$normKey] = (int) $count;
                }
            }

            $pedidosPorModalidad = [
                ['name' => 'Delivery', 'value' => $countsByNormalizedModality['Delivery']],
                ['name' => 'Pick-up',  'value' => $countsByNormalizedModality['Pick-up']],
                ['name' => 'Mesa',     'value' => $countsByNormalizedModality['Mesa']],
            ];

            // 7. menu_actual — los últimos 10 platillos disponibles con su nombre, categoría, precio y estado (is_available)
            $menuActual = Dish::with('category')
                ->where('is_available', true)
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($dish) {
                    return [
                        'id'           => $dish->id,
                        'name'         => $dish->name,
                        'nombre'       => $dish->name,
                        'category'     => $dish->category ? $dish->category->name : 'Sin categoría',
                        'categoria'    => $dish->category ? $dish->category->name : 'Sin categoría',
                        'price'        => (float) $dish->price,
                        'precio'       => (float) $dish->price,
                        'is_available' => (bool) $dish->is_available,
                        'disponible'   => (bool) $dish->is_available,
                        'active'       => (bool) $dish->is_available,
                        'status'       => $dish->is_available ? 'disponible' : 'no_disponible',
                    ];
                });

            return response()->json([
                'platillos_en_menu'      => $platillosEnMenu,
                'dishes_count'           => $platillosEnMenu,
                'pedidos_recientes'      => $pedidosRecientes,
                'active_orders'          => $pedidosRecientes,
                'proximas_reservaciones' => $proximasReservaciones,
                'upcoming_reservations'  => $proximasReservaciones,
                'ingresos_del_dia'       => round($ingresosDelDia, 2),
                'total_sales'            => round($ingresosDelDia, 2),
                'ventas_semana'          => $ventasSemana,
                'pedidos_por_modalidad'  => $pedidosPorModalidad,
                'menu_actual'            => $menuActual,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error en DashboardController: ' . $e->getMessage());

            // Return safe fallback values so dashboard UI never crashes
            return response()->json([
                'platillos_en_menu'      => 0,
                'dishes_count'           => 0,
                'pedidos_recientes'      => 0,
                'active_orders'          => 0,
                'proximas_reservaciones' => 0,
                'pedidos_por_modalidad'  => [
                    ['name' => 'Delivery', 'value' => 0],
                    ['name' => 'Pick-up',  'value' => 0],
                    ['name' => 'Mesa',     'value' => 0],
                ],
                'menu_actual'            => [],
            ]);
        }
    }
}
