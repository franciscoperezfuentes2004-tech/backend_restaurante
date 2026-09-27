<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\IndexStockRequest;
use App\Http\Requests\StoreStockEntryRequest;
use App\Models\Ingredient;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

class StockController extends Controller
{
    /**
     * Calculate item status
     */
    private function calculateStatus(Stock $stock): string
    {
        if ($stock->quantity <= 0) {
            return 'agotado';
        }

        if ($stock->expiry_date) {
            $exp = Carbon::parse($stock->expiry_date);
            $today = Carbon::today();
            if ($exp->gte($today) && $exp->diffInDays($today) <= 3) {
                return 'proximo_vencer';
            }
        }

        if ($stock->quantity <= $stock->min_quantity) {
            return 'stock_bajo';
        }

        return 'disponible';
    }

    /**
     * Auto sync stock records for all ingredients
     */
    private function syncIngredientsStock(): void
    {
        $ingredientIds = Ingredient::pluck('id');

        foreach ($ingredientIds as $ingId) {
            Stock::firstOrCreate(
                ['ingredient_id' => $ingId],
                ['quantity' => 0, 'min_quantity' => 0]
            );
        }
    }

    /**
     * GET /api/admin/stock (or /admin/stock)
     */
    public function index(IndexStockRequest $request)
    {
        $this->syncIngredientsStock();

        $validated = $request->validated();
        $query = Stock::with(['ingredient.supplier', 'supplier', 'lastUpdatedBy']);

        // Search filter (sanitizado previamente en IndexStockRequest con strip_tags)
        if (isset($validated['search']) && $validated['search'] !== '') {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->whereHas('ingredient', function ($iq) use ($search) {
                    $iq->where('name', 'like', "%{$search}%")
                       ->orWhere('category', 'like', "%{$search}%")
                       ->orWhereHas('supplier', function ($isq) use ($search) {
                           $isq->where('company_name', 'like', "%{$search}%")
                               ->orWhere('contact_name', 'like', "%{$search}%");
                       });
                })->orWhereHas('supplier', function ($sq) use ($search) {
                    $sq->where('company_name', 'like', "%{$search}%")
                       ->orWhere('contact_name', 'like', "%{$search}%");
                });
            });
        }

        // Category filter
        if (!empty($validated['category']) && $validated['category'] !== 'all') {
            $category = $validated['category'];
            $query->whereHas('ingredient', function ($q) use ($category) {
                $q->where('category', $category);
            });
        }

        // Supplier filter
        if (!empty($validated['supplier_id']) && $validated['supplier_id'] !== 'all') {
            $query->where('supplier_id', $validated['supplier_id']);
        }

        // Date filter
        if (!empty($validated['fecha'])) {
            $query->whereDate('updated_at', $validated['fecha']);
        }

        $allStockItems = $query->get();

        // Map and filter status if needed
        $stockList = [];
        $stockBajoCount = 0;
        $agotadosCount = 0;
        $proximosVencerCount = 0;
        $totalCapital = 0;
        $alertasCriticas = [];
        $accionesRecomendadas = [];

        // Latest unit cost map per ingredient
        $latestCosts = StockMovement::whereNotNull('cost_per_unit')
            ->where('cost_per_unit', '>', 0)
            ->orderBy('created_at', 'desc')
            ->pluck('cost_per_unit', 'ingredient_id')
            ->toArray();

        foreach ($allStockItems as $item) {
            if (!$item->ingredient) continue;

            $status = $this->calculateStatus($item);

            if ($status === 'stock_bajo') $stockBajoCount++;
            if ($status === 'agotado') $agotadosCount++;
            if ($status === 'proximo_vencer') $proximosVencerCount++;

            $unitCost = $latestCosts[$item->ingredient_id] ?? 0;
            $totalCapital += ($item->quantity * $unitCost);

            if (in_array($status, ['stock_bajo', 'agotado', 'proximo_vencer'])) {
                $alertasCriticas[] = [
                    'id'              => $item->id,
                    'ingredient_id'   => $item->ingredient_id,
                    'ingredient_name' => $item->ingredient->name,
                    'quantity'        => $item->quantity,
                    'unit'            => $item->ingredient->unit,
                    'status'          => $status,
                ];
            }

            // Filter status if requested
            if ($request->filled('estado') && $request->estado !== 'all') {
                $filterStatus = $request->estado;
                if ($filterStatus === 'proximo_a_vencer') $filterStatus = 'proximo_vencer';
                if ($status !== $filterStatus) {
                    continue;
                }
            }

            $stockList[] = [
                'id'              => $item->id,
                'ingredient_id'   => $item->ingredient_id,
                'ingredient_name' => $item->ingredient->name,
                'category'        => $item->ingredient->category,
                'unit'            => $item->ingredient->unit,
                'quantity'        => (float) $item->quantity,
                'min_quantity'    => (float) $item->min_quantity,
                'expiry_date'     => $item->expiry_date ? $item->expiry_date->format('Y-m-d') : null,
                'status'          => $status,
                'supplier_id'     => $item->supplier_id,
                'supplier_name'   => $item->supplier ? $item->supplier->name : ($item->ingredient->supplier ? $item->ingredient->supplier->name : null),
                'last_updated'    => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
            ];
        }

        // Salud porcentaje calculation
        $totalIngs = count($allStockItems);
        $saludPorcentaje = 100;
        if ($totalIngs > 0) {
            $lossValue = ($agotadosCount * 1.0) + ($stockBajoCount * 0.5) + ($proximosVencerCount * 0.25);
            $saludPorcentaje = max(0, min(100, (int) round((($totalIngs - $lossValue) / $totalIngs) * 100)));
        }

        // Recommended actions
        if ($agotadosCount > 0) {
            $accionesRecomendadas[] = "Reabastecer urgentemente los {$agotadosCount} ingredientes agotados.";
        }
        if ($stockBajoCount > 0) {
            $accionesRecomendadas[] = "Generar orden de compra para {$stockBajoCount} productos en stock bajo.";
        }
        if ($proximosVencerCount > 0) {
            $accionesRecomendadas[] = "Priorizar en cocina {$proximosVencerCount} insumos próximos a vencer.";
        }
        if (empty($accionesRecomendadas)) {
            $accionesRecomendadas[] = "El inventario se encuentra en un nivel óptimo de stock.";
        }

        return response()->json([
            'stock'   => $stockList,
            'resumen' => [
                'total_ingredientes'    => $totalIngs,
                'stock_bajo'            => $stockBajoCount,
                'agotados'              => $agotadosCount,
                'proximos_vencer'       => $proximosVencerCount,
                'capital_almacen'       => round($totalCapital, 2),
                'salud_porcentaje'      => $saludPorcentaje,
                'alertas_criticas'      => $alertasCriticas,
                'acciones_recomendadas' => $accionesRecomendadas,
            ]
        ]);
    }

    /**
     * POST /api/admin/stock/entry
     */
    public function storeEntry(StoreStockEntryRequest $request)
    {
        $validated = $request->validated();

        $ingredientId = (int) $validated['ingredient_id'];
        $qty = (float) $validated['quantity'];
        $supplierId = (int) $validated['supplier_id'];
        $totalCost = (float) ($validated['total_cost'] ?? ($validated['cost_total'] ?? 0));

        // Calcular el costo por unidad al guardar en la base de datos
        $costPerUnit = $qty > 0 ? round($totalCost / $qty, 4) : 0.00;

        $expiryDate = $validated['expiration_date'] ?? ($validated['expiry_date'] ?? null);
        $notes = isset($validated['notes']) && $validated['notes'] !== null
            ? trim(strip_tags((string) $validated['notes']))
            : null;
        $userId = auth()->id();

        // 1. Obtener o crear registro en stock
        $stock = Stock::firstOrCreate(
            ['ingredient_id' => $ingredientId],
            ['quantity' => 0, 'min_quantity' => 0]
        );

        // Actualizar stock
        $stock->quantity = (float) $stock->quantity + $qty;
        $stock->supplier_id = $supplierId;
        if ($expiryDate) {
            $stock->expiry_date = $expiryDate;
        }
        $stock->last_updated_by = $userId;
        $stock->save();

        // 2. Registrar movimiento de stock
        $movement = StockMovement::create([
            'ingredient_id' => $ingredientId,
            'type'          => 'entrada',
            'quantity'      => $qty,
            'cost_per_unit' => $costPerUnit,
            'supplier_id'   => $supplierId,
            'expiry_date'   => $expiryDate,
            'notes'         => $notes,
            'created_by'    => $userId,
        ]);

        return response()->json([
            'message'       => 'Entrada de mercancía registrada correctamente',
            'stock'         => $stock,
            'movement'      => $movement,
            'cost_per_unit' => $costPerUnit,
        ], 201);
    }

    /**
     * POST /api/admin/stock/adjustment
     */
    public function storeAdjustment(AdjustStockRequest $request)
    {
        $validated = $request->validated();

        $ingredientId = (int) $validated['ingredient_id'];
        $rawType = $validated['movement_type'] ?? ($validated['type'] ?? 'ajuste');
        $qty = (float) $validated['quantity'];
        $notes = isset($validated['notes']) && $validated['notes'] !== null
            ? trim(strip_tags((string) $validated['notes']))
            : null;
        $expiryDate = $validated['expiration_date'] ?? ($validated['expiry_date'] ?? null);
        $userId = auth()->id();

        // Mapear alias a valores del esquema en base de datos
        $isMerma = in_array($rawType, ['waste', 'merma', 'desperdicio'], true);
        $type = match ($rawType) {
            'waste', 'merma' => 'merma',
            'manual_adjustment', 'ajuste' => 'ajuste',
            'salida' => 'salida',
            'entrada' => 'entrada',
            default => $rawType,
        };

        $stock = Stock::firstOrCreate(
            ['ingredient_id' => $ingredientId],
            ['quantity' => 0, 'min_quantity' => 0]
        );

        // ── LÓGICA DE NEGOCIO (Integridad Matemática del Inventario) ────────
        // Si el tipo es Merma / Desperdicio, verificar existencia actual antes de restar.
        // Si la existencia es menor a la merma solicitada, abortar con HTTP 422.
        if ($isMerma && $qty > (float) $stock->quantity) {
            throw ValidationException::withMessages([
                'quantity' => ['La merma no puede ser mayor a la existencia actual'],
            ]);
        }

        // Adjust quantity based on type
        if ($type === 'merma' || $type === 'salida') {
            $adjustQty = abs($qty);
            $stock->quantity = max(0, (float) $stock->quantity - $adjustQty);
            $movementQty = -$adjustQty;
        } elseif ($type === 'ajuste') {
            // Check if absolute target quantity or delta is passed
            if (!empty($validated['is_absolute'])) {
                $stock->quantity = max(0, $qty);
                $movementQty = $qty;
            } else {
                $stock->quantity = max(0, (float) $stock->quantity + $qty);
                $movementQty = $qty;
            }
        } else { // entrada
            $stock->quantity = (float) $stock->quantity + abs($qty);
            $movementQty = abs($qty);
        }

        if ($expiryDate) {
            $stock->expiry_date = $expiryDate;
        }
        $stock->last_updated_by = $userId;
        $stock->save();

        $movement = StockMovement::create([
            'ingredient_id' => $ingredientId,
            'type'          => $type,
            'quantity'      => $movementQty,
            'expiry_date'   => $expiryDate,
            'notes'         => $notes,
            'created_by'    => $userId,
        ]);

        return response()->json([
            'message'  => 'Ajuste de inventario registrado correctamente',
            'stock'    => $stock,
            'movement' => $movement,
        ]);
    }

    /**
     * PATCH /api/admin/stock/{ingredient_id}/min
     */
    public function updateMin(Request $request, $ingredient_id)
    {
        $validated = $request->validate([
            'min_quantity' => 'required|numeric|gte:0',
        ], [
            'min_quantity.required' => 'La cantidad mínima es obligatoria.',
            'min_quantity.gte'      => 'La cantidad mínima debe ser mayor o igual a 0.',
        ]);

        $stock = Stock::firstOrCreate(
            ['ingredient_id' => $ingredient_id],
            ['quantity' => 0, 'min_quantity' => 0]
        );

        $stock->min_quantity = (float) $validated['min_quantity'];
        $stock->last_updated_by = auth()->id();
        $stock->save();

        return response()->json([
            'message'      => 'Stock mínimo actualizado correctamente',
            'min_quantity' => $stock->min_quantity,
        ]);
    }

    /**
     * GET /api/admin/stock/movements
     */
    public function getMovements(Request $request)
    {
        $query = StockMovement::with(['ingredient', 'supplier', 'createdBy']);

        if ($request->filled('ingredient_id') && $request->ingredient_id !== 'all') {
            $query->where('ingredient_id', $request->ingredient_id);
        }

        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        if ($request->filled('fecha_inicio')) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $movements = $query->orderBy('created_at', 'desc')->get();

        $formatted = $movements->map(function ($m) {
            return [
                'id'              => $m->id,
                'ingredient_id'   => $m->ingredient_id,
                'ingredient_name' => $m->ingredient ? $m->ingredient->name : 'Desconocido',
                'type'            => $m->type,
                'quantity'        => (float) $m->quantity,
                'cost_per_unit'   => $m->cost_per_unit !== null ? (float) $m->cost_per_unit : null,
                'supplier_name'   => $m->supplier ? $m->supplier->name : null,
                'expiry_date'     => $m->expiry_date ? $m->expiry_date->format('Y-m-d') : null,
                'notes'           => $m->notes,
                'created_by_name' => $m->createdBy ? $m->createdBy->name : 'Sistema',
                'created_at'      => $m->created_at ? $m->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'movements' => $formatted
        ]);
    }

    /**
     * GET /api/admin/stock/metrics
     */
    public function getMetrics(Request $request)
    {
        $daysMap = [
            1 => 'Lun',
            2 => 'Mar',
            3 => 'Mié',
            4 => 'Jue',
            5 => 'Vie',
            6 => 'Sáb',
            7 => 'Dom',
        ];

        // 1. Impacto Financiero (Últimos 7 días)
        $impactoFinanciero = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $dayOfWeek = $daysMap[$date->dayOfWeekIso] ?? 'Día';
            $dateStr = $date->format('Y-m-d');

            $dayMovements = StockMovement::whereDate('created_at', $dateStr)->get();

            $dayConsumo = 0;
            $dayPerdidas = 0;

            foreach ($dayMovements as $m) {
                $cost = (float) ($m->cost_per_unit ?? 0);
                if (in_array($m->type, ['salida', 'entrada'])) {
                    $dayConsumo += abs($m->quantity) * $cost;
                }
                if ($m->type === 'merma') {
                    $dayPerdidas += abs($m->quantity) * ($cost > 0 ? $cost : 0);
                }
            }

            $impactoFinanciero[] = [
                'dia'      => $dayOfWeek,
                'consumo'  => round($dayConsumo, 2),
                'perdidas' => round($dayPerdidas, 2),
            ];
        }

        // 2. Movimientos de los últimos 30 días para distribución, top_gasto y top_mermas
        $thirtyDaysAgo = Carbon::today()->subDays(30);
        $movements30Days = StockMovement::with(['ingredient'])
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->get();

        // Distribución de pérdidas por categoría
        $catPerdidasMap = [];
        $totalPerdidas30 = 0;

        // Top gasto (entradas)
        $gastoMap = []; // ing_id => ['name' => ..., 'unit' => ..., 'monto' => ...]

        // Top mermas
        $mermaMap = []; // ing_id => ['name' => ..., 'unit' => ..., 'monto' => ...]

        foreach ($movements30Days as $m) {
            if (!$m->ingredient) continue;

            $ingId = $m->ingredient_id;
            $ingName = $m->ingredient->name;
            $catName = $m->ingredient->category ?: 'General';
            $unit = $m->ingredient->unit ?: 'unidades';
            $cost = (float) ($m->cost_per_unit ?? 0);

            if ($m->type === 'entrada') {
                $costTotal = abs($m->quantity) * $cost;
                if (!isset($gastoMap[$ingId])) {
                    $gastoMap[$ingId] = [
                        'ingredient_name' => $ingName,
                        'unidad'          => $unit,
                        'gasto_total'     => 0,
                    ];
                }
                $gastoMap[$ingId]['gasto_total'] += $costTotal;
            }

            if ($m->type === 'merma') {
                $lossTotal = abs($m->quantity) * $cost;
                $totalPerdidas30 += $lossTotal;
                $catPerdidasMap[$catName] = ($catPerdidasMap[$catName] ?? 0) + $lossTotal;

                if (!isset($mermaMap[$ingId])) {
                    $mermaMap[$ingId] = [
                        'ingredient_name' => $ingName,
                        'unidad'          => $unit,
                        'merma_total'     => 0,
                    ];
                }
                $mermaMap[$ingId]['merma_total'] += $lossTotal;
            }
        }

        // Formatear distribucion_perdidas
        $distribucionPerdidas = [];
        foreach ($catPerdidasMap as $cat => $monto) {
            if ($monto <= 0) continue;
            $porcentaje = $totalPerdidas30 > 0 ? round(($monto / $totalPerdidas30) * 100, 1) : 0;
            $distribucionPerdidas[] = [
                'categoria'  => $cat,
                'porcentaje' => $porcentaje,
                'monto'      => round($monto, 2),
            ];
        }
        usort($distribucionPerdidas, fn($a, $b) => $b['monto'] <=> $a['monto']);

        // Formatear top_gasto
        $topGasto = collect($gastoMap)
            ->map(fn($item) => [
                'ingredient_name' => $item['ingredient_name'],
                'gasto_total'     => round($item['gasto_total'], 2),
                'unidad'          => $item['unidad'],
            ])
            ->filter(fn($item) => $item['gasto_total'] > 0)
            ->sortByDesc('gasto_total')
            ->take(5)
            ->values()
            ->all();

        // Formatear top_mermas
        $topMermas = collect($mermaMap)
            ->map(fn($item) => [
                'ingredient_name' => $item['ingredient_name'],
                'merma_total'     => round($item['merma_total'], 2),
                'unidad'          => $item['unidad'],
            ])
            ->filter(fn($item) => $item['merma_total'] > 0)
            ->sortByDesc('merma_total')
            ->take(5)
            ->values()
            ->all();

        return response()->json([
            'impacto_financiero'    => $impactoFinanciero,
            'distribucion_perdidas' => $distribucionPerdidas,
            'top_gasto'             => $topGasto,
            'top_mermas'            => $topMermas,
        ]);
    }
}
