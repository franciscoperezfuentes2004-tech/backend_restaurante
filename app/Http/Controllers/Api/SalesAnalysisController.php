<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesAnalysisController extends Controller
{
    /**
     * GET /api/admin/sales
     */
    public function index(Request $request)
    {
        $periodo = $request->query('periodo', 'hoy');
        if (!in_array($periodo, ['hoy', 'semana', 'mes', 'anio'])) {
            $periodo = 'hoy';
        }

        $now = Carbon::now();

        // Determine current and previous date ranges based on period
        switch ($periodo) {
            case 'semana':
                $startDate = $now->copy()->startOfWeek();
                $endDate   = $now->copy()->endOfWeek();
                $prevStartDate = $now->copy()->subWeek()->startOfWeek();
                $prevEndDate   = $now->copy()->subWeek()->endOfWeek();
                break;
            case 'mes':
                $startDate = $now->copy()->startOfMonth();
                $endDate   = $now->copy()->endOfMonth();
                $prevStartDate = $now->copy()->subMonth()->startOfMonth();
                $prevEndDate   = $now->copy()->subMonth()->endOfMonth();
                break;
            case 'anio':
                $startDate = $now->copy()->startOfYear();
                $endDate   = $now->copy()->endOfYear();
                $prevStartDate = $now->copy()->subYear()->startOfYear();
                $prevEndDate   = $now->copy()->subYear()->endOfYear();
                break;
            case 'hoy':
            default:
                $startDate = $now->copy()->startOfDay();
                $endDate   = $now->copy()->endOfDay();
                $prevStartDate = $now->copy()->subDay()->startOfDay();
                $prevEndDate   = $now->copy()->subDay()->endOfDay();
                break;
        }

        // --- Current Period Metrics ---
        $paidOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            });

        $ventasPeriodo = round((float) $paidOrdersQuery->sum('total_amount'), 2);

        $allOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate]);
        $totalPedidos = $allOrdersQuery->count();

        $ticketPromedio = $totalPedidos > 0 ? round($ventasPeriodo / $totalPedidos, 2) : 0.00;

        $clientesCount = (int) Order::whereBetween('created_at', [$startDate, $endDate])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->distinct('customer_name')
            ->count('customer_name');

        if ($clientesCount === 0 && $totalPedidos > 0) {
            $clientesCount = $totalPedidos;
        }

        // --- Previous Period Metrics ---
        $prevPaidOrdersQuery = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            });

        $prevVentas = round((float) $prevPaidOrdersQuery->sum('total_amount'), 2);

        $prevAllOrdersQuery = Order::whereBetween('created_at', [$prevStartDate, $prevEndDate]);
        $prevPedidos = $prevAllOrdersQuery->count();

        $prevTicket = $prevPedidos > 0 ? round($prevVentas / $prevPedidos, 2) : 0.00;

        $prevClientes = (int) Order::whereBetween('created_at', [$prevStartDate, $prevEndDate])
            ->whereNotNull('customer_name')
            ->where('customer_name', '!=', '')
            ->distinct('customer_name')
            ->count('customer_name');
        if ($prevClientes === 0 && $prevPedidos > 0) {
            $prevClientes = $prevPedidos;
        }

        // Variations
        $variacionVentas = $this->calcVariation($ventasPeriodo, $prevVentas);
        $variacionPedidos = $this->calcVariation($totalPedidos, $prevPedidos);
        $variacionTicket = $this->calcVariation($ticketPromedio, $prevTicket);
        $variacionClientes = $this->calcVariation($clientesCount, $prevClientes);

        // --- Sales Trend (Tendencia de Ventas) ---
        $tendenciaVentas = $this->buildSalesTrend($periodo, $startDate, $endDate);

        // --- Peak Hours (Horarios Pico) ---
        $horariosPico = $this->buildPeakHours($startDate, $endDate);

        // --- Top Dishes (Top Platillos) ---
        $topPlatillos = $this->buildTopDishes($startDate, $endDate);

        // --- Categories (Categorías) ---
        $categorias = $this->buildCategoryDistribution($startDate, $endDate, $ventasPeriodo);

        // --- Resumen Inteligente ---
        $resumenInteligente = $this->buildSmartSummary(
            $ventasPeriodo,
            $totalPedidos,
            $ticketPromedio,
            $variacionVentas,
            $clientesCount,
            $horariosPico,
            $topPlatillos,
            $categorias
        );

        return response()->json([
            'resumen' => [
                'ventas_periodo'    => $ventasPeriodo,
                'total_pedidos'     => $totalPedidos,
                'ticket_promedio'   => $ticketPromedio,
                'clientes_atendidos'=> $clientesCount,
                'variacion_ventas'  => $variacionVentas,
                'variacion_pedidos' => $variacionPedidos,
                'variacion_ticket'  => $variacionTicket,
                'variacion_clientes'=> $variacionClientes,
            ],
            'resumen_inteligente' => $resumenInteligente,
            'tendencia_ventas'   => $tendenciaVentas,
            'horarios_pico'      => $horariosPico,
            'top_platillos'      => $topPlatillos,
            'categorias'         => $categorias,
        ]);
    }

    private function calcVariation($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100.0 : 0.0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    private function buildSalesTrend($periodo, Carbon $startDate, Carbon $endDate)
    {
        $trend = [];

        if ($periodo === 'hoy') {
            // Group by hour (08:00 to 23:00)
            for ($h = 8; $h <= 23; $h++) {
                $hourStr = sprintf('%02d:00', $h);
                $hStart = $startDate->copy()->hour($h)->minute(0)->second(0);
                $hEnd   = $startDate->copy()->hour($h)->minute(59)->second(59);

                $sum = Order::whereBetween('created_at', [$hStart, $hEnd])
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                          ->orWhere('status', 'completed');
                    })
                    ->sum('total_amount');

                $trend[] = [
                    'fecha' => $hourStr,
                    'total' => round((float) $sum, 2)
                ];
            }
        } elseif ($periodo === 'semana') {
            // 7 days of the week
            $curr = $startDate->copy();
            $dayNames = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
            while ($curr->lte($endDate)) {
                $dStart = $curr->copy()->startOfDay();
                $dEnd   = $curr->copy()->endOfDay();
                $dayLabel = $dayNames[$curr->dayOfWeek] . ' ' . $curr->format('d/m');

                $sum = Order::whereBetween('created_at', [$dStart, $dEnd])
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                          ->orWhere('status', 'completed');
                    })
                    ->sum('total_amount');

                $trend[] = [
                    'fecha' => $dayLabel,
                    'total' => round((float) $sum, 2)
                ];
                $curr->addDay();
            }
        } elseif ($periodo === 'mes') {
            // Days of the month
            $curr = $startDate->copy();
            while ($curr->lte($endDate)) {
                $dStart = $curr->copy()->startOfDay();
                $dEnd   = $curr->copy()->endOfDay();

                $sum = Order::whereBetween('created_at', [$dStart, $dEnd])
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                          ->orWhere('status', 'completed');
                    })
                    ->sum('total_amount');

                $trend[] = [
                    'fecha' => $curr->format('d M'),
                    'total' => round((float) $sum, 2)
                ];
                $curr->addDay();
            }
        } else {
            // year: 12 months
            $monthNames = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
            for ($m = 1; $m <= 12; $m++) {
                $mStart = $startDate->copy()->month($m)->startOfMonth();
                $mEnd   = $startDate->copy()->month($m)->endOfMonth();

                $sum = Order::whereBetween('created_at', [$mStart, $mEnd])
                    ->where(function ($q) {
                        $q->where('payment_status', 'paid')
                          ->orWhere('status', 'completed');
                    })
                    ->sum('total_amount');

                $trend[] = [
                    'fecha' => $monthNames[$m - 1],
                    'total' => round((float) $sum, 2)
                ];
            }
        }

        return $trend;
    }

    private function buildPeakHours(Carbon $startDate, Carbon $endDate)
    {
        // 1. Fetch all orders within the date range
        $orders = Order::whereBetween('created_at', [$startDate, $endDate])
            ->select(['id', 'created_at', 'total_amount', 'status', 'payment_status'])
            ->get();

        // 2. Initialize default operating hours (08:00 to 23:00)
        $hoursData = [];
        for ($h = 8; $h <= 23; $h++) {
            $hoursData[$h] = [
                'pedidos' => 0,
                'ventas'  => 0.0,
            ];
        }

        // 3. Aggregate orders by local hour (America/Mexico_City)
        $tz = new \DateTimeZone('America/Mexico_City');
        $totalOrdersCount = 0;

        foreach ($orders as $order) {
            if (!$order->created_at) continue;

            $hour = (int) Carbon::parse($order->created_at)->setTimezone($tz)->format('H');
            
            // If order occurred outside standard 08-23 range, dynamically add the slot
            if (!isset($hoursData[$hour])) {
                $hoursData[$hour] = [
                    'pedidos' => 0,
                    'ventas'  => 0.0,
                ];
            }

            $hoursData[$hour]['pedidos']++;
            $totalOrdersCount++;

            // Sum sales for paid or completed orders
            $isPaid = $order->payment_status === 'paid' || $order->status === 'completed';
            if ($isPaid) {
                $hoursData[$hour]['ventas'] += (float) ($order->total_amount ?? 0);
            }
        }

        // Sort by hour asc
        ksort($hoursData);

        // Find max orders for relative intensity calculation
        $maxOrders = 0;
        foreach ($hoursData as $data) {
            if ($data['pedidos'] > $maxOrders) {
                $maxOrders = $data['pedidos'];
            }
        }

        // 4. Format final list with realistic metrics and levels
        $result = [];
        foreach ($hoursData as $h => $data) {
            $count = $data['pedidos'];
            $ventas = round($data['ventas'], 2);
            $ticketPromedio = $count > 0 ? round($ventas / $count, 2) : 0.0;
            $pct = $totalOrdersCount > 0 ? round(($count / $totalOrdersCount) * 100, 1) : 0.0;

            // Dynamic intensity level relative to activity
            $nivel = 'bajo';
            if ($maxOrders > 0 && $count > 0) {
                if ($count === $maxOrders) {
                    $nivel = 'pico';
                } elseif ($count >= ceil($maxOrders * 0.65)) {
                    $nivel = 'alto';
                } elseif ($count >= ceil($maxOrders * 0.35)) {
                    $nivel = 'medio';
                } else {
                    $nivel = 'bajo';
                }
            }

            $hNext = ($h + 1) % 24;
            $result[] = [
                'hora'            => sprintf('%02d:00', $h),
                'rango'           => sprintf('%02d:00 - %02d:00', $h, $hNext),
                'pedidos'         => $count,
                'ventas'          => $ventas,
                'ticket_promedio' => $ticketPromedio,
                'porcentaje'      => $pct,
                'nivel'           => $nivel
            ];
        }

        return $result;
    }

    private function buildTopDishes(Carbon $startDate, Carbon $endDate)
    {
        $items = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->with(['dish.category'])
        ->get();

        $grouped = [];
        foreach ($items as $item) {
            $dishName = $item->dish ? $item->dish->name : 'Platillo Desconocido';
            $categoryName = $item->dish && $item->dish->category ? $item->dish->category->name : 'General';
            $lineRevenue = (float) ($item->quantity * $item->price);

            if (!isset($grouped[$dishName])) {
                $grouped[$dishName] = [
                    'name'      => $dishName,
                    'ingresos'  => 0.0,
                    'cantidad'  => 0,
                    'categoria' => $categoryName,
                ];
            }

            $grouped[$dishName]['ingresos'] += $lineRevenue;
            $grouped[$dishName]['cantidad'] += (int) $item->quantity;
        }

        // Sort by revenue desc
        usort($grouped, function ($a, $b) {
            return $b['ingresos'] <=> $a['ingresos'];
        });

        // Format rounded values and take top 10
        return array_map(function ($item) {
            return [
                'name'      => $item['name'],
                'ingresos'  => round($item['ingresos'], 2),
                'cantidad'  => $item['cantidad'],
                'categoria' => $item['categoria'],
            ];
        }, array_slice($grouped, 0, 10));
    }

    private function buildCategoryDistribution(Carbon $startDate, Carbon $endDate, float $totalVentas)
    {
        $categories = Category::all();
        $catSales = [];

        $items = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        })
        ->with(['dish.category'])
        ->get();

        foreach ($items as $item) {
            $categoryName = $item->dish && $item->dish->category ? $item->dish->category->name : 'General';
            $lineRevenue = (float) ($item->quantity * $item->price);

            if (!isset($catSales[$categoryName])) {
                $catSales[$categoryName] = 0.0;
            }
            $catSales[$categoryName] += $lineRevenue;
        }

        $overallSum = array_sum($catSales);
        if ($overallSum == 0 && $totalVentas > 0) {
            $overallSum = $totalVentas;
        }

        $result = [];
        foreach ($catSales as $catName => $revenue) {
            $pct = $overallSum > 0 ? round(($revenue / $overallSum) * 100, 1) : 0;
            $result[] = [
                'name'          => $catName,
                'ingresos'      => round($revenue, 2),
                'participacion' => $pct,
            ];
        }

        usort($result, function ($a, $b) {
            return $b['ingresos'] <=> $a['ingresos'];
        });

        return $result;
    }

    private function buildSmartSummary(
        float $ventas,
        int $pedidos,
        float $ticket,
        float $variacionVentas,
        int $clientes,
        array $horariosPico,
        array $topPlatillos,
        array $categorias
    ) {
        // 1. Crecimiento Comercial
        $trendSign = $variacionVentas >= 0 ? '+' : '';
        $crecimientoComercial = sprintf(
            "Las ventas alcanzaron $%s a través de %d pedidos. El ticket promedio se estableció en $%s, con una tendencia de %s%s%% respecto al período anterior.",
            number_format($ventas, 2),
            $pedidos,
            number_format($ticket, 2),
            $trendSign,
            number_format($variacionVentas, 1)
        );

        // 2. Demanda Horarios
        $peak = null;
        foreach ($horariosPico as $h) {
            if (!$peak || $h['pedidos'] > $peak['pedidos']) {
                $peak = $h;
            }
        }

        if ($peak && $peak['pedidos'] > 0) {
            $demandaHorarios = sprintf(
                "Se brindó servicio a %d clientes. El horario pico de mayor demanda fue a las %s (%s) con %d pedidos ($%s en ventas).",
                $clientes,
                $peak['hora'],
                $peak['rango'] ?? ($peak['hora'] . 'h'),
                $peak['pedidos'],
                number_format($peak['ventas'] ?? 0, 2)
            );
        } else {
            $demandaHorarios = sprintf(
                "Se brindó servicio a %d clientes. Sin pedidos registrados en el período para determinar horario pico.",
                $clientes
            );
        }

        // 3. Rendimiento Menú
        $topCat = count($categorias) > 0 ? $categorias[0] : null;
        $topDish = count($topPlatillos) > 0 ? $topPlatillos[0] : null;

        if ($topCat || $topDish) {
            $catText = $topCat ? sprintf("La categoría '%s' lideró con $%s (%s%%).", $topCat['name'], number_format($topCat['ingresos'], 2), $topCat['participacion']) : "";
            $dishText = $topDish ? sprintf("El platillo estrella fue '%s' con %d unidades vendidas ($%s).", $topDish['name'], $topDish['cantidad'], number_format($topDish['ingresos'], 2)) : "";
            $rendimientoMenu = trim($catText . " " . $dishText);
        } else {
            $rendimientoMenu = "Sin datos de categorías o platillos disponibles para este período.";
        }

        return [
            'crecimiento_comercial' => $crecimientoComercial,
            'demanda_horarios'     => $demandaHorarios,
            'rendimiento_menu'     => $rendimientoMenu,
        ];
    }
}
