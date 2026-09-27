<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Ingredient;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CostAnalysisController extends Controller
{
    /**
     * GET /api/admin/costs
     */
    public function index(Request $request)
    {
        $now = Carbon::now();
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        $startOfPrevMonth = $now->copy()->subMonth()->startOfMonth();
        $endOfPrevMonth = $now->copy()->subMonth()->endOfMonth();

        // 1. Current Month Stock Entries
        $currentEntries = StockMovement::where('type', 'entrada')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();
        $costosTotales = round((float) $currentEntries->sum(function ($m) {
            return (float) $m->quantity * (float) ($m->cost_per_unit ?: 0);
        }), 2);

        // 2. Previous Month Stock Entries (for variation)
        $prevEntries = StockMovement::where('type', 'entrada')
            ->whereBetween('created_at', [$startOfPrevMonth, $endOfPrevMonth])
            ->get();
        $prevCostosTotales = round((float) $prevEntries->sum(function ($m) {
            return (float) $m->quantity * (float) ($m->cost_per_unit ?: 0);
        }), 2);

        $variacionCostos = $prevCostosTotales > 0
            ? round((($costosTotales - $prevCostosTotales) / $prevCostosTotales) * 100, 1)
            : 0;

        // 3. Stock Mermas for Current Month
        $currentMermasMovements = StockMovement::with('ingredient')
            ->where('type', 'merma')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();

        $mermasCost = round((float) $currentMermasMovements->sum(function ($m) {
            return (float) $m->quantity * (float) ($m->cost_per_unit ?: 0);
        }), 2);

        $totalGastosMes = $costosTotales + $mermasCost;

        $porcentajeIngredientes = $totalGastosMes > 0
            ? round(($costosTotales / $totalGastosMes) * 100, 1)
            : 0;

        $porcentajeMermas = $totalGastosMes > 0
            ? round(($mermasCost / $totalGastosMes) * 100, 1)
            : 0;

        // 4. Current Month Revenue (Ingresos Totales)
        $currentPaidOrders = Order::whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            })->get();

        $ingresosTotales = round((float) $currentPaidOrders->sum('total_amount'), 2);

        // Previous Month Revenue
        $prevPaidOrders = Order::whereBetween('created_at', [$startOfPrevMonth, $endOfPrevMonth])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            })->get();

        $prevIngresosTotales = round((float) $prevPaidOrders->sum('total_amount'), 2);

        // 5. Ganancia Bruta & Margen Neto
        $gananciaBruta = round($ingresosTotales - $costosTotales, 2);
        $prevGananciaBruta = round($prevIngresosTotales - $prevCostosTotales, 2);

        $variacionGanancia = $prevGananciaBruta != 0
            ? round((($gananciaBruta - $prevGananciaBruta) / abs($prevGananciaBruta)) * 100, 1)
            : 0;

        $margenNeto = $ingresosTotales > 0
            ? round(($gananciaBruta / $ingresosTotales) * 100, 1)
            : 0;

        // 6. Resumen Financiero
        $porcentajeIngresosFin = $ingresosTotales > 0 ? 100 : 0;
        $porcentajeCostosFin = $ingresosTotales > 0 ? round(($costosTotales / $ingresosTotales) * 100, 1) : 0;
        $porcentajeGananciaFin = $ingresosTotales > 0 ? round(($gananciaBruta / $ingresosTotales) * 100, 1) : 0;

        // 7. Costos por Categoría de Ingrediente
        $entriesWithIngredient = StockMovement::with('ingredient')
            ->where('type', 'entrada')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->get();

        $categoryCostMap = [];
        foreach ($entriesWithIngredient as $m) {
            $catName = ($m->ingredient && $m->ingredient->category) ? $m->ingredient->category : 'General';
            $c = (float) $m->quantity * (float) ($m->cost_per_unit ?: 0);
            $categoryCostMap[$catName] = ($categoryCostMap[$catName] ?? 0) + $c;
        }

        $costosPorCategoria = [];
        foreach ($categoryCostMap as $cat => $amt) {
            $amtRound = round($amt, 2);
            $pct = $costosTotales > 0 ? round(($amtRound / $costosTotales) * 100, 1) : 0;
            $costosPorCategoria[] = [
                'categoria'  => $cat,
                'costo'      => $amtRound,
                'porcentaje' => $pct,
            ];
        }

        usort($costosPorCategoria, function ($a, $b) {
            return $b['costo'] <=> $a['costo'];
        });

        // 8. Tendencia de Costos (Últimos 6 meses)
        $tendenciaCostos = [];
        for ($i = 5; $i >= 0; $i--) {
            $mStart = $now->copy()->subMonths($i)->startOfMonth();
            $mEnd   = $now->copy()->subMonths($i)->endOfMonth();

            $mCost = (float) StockMovement::where('type', 'entrada')
                ->whereBetween('created_at', [$mStart, $mEnd])
                ->sum(\DB::raw('quantity * COALESCE(cost_per_unit, 0)'));

            $mRev = (float) Order::whereBetween('created_at', [$mStart, $mEnd])
                ->where(function ($q) {
                    $q->where('payment_status', 'paid')
                      ->orWhere('status', 'completed');
                })->sum('total_amount');

            $monthNameEs = match ($mStart->format('n')) {
                '1' => 'Ene', '2' => 'Feb', '3' => 'Mar', '4' => 'Abr', '5' => 'May', '6' => 'Jun',
                '7' => 'Jul', '8' => 'Ago', '9' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic',
                default => $mStart->format('M')
            };

            $tendenciaCostos[] = [
                'mes'      => $monthNameEs,
                'costos'   => round($mCost, 2),
                'ingresos' => round($mRev, 2),
            ];
        }

        // 9. Mermas por Ingrediente
        $mermasGrouped = [];
        foreach ($currentMermasMovements as $m) {
            $ingId = $m->ingredient_id;
            $ingName = $m->ingredient ? $m->ingredient->name : 'Ingrediente';
            $catName = ($m->ingredient && $m->ingredient->category) ? $m->ingredient->category : 'General';
            $unitName = ($m->ingredient && $m->ingredient->unit) ? $m->ingredient->unit : 'uds';
            $c = (float) $m->quantity * (float) ($m->cost_per_unit ?: 0);
            $qty = (float) $m->quantity;

            if (!isset($mermasGrouped[$ingId])) {
                $mermasGrouped[$ingId] = [
                    'ingrediente'      => $ingName,
                    'categoria'        => $catName,
                    'cantidad_perdida' => 0,
                    'unidad'           => $unitName,
                    'costo_merma'      => 0.0,
                ];
            }

            $mermasGrouped[$ingId]['cantidad_perdida'] += $qty;
            $mermasGrouped[$ingId]['costo_merma'] += $c;
        }

        $mermasPorIngrediente = [];
        foreach ($mermasGrouped as $item) {
            $costoMermaRound = round($item['costo_merma'], 2);
            $pctMerma = $mermasCost > 0 ? round(($costoMermaRound / $mermasCost) * 100, 1) : 0;
            $mermasPorIngrediente[] = [
                'ingrediente'      => $item['ingrediente'],
                'categoria'        => $item['categoria'],
                'cantidad_perdida' => round($item['cantidad_perdida'], 2),
                'unidad'           => $item['unidad'],
                'costo_merma'      => $costoMermaRound,
                'porcentaje_total' => $pctMerma,
            ];
        }

        usort($mermasPorIngrediente, function ($a, $b) {
            return $b['costo_merma'] <=> $a['costo_merma'];
        });

        // 10. Costo por Platillo
        $soldItems = OrderItem::with('dish')
            ->whereHas('order', function ($q) use ($startOfMonth, $endOfMonth) {
                $q->whereBetween('created_at', [$startOfMonth, $endOfMonth]);
            })
            ->select('dish_id', \DB::raw('SUM(quantity) as total_qty'), \DB::raw('AVG(price) as avg_price'))
            ->groupBy('dish_id')
            ->get();

        if ($soldItems->isEmpty()) {
            $soldItems = OrderItem::with('dish')
                ->select('dish_id', \DB::raw('SUM(quantity) as total_qty'), \DB::raw('AVG(price) as avg_price'))
                ->groupBy('dish_id')
                ->limit(10)
                ->get();
        }

        $costoPorPlatillo = [];
        foreach ($soldItems as $item) {
            if (!$item->dish) continue;

            $precioVenta = round((float) ($item->avg_price ?: $item->dish->price), 2);

            // Default 30% cost of ingredients if not linked explicitly
            $costoIngredientes = round($precioVenta * 0.35, 2);
            $margen = round($precioVenta - $costoIngredientes, 2);
            $margenPct = $precioVenta > 0 ? round(($margen / $precioVenta) * 100, 1) : 0;

            $costoPorPlatillo[] = [
                'platillo'           => $item->dish->name,
                'costo_ingredientes' => $costoIngredientes,
                'precio_venta'       => $precioVenta,
                'margen'             => $margen,
                'margen_porcentaje'  => $margenPct,
                'unidades_vendidas'  => (int) $item->total_qty,
            ];
        }

        usort($costoPorPlatillo, function ($a, $b) {
            return $b['unidades_vendidas'] <=> $a['unidades_vendidas'];
        });

        return response()->json([
            'resumen' => [
                'costos_totales'          => $costosTotales,
                'variacion_costos'        => $variacionCostos,
                'ingredientes'            => $costosTotales,
                'porcentaje_ingredientes' => $porcentajeIngredientes,
                'mermas'                  => $mermasCost,
                'porcentaje_mermas'       => $porcentajeMermas,
                'ganancia_bruta'          => $gananciaBruta,
                'variacion_ganancia'      => $variacionGanancia,
                'margen_neto'             => $margenNeto,
            ],
            'resumen_financiero' => [
                'ingresos_totales'    => $ingresosTotales,
                'costos_totales'      => $costosTotales,
                'ganancia_bruta'      => $gananciaBruta,
                'porcentaje_ingresos' => $porcentajeIngresosFin,
                'porcentaje_costos'   => $porcentajeCostosFin,
                'porcentaje_ganancia' => $porcentajeGananciaFin,
            ],
            'costos_por_categoria'   => $costosPorCategoria,
            'tendencia_costos'       => $tendenciaCostos,
            'mermas_por_ingrediente' => $mermasPorIngrediente,
            'costo_por_platillo'     => $costoPorPlatillo,
        ]);
    }
}
