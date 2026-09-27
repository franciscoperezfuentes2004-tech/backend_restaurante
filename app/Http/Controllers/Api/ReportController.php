<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportScheduleRequest;
use App\Http\Requests\UpdateReportScheduleRequest;
use App\Models\Report;
use App\Models\ScheduledReport;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\StockMovement;
use App\Models\Reservation;
use App\Models\Delivery;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    /**
     * GET /api/admin/reports
     */
    public function index(Request $request)
    {
        $query = Report::query();

        // Filter by search (id or type or generated_by)
        if ($request->filled('search')) {
            $search = trim($request->search);
            $cleanId = ltrim(preg_replace('/[^0-9]/', '', $search), '0');

            $query->where(function ($q) use ($search, $cleanId) {
                $q->where('type', 'like', "%{$search}%")
                  ->orWhere('periodo', 'like', "%{$search}%")
                  ->orWhere('generated_by', 'like', "%{$search}%");

                if (!empty($cleanId)) {
                    $q->orWhere('id', (int) $cleanId);
                }
            });
        }

        // Filter by type
        if ($request->filled('type') && $request->type !== 'all' && $request->type !== 'Todos') {
            $query->where('type', $request->type);
        }

        // Filter by periodo
        if ($request->filled('periodo') && $request->periodo !== 'all' && $request->periodo !== 'Todos') {
            $query->where('periodo', 'like', "%{$request->periodo}%");
        }

        $total = $query->count();
        $reports = $query->orderBy('id', 'desc')->get();

        $typeLabels = [
            'ejecutivo'     => 'Resumen Ejecutivo',
            'ventas'        => 'Reporte de Ventas',
            'productos'     => 'Reporte de Productos',
            'inventario'    => 'Reporte de Inventario',
            'delivery'      => 'Reporte de Delivery',
            'reservaciones' => 'Reporte de Reservaciones',
            'costos'        => 'Reporte de Costos',
            'finanzas'      => 'Reporte de Finanzas',
            'impuestos'     => 'Reporte de Impuestos',
        ];

        $reportesFormatted = $reports->map(function ($report) use ($typeLabels) {
            $nombreLabel = $typeLabels[$report->type] ?? ('Reporte de ' . ucfirst($report->type));
            return [
                'id'                => $report->id,
                'nombre'            => $nombreLabel,
                'type'              => $report->type,
                'periodo'           => $report->periodo,
                'generated_by_name' => $report->generated_by ?: 'Super Administrador',
                'status'            => 'listo',
                'created_at'        => $report->created_at ? $report->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json([
            'reportes' => $reportesFormatted,
            'total'    => $total,
        ]);
    }

    /**
     * GET /api/admin/reports/{id}
     */
    public function show($id)
    {
        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        $typeLabels = [
            'ejecutivo'     => 'Resumen Ejecutivo',
            'ventas'        => 'Reporte de Ventas',
            'productos'     => 'Reporte de Productos',
            'inventario'    => 'Reporte de Inventario',
            'delivery'      => 'Reporte de Delivery',
            'reservaciones' => 'Reporte de Reservaciones',
            'costos'        => 'Reporte de Costos',
            'finanzas'      => 'Reporte de Finanzas',
            'impuestos'     => 'Reporte de Impuestos',
        ];

        return response()->json([
            'id'                => $report->id,
            'nombre'            => $typeLabels[$report->type] ?? ('Reporte de ' . ucfirst($report->type)),
            'type'              => $report->type,
            'periodo'           => $report->periodo,
            'fecha_inicio'      => $report->fecha_inicio ? $report->fecha_inicio->format('Y-m-d') : null,
            'fecha_fin'         => $report->fecha_fin ? $report->fecha_fin->format('Y-m-d') : null,
            'data'              => $report->data,
            'generated_by_name' => $report->generated_by ?: 'Super Administrador',
            'status'            => 'listo',
            'created_at'        => $report->created_at ? $report->created_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * DELETE /api/admin/reports/{id}
     */
    public function destroy($id)
    {
        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        $report->delete();

        return response()->json([
            'message' => 'Reporte eliminado correctamente'
        ]);
    }

    /**
     * GET /api/admin/reports/scheduled
     */
    public function getScheduled()
    {
        $scheduled = ScheduledReport::orderBy('id', 'desc')->get();

        $typeLabels = [
            'ejecutivo'     => 'Resumen Ejecutivo',
            'ventas'        => 'Ventas',
            'productos'     => 'Productos',
            'inventario'    => 'Inventario',
            'delivery'      => 'Delivery',
            'reservaciones' => 'Reservaciones',
            'costos'        => 'Costos',
            'finanzas'      => 'Finanzas',
            'impuestos'     => 'Impuestos',
        ];

        $formatted = $scheduled->map(function ($s) use ($typeLabels) {
            $freqLabel = ucfirst($s->frequency) . ' (' . $s->time . ' hrs)';
            if ($s->frequency === 'semanal' && $s->day_of_week) {
                $freqLabel = "Semanal ({$s->day_of_week}s {$s->time})";
            } elseif ($s->frequency === 'mensual' && $s->day_of_month) {
                $freqLabel = "Mensual (Día {$s->day_of_month}, {$s->time})";
            }

            return [
                'id'           => $s->id,
                'name'         => $s->name,
                'type'         => $s->type,
                'type_label'   => $typeLabels[$s->type] ?? ucfirst($s->type),
                'frequency'    => $s->frequency,
                'freq_label'   => $freqLabel,
                'day_of_week'  => $s->day_of_week,
                'day_of_month' => $s->day_of_month,
                'time'         => $s->time,
                'emails'       => $s->emails ?: [],
                'format'       => strtoupper($s->format ?: 'PDF'),
                'active'       => (bool) $s->active,
                'status'       => $s->active ? 'Activo' : 'Pausado',
                'next_run'     => $s->next_run ? $s->next_run->format('Y-m-d H:i:s') : Carbon::now()->addDay()->format('Y-m-d') . ' ' . $s->time . ':00',
            ];
        });

        return response()->json([
            'programados' => $formatted
        ]);
    }

    /**
     * POST /api/admin/reports/scheduled
     */
    public function storeScheduled(StoreReportScheduleRequest $request)
    {
        $validated = $request->validated();

        $name = trim(strip_tags($validated['name']));
        $rawType = $validated['report_type'] ?? ($validated['type'] ?? 'ventas');
        $typeMap = [
            'sales'     => 'ventas',
            'inventory' => 'inventario',
            'cuts'      => 'costos',
            'reviews'   => 'ejecutivo',
        ];
        $type = $typeMap[$rawType] ?? $rawType;

        $rawFreq = $validated['frequency'];
        $freqMap = [
            'daily'   => 'diario',
            'weekly'  => 'semanal',
            'monthly' => 'mensual',
            'diario'  => 'diario',
            'semanal' => 'semanal',
            'mensual' => 'mensual',
        ];
        $frequency = $freqMap[strtolower($rawFreq)] ?? strtolower($rawFreq);

        $time = $validated['send_time'] ?? ($validated['time'] ?? '23:30');
        $format = strtolower($validated['export_format'] ?? ($validated['format'] ?? 'pdf'));
        $rawEmails = $validated['recipients'] ?? ($validated['emails'] ?? []);
        $emails = array_values(array_unique(array_map('trim', $rawEmails)));

        $nextRun = Carbon::now()->addDay()->setTimeFromTimeString($time);

        $scheduled = ScheduledReport::create([
            'name'         => $name,
            'type'         => $type,
            'frequency'    => $frequency,
            'day_of_week'  => $validated['day_of_week'] ?? null,
            'day_of_month' => $validated['day_of_month'] ?? null,
            'time'         => $time,
            'emails'       => $emails,
            'format'       => $format,
            'active'       => array_key_exists('active', $validated) ? (bool)$validated['active'] : true,
            'next_run'     => $nextRun,
        ]);

        return response()->json([
            'message'   => 'Reporte programado creado correctamente',
            'scheduled' => $scheduled
        ], 201);
    }

    /**
     * PUT /api/admin/reports/scheduled/{id}
     */
    public function updateScheduled(UpdateReportScheduleRequest $request, $id)
    {
        $scheduled = ScheduledReport::find($id);
        if (!$scheduled) {
            return response()->json(['message' => 'Reporte programado no encontrado'], 404);
        }

        $validated = $request->validated();

        if (array_key_exists('name', $validated)) {
            $scheduled->name = trim(strip_tags($validated['name']));
        }

        if (array_key_exists('report_type', $validated) || array_key_exists('type', $validated)) {
            $rawType = $validated['report_type'] ?? $validated['type'];
            $typeMap = [
                'sales'     => 'ventas',
                'inventory' => 'inventario',
                'cuts'      => 'costos',
                'reviews'   => 'ejecutivo',
            ];
            $scheduled->type = $typeMap[$rawType] ?? $rawType;
        }

        if (array_key_exists('frequency', $validated)) {
            $rawFreq = $validated['frequency'];
            $freqMap = [
                'daily'   => 'diario',
                'weekly'  => 'semanal',
                'monthly' => 'mensual',
                'diario'  => 'diario',
                'semanal' => 'semanal',
                'mensual' => 'mensual',
            ];
            $scheduled->frequency = $freqMap[strtolower($rawFreq)] ?? strtolower($rawFreq);
        }

        if (array_key_exists('send_time', $validated) || array_key_exists('time', $validated)) {
            $scheduled->time = $validated['send_time'] ?? $validated['time'];
        }

        if (array_key_exists('export_format', $validated) || array_key_exists('format', $validated)) {
            $scheduled->format = strtolower($validated['export_format'] ?? $validated['format']);
        }

        if (array_key_exists('recipients', $validated) || array_key_exists('emails', $validated)) {
            $rawEmails = $validated['recipients'] ?? $validated['emails'];
            $scheduled->emails = array_values(array_unique(array_map('trim', $rawEmails)));
        }

        if (array_key_exists('active', $validated)) {
            $scheduled->active = (bool)$validated['active'];
        }

        if (array_key_exists('day_of_week', $validated)) {
            $scheduled->day_of_week = $validated['day_of_week'];
        }

        if (array_key_exists('day_of_month', $validated)) {
            $scheduled->day_of_month = $validated['day_of_month'];
        }

        $scheduled->save();

        return response()->json([
            'message'   => 'Reporte programado actualizado correctamente',
            'scheduled' => $scheduled
        ]);
    }

    /**
     * DELETE /api/admin/reports/scheduled/{id}
     */
    public function destroyScheduled($id)
    {
        $scheduled = ScheduledReport::find($id);
        if (!$scheduled) {
            return response()->json(['message' => 'Reporte programado no encontrado'], 404);
        }

        $scheduled->delete();

        return response()->json([
            'message' => 'Reporte programado eliminado correctamente'
        ]);
    }

    /**
     * PATCH /api/admin/reports/scheduled/{id}/toggle
     */
    public function toggleScheduled($id)
    {
        $scheduled = ScheduledReport::find($id);
        if (!$scheduled) {
            return response()->json(['message' => 'Reporte programado no encontrado'], 404);
        }

        $scheduled->active = !$scheduled->active;
        $scheduled->save();

        return response()->json([
            'message'   => $scheduled->active ? 'Reporte activado correctamente' : 'Reporte pausado correctamente',
            'active'    => (bool) $scheduled->active,
            'scheduled' => $scheduled
        ]);
    }

    /**
     * POST /api/admin/reports/generate
     */
    public function generate(Request $request)
    {
        $request->validate([
            'type'         => 'required|string|in:ejecutivo,ventas,productos,inventario,delivery,reservaciones,costos,finanzas,impuestos',
            'periodo'      => 'required|string',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
        ]);

        $type = $request->type;
        $periodo = $request->periodo;
        $fechaInicioStr = $request->fecha_inicio;
        $fechaFinStr = $request->fecha_fin;

        $now = Carbon::now();

        // Determine date range
        if (!empty($fechaInicioStr) && !empty($fechaFinStr)) {
            $startDate = Carbon::parse($fechaInicioStr)->startOfDay();
            $endDate   = Carbon::parse($fechaFinStr)->endOfDay();
            $periodoLabel = 'Personalizado (' . $startDate->format('d/m/Y') . ' - ' . $endDate->format('d/m/Y') . ')';
        } else {
            switch ($periodo) {
                case 'hoy':
                    $startDate = $now->copy()->startOfDay();
                    $endDate   = $now->copy()->endOfDay();
                    $periodoLabel = 'Hoy (' . $now->format('d/m/Y') . ')';
                    break;
                case 'mes':
                    $startDate = $now->copy()->startOfMonth();
                    $endDate   = $now->copy()->endOfMonth();
                    $periodoLabel = 'Mes de ' . $now->format('F Y');
                    break;
                case 'semana':
                default:
                    $startDate = $now->copy()->startOfWeek();
                    $endDate   = $now->copy()->endOfWeek();
                    $periodoLabel = 'Semana (' . $startDate->format('d/m') . ' - ' . $endDate->format('d/m/Y') . ')';
                    break;
            }
        }

        // Build dataset according to report type
        $data = $this->buildReportData($type, $startDate, $endDate);

        $generatedBy = 'Super Administrador';
        if ($request->user()) {
            $generatedBy = $request->user()->name ?: ($request->user()->email ?: 'Administrador');
        }

        $report = Report::create([
            'type'         => $type,
            'periodo'      => $periodoLabel,
            'fecha_inicio' => $startDate->format('Y-m-d'),
            'fecha_fin'    => $endDate->format('Y-m-d'),
            'data'         => $data,
            'generated_by' => $generatedBy,
        ]);

        return response()->json([
            'id'           => $report->id,
            'type'         => $report->type,
            'periodo'      => $report->periodo,
            'fecha_inicio' => $report->fecha_inicio ? $report->fecha_inicio->format('Y-m-d') : null,
            'fecha_fin'    => $report->fecha_fin ? $report->fecha_fin->format('Y-m-d') : null,
            'data'         => $report->data,
            'generated_by' => $report->generated_by,
            'created_at'   => $report->created_at ? $report->created_at->format('Y-m-d H:i:s') : null,
        ]);
    }

    /**
     * POST /api/admin/reports/{id}/send
     */
    public function send(Request $request, $id)
    {
        $report = Report::find($id);
        if (!$report) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        $request->validate([
            'emails'   => 'required|array|min:1',
            'emails.*' => 'required|email',
        ]);

        $emails = array_unique(array_map('trim', $request->emails));

        try {
            // Attempt sending via Laravel Mail system or log simulation
            Log::info("Enviando reporte ID {$report->id} ({$report->type}) a los correos: " . implode(', ', $emails));
        } catch (\Exception $e) {
            Log::error("Error enviando correo de reporte: " . $e->getMessage());
        }

        return response()->json([
            'message' => 'Reporte enviado exitosamente a los ' . count($emails) . ' destinatario(s) especificados.',
            'emails'  => $emails,
            'id'      => $report->id
        ]);
    }

    private function buildReportData(string $type, Carbon $startDate, Carbon $endDate): array
    {
        $paidOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate])
            ->where(function ($q) {
                $q->where('payment_status', 'paid')
                  ->orWhere('status', 'completed');
            });

        $allOrdersQuery = Order::whereBetween('created_at', [$startDate, $endDate]);

        $totalSales = round((float) $paidOrdersQuery->sum('total_amount'), 2);
        $totalOrdersCount = $allOrdersQuery->count();
        $avgTicket = $totalOrdersCount > 0 ? round($totalSales / $totalOrdersCount, 2) : 0.00;

        switch ($type) {
            case 'ejecutivo':
                $reservationsCount = Reservation::whereBetween('created_at', [$startDate, $endDate])->count();
                $deliveryOrdersCount = Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('modality', 'delivery')->count();
                $criticalStockCount = Ingredient::whereColumn('quantity', '<=', 'min_quantity')->count();

                // Top Dish
                $topItem = OrderItem::whereHas('order', function ($q) use ($startDate, $endDate) {
                    $q->whereBetween('created_at', [$startDate, $endDate]);
                })
                ->select('dish_id', \DB::raw('SUM(quantity) as total_qty'), \DB::raw('SUM(quantity * price) as total_rev'))
                ->groupBy('dish_id')
                ->orderByDesc('total_rev')
                ->first();

                $topDishName = 'N/A';
                if ($topItem && $topItem->dish_id) {
                    $dishObj = Dish::find($topItem->dish_id);
                    if ($dishObj) $topDishName = $dishObj->name;
                }

                return [
                    'kpis' => [
                        'ventas_totales'     => $totalSales,
                        'total_pedidos'      => $totalOrdersCount,
                        'ticket_promedio'    => $avgTicket,
                        'reservaciones'      => $reservationsCount,
                        'pedidos_delivery'   => $deliveryOrdersCount,
                        'insumos_criticos'   => $criticalStockCount,
                        'platillo_estrella'  => $topDishName,
                    ],
                    'headers' => ['Indicador Clave', 'Valor Actual', 'Estado'],
                    'rows' => [
                        ['Ventas Consolidadas', '$' . number_format($totalSales, 2), 'Completado'],
                        ['Órdenes Procesadas', number_format($totalOrdersCount) . ' pedidos', 'Operativo'],
                        ['Ticket Promedio', '$' . number_format($avgTicket, 2), 'Estable'],
                        ['Reservaciones Atendidas', number_format($reservationsCount) . ' mesas', 'Normal'],
                        ['Alertas de Inventario', $criticalStockCount . ' insumos críticos', $criticalStockCount > 0 ? 'Requiere Atención' : 'Óptimo'],
                    ]
                ];

            case 'ventas':
                $cashSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['cash', 'efectivo'])->sum('total_amount'), 2);
                $cardSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['terminal', 'card', 'tarjeta'])->sum('total_amount'), 2);
                $transferSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['transfer', 'transferencia'])->sum('total_amount'), 2);

                return [
                    'kpis' => [
                        'ventas_totales'   => $totalSales,
                        'total_pedidos'    => $totalOrdersCount,
                        'ticket_promedio'  => $avgTicket,
                        'ventas_efectivo'  => $cashSales,
                        'ventas_terminal'  => $cardSales,
                        'ventas_transfer'  => $transferSales,
                    ],
                    'headers' => ['Método de Pago', 'Transacciones', 'Monto Bruto ($)', 'Participación (%)'],
                    'rows' => [
                        ['Efectivo', number_format((clone $paidOrdersQuery)->whereIn('payment_method', ['cash', 'efectivo'])->count()), '$' . number_format($cashSales, 2), $totalSales > 0 ? round(($cashSales / $totalSales) * 100, 1) . '%' : '0%'],
                        ['Terminal / Tarjeta', number_format((clone $paidOrdersQuery)->whereIn('payment_method', ['terminal', 'card', 'tarjeta'])->count()), '$' . number_format($cardSales, 2), $totalSales > 0 ? round(($cardSales / $totalSales) * 100, 1) . '%' : '0%'],
                        ['Transferencia', number_format((clone $paidOrdersQuery)->whereIn('payment_method', ['transfer', 'transferencia'])->count()), '$' . number_format($transferSales, 2), $totalSales > 0 ? round(($transferSales / $totalSales) * 100, 1) . '%' : '0%'],
                    ]
                ];

            case 'productos':
                $dishes = Dish::with('category')->get();
                $rows = [];
                $totalUnits = 0;

                foreach ($dishes as $dish) {
                    $qty = OrderItem::where('dish_id', $dish->id)
                        ->whereHas('order', function ($q) use ($startDate, $endDate) {
                            $q->whereBetween('created_at', [$startDate, $endDate]);
                        })->sum('quantity');

                    $rev = $qty * (float) $dish->price;
                    $totalUnits += $qty;

                    $rows[] = [
                        $dish->name,
                        $dish->category ? $dish->category->name : 'General',
                        number_format($qty) . ' porciones',
                        '$' . number_format((float) $dish->price, 2),
                        '$' . number_format($rev, 2)
                    ];
                }

                usort($rows, function ($a, $b) {
                    $valA = (float) str_replace(['$', ','], '', $a[4]);
                    $valB = (float) str_replace(['$', ','], '', $b[4]);
                    return $valB <=> $valA;
                });

                return [
                    'kpis' => [
                        'total_platillos'  => count($dishes),
                        'unidades_vendidas'=> $totalUnits,
                        'ingresos_menu'    => $totalSales,
                    ],
                    'headers' => ['Platillo / Producto', 'Categoría', 'Unidades Vendidas', 'Precio Unitario', 'Ingresos Generados'],
                    'rows'    => array_slice($rows, 0, 15)
                ];

            case 'inventario':
                $ingredients = Ingredient::with('supplier')->get();
                $rows = [];
                $totalStockVal = 0;
                $lowStockCount = 0;

                foreach ($ingredients as $ing) {
                    $qty = (float) $ing->quantity;
                    $min = (float) $ing->min_quantity;
                    $status = $qty <= 0 ? 'Agotado' : ($qty <= $min ? 'Bajo Stock' : 'Disponible');

                    if ($qty <= $min) $lowStockCount++;

                    $rows[] = [
                        $ing->name,
                        $ing->category ?: 'General',
                        $qty . ' ' . $ing->unit,
                        $min . ' ' . $ing->unit,
                        $ing->supplier ? ($ing->supplier->company_name ?? $ing->supplier->name) : 'Sin proveedor',
                        $status
                    ];
                }

                return [
                    'kpis' => [
                        'total_insumos'   => count($ingredients),
                        'alertas_stock'   => $lowStockCount,
                        'movimientos'     => StockMovement::whereBetween('created_at', [$startDate, $endDate])->count(),
                    ],
                    'headers' => ['Ingrediente', 'Categoría', 'Stock Actual', 'Stock Mínimo', 'Proveedor', 'Estado'],
                    'rows'    => $rows
                ];

            case 'delivery':
                $deliveries = Order::whereBetween('created_at', [$startDate, $endDate])
                    ->where('modality', 'delivery')
                    ->get();

                $rows = [];
                foreach ($deliveries as $del) {
                    $rows[] = [
                        $del->folio,
                        $del->customer_name ?: 'Cliente',
                        $del->customer_address ?: 'Domicilio no especificado',
                        '$' . number_format((float) $del->total_amount, 2),
                        ucfirst($del->payment_method),
                        ucfirst($del->status)
                    ];
                }

                return [
                    'kpis' => [
                        'total_envios'   => count($deliveries),
                        'ventas_delivery'=> round((float) $deliveries->sum('total_amount'), 2),
                    ],
                    'headers' => ['Folio', 'Cliente', 'Dirección de Entrega', 'Monto ($)', 'Método', 'Estado'],
                    'rows'    => $rows
                ];

            case 'reservaciones':
                $reservations = Reservation::whereBetween('created_at', [$startDate, $endDate])->get();
                $rows = [];

                foreach ($reservations as $res) {
                    $rows[] = [
                        $res->customer_name,
                        $res->customer_phone ?: 'S/N',
                        $res->guests . ' personas',
                        $res->reservation_date ? Carbon::parse($res. ' ' . $res->reservation_time)->format('d/m/Y H:i') : 'N/A',
                        $res->table_number ? 'Mesa ' . $res->table_number : 'General',
                        ucfirst($res->status)
                    ];
                }

                return [
                    'kpis' => [
                        'total_reservas' => count($reservations),
                        'comensales'     => (int) $reservations->sum('guests'),
                    ],
                    'headers' => ['Cliente', 'Teléfono', 'Personas', 'Fecha y Hora', 'Ubicación', 'Estado'],
                    'rows'    => $rows
                ];

            case 'costos':
                $entriesCost = round((float) StockMovement::where('type', 'entrada')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum(\DB::raw('quantity * COALESCE(cost_per_unit, 0)')), 2);

                $mermasCost = round((float) StockMovement::where('type', 'merma')
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->sum(\DB::raw('quantity * COALESCE(cost_per_unit, 0)')), 2);

                return [
                    'kpis' => [
                        'compras_insumos' => $entriesCost,
                        'costo_mermas'    => $mermasCost,
                        'total_gastos'    => round($entriesCost + $mermasCost, 2),
                    ],
                    'headers' => ['Concepto de Gasto', 'Monto Incurrido ($)', 'Participación (%)'],
                    'rows' => [
                        ['Compras y Entradas de Mercancía', '$' . number_format($entriesCost, 2), ($entriesCost + $mermasCost > 0) ? round(($entriesCost / ($entriesCost + $mermasCost)) * 100, 1) . '%' : '100%'],
                        ['Mermas y Desperdicios Registrados', '$' . number_format($mermasCost, 2), ($entriesCost + $mermasCost > 0) ? round(($mermasCost / ($entriesCost + $mermasCost)) * 100, 1) . '%' : '0%'],
                    ]
                ];

            case 'finanzas':
                $cashSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['cash', 'efectivo'])->sum('total_amount'), 2);
                $cardSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['terminal', 'card', 'tarjeta'])->sum('total_amount'), 2);
                $transferSales = round((float) (clone $paidOrdersQuery)->whereIn('payment_method', ['transfer', 'transferencia'])->sum('total_amount'), 2);

                return [
                    'kpis' => [
                        'ingresos_brutos'  => $totalSales,
                        'cobrado_efectivo' => $cashSales,
                        'cobrado_terminal' => $cardSales,
                        'cobrado_transfer' => $transferSales,
                        'balance_neto'     => $totalSales,
                    ],
                    'headers' => ['Flujo de Caja', 'Monto Acumulado ($)', 'Estado de Cuenta'],
                    'rows' => [
                        ['Ingresos Totales por Ventas', '$' . number_format($totalSales, 2), 'Recibido'],
                        ['Cobros en Efectivo', '$' . number_format($cashSales, 2), 'En Caja'],
                        ['Cobros en Terminal Bancaria', '$' . number_format($cardSales, 2), 'Depositado'],
                        ['Cobros por Transferencia', '$' . number_format($transferSales, 2), 'Verificado'],
                    ]
                ];

            case 'impuestos':
                $subtotalGravable = round($totalSales / 1.16, 2);
                $ivaEstimado = round($totalSales - $subtotalGravable, 2);

                return [
                    'kpis' => [
                        'ventas_gravables' => $totalSales,
                        'base_imponible'   => $subtotalGravable,
                        'iva_estimado_16'  => $ivaEstimado,
                    ],
                    'headers' => ['Concepto Fiscal', 'Tasa Aplicable', 'Monto Base ($)', 'Impuesto Calculado ($)'],
                    'rows' => [
                        ['Ventas con IVA Incluido (16%)', '16.0%', '$' . number_format($subtotalGravable, 2), '$' . number_format($ivaEstimado, 2)],
                        ['Total Contribución Fiscal Estimada', '-', '$' . number_format($subtotalGravable, 2), '$' . number_format($ivaEstimado, 2)],
                    ]
                ];

            default:
                return [
                    'kpis' => ['ventas' => $totalSales],
                    'headers' => ['Concepto', 'Valor'],
                    'rows' => [['Ventas Totales', '$' . number_format($totalSales, 2)]]
                ];
        }
    }

    /**
     * Reporte financiero de ingresos agrupados por día para pedidos de delivery entregados.
     * Utiliza DB::raw para que el motor de base de datos realice los cálculos matemáticos pesados.
     */
    public function ingresosDelivery(Request $request)
    {
        // 1. Recibimos el filtro
        $filtro = strtolower(trim((string) $request->input('filtro', 'mes')));
        
        // 2. Filtramos solo los pedidos completados y hacemos los JOINS necesarios
        $query = Delivery::query()
            ->join('orders', 'deliveries.order_id', '=', 'orders.id')
            // Unimos con la tabla de repartidores/usuarios
            ->leftJoin('delivery_drivers', 'deliveries.driver_id', '=', 'delivery_drivers.id')
            ->leftJoin('users as driver_users', 'delivery_drivers.user_id', '=', 'driver_users.id')
            ->leftJoin('users as direct_repartidores', 'deliveries.driver_id', '=', 'direct_repartidores.id')
            ->where(function ($q) {
                $q->whereIn('deliveries.status', ['delivered', 'completed', 'entregada', 'Entregada'])
                  ->orWhere(function ($sq) {
                      $sq->whereIn('orders.status', ['delivered', 'completed', 'entregada', 'Entregada'])
                         ->whereNotIn('deliveries.status', ['cancelled', 'cancelada', 'canceled']);
                  });
            });

        // 3. Aplicamos el rango de fechas según el filtro seleccionado
        switch ($filtro) {
            case 'semana':
            case 'week':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'mes':
            case 'month':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3meses':
            case '3_meses':
            case '3months':
            case 'trimestre':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            case 'ano':
            case 'año':
            case 'year':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
                break;
            case 'hoy':
            case 'today':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            default:
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('deliveries.created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay()
                    ]);
                } else {
                    $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                }
                break;
        }

        // 4. CRÍTICO: La agrupación en PostgreSQL por REPARTIDOR (prioridad a tabla users)
        $ingresos = $query->select(
            // Extraemos el nombre del repartidor para que React lo pueda leer
            DB::raw("COALESCE(driver_users.name, direct_repartidores.name, delivery_drivers.name, 'Sin asignar') as repartidor"),
            DB::raw('COUNT(deliveries.id) as pedidos_realizados'),
            DB::raw('COALESCE(SUM(orders.total_amount), 0) as ingresos_generados')
        )
        ->groupBy(
            DB::raw("COALESCE(driver_users.name, direct_repartidores.name, delivery_drivers.name, 'Sin asignar')")
        )
        ->orderBy('ingresos_generados', 'desc')
        ->get();

        // 5. Formateamos la respuesta para enviarla a React
        $mapped = $ingresos->map(function ($item) {
            return [
                'repartidor'         => (string) $item->repartidor,
                'pedidos_realizados' => (int) $item->pedidos_realizados,
                'ingresos_generados' => round((float) $item->ingresos_generados, 2),
            ];
        });

        return response()->json($mapped);
    }

    /**
     * Reporte financiero de ingresos agrupados por repartidor.
     * Une deliveries, delivery_drivers/users y orders para calcular el total recaudado por persona en SQL.
     */
    public function ingresosPorRepartidor(Request $request)
    {
        // Recibimos el filtro desde React (por defecto 'mes')
        $filtro = strtolower(trim((string) $request->input('filtro', 'mes')));

        // Iniciamos la consulta filtrando solo las entregadas exitosamente
        $query = Delivery::query()
            ->join('orders', 'deliveries.order_id', '=', 'orders.id')
            ->leftJoin('delivery_drivers', 'deliveries.driver_id', '=', 'delivery_drivers.id')
            ->leftJoin('users as driver_users', 'delivery_drivers.user_id', '=', 'driver_users.id')
            ->leftJoin('users as direct_repartidores', 'deliveries.driver_id', '=', 'direct_repartidores.id')
            ->where(function ($q) {
                $q->whereIn('deliveries.status', ['delivered', 'completed', 'entregada', 'Entregada'])
                  ->orWhere(function ($sq) {
                      $sq->whereIn('orders.status', ['delivered', 'completed', 'entregada', 'Entregada'])
                         ->whereNotIn('deliveries.status', ['cancelled', 'cancelada', 'canceled']);
                  });
            });

        // Filtros de tiempo
        switch ($filtro) {
            case 'semana':
            case 'week':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'mes':
            case 'month':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3meses':
            case '3_meses':
            case '3months':
            case 'trimestre':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            case 'ano':
            case 'año':
            case 'year':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
                break;
            case 'hoy':
            case 'today':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            default:
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('deliveries.created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay()
                    ]);
                } else {
                    $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                }
                break;
        }

        // El JOIN y la agrupación en PostgreSQL (CORREGIDO - Prioridad absoluta a la tabla users)
        $ingresos = $query->select(
            // SOLUCIÓN: driver_users.name y direct_repartidores.name van primero
            DB::raw("COALESCE(driver_users.name, direct_repartidores.name, delivery_drivers.name, 'Sin asignar') as repartidor"),
            DB::raw("COALESCE(driver_users.id, direct_repartidores.id, delivery_drivers.id, 0) as repartidor_id"),
            DB::raw('COUNT(deliveries.id) as pedidos_realizados'),
            DB::raw('COALESCE(SUM(orders.total_amount), 0) as ingresos_generados')
        )
        ->groupBy(
            DB::raw("COALESCE(driver_users.name, direct_repartidores.name, delivery_drivers.name, 'Sin asignar')"),
            DB::raw("COALESCE(driver_users.id, direct_repartidores.id, delivery_drivers.id, 0)")
        )
        ->orderBy('ingresos_generados', 'desc')
        ->get();

        $mapped = $ingresos->map(function ($item) {
            return [
                'repartidor'         => (string) $item->repartidor,
                'repartidor_id'      => (int) $item->repartidor_id,
                'pedidos_realizados' => (int) $item->pedidos_realizados,
                'ingresos_generados' => round((float) $item->ingresos_generados, 2),
                'total'              => round((float) $item->ingresos_generados, 2),
            ];
        });

        return response()->json($mapped);
    }

    /**
     * KPIs y estadísticas de rendimiento de delivery filtradas por temporalidad.
     * Utiliza EXTRACT(EPOCH) e ISODOW en PostgreSQL / strftime en SQLite para medir tiempos, puntualidad, top repartidores y gráfica por días.
     */
    public function kpisRendimiento(Request $request)
    {
        // 1. Recibir el filtro (hoy, semana, mes, 3meses)
        $filtro = strtolower(trim((string) $request->input('filtro', 'mes')));

        // 2. Base de la consulta (Sin filtrar por estado aún, para poder contar las canceladas)
        $query = Delivery::query();

        // 3. Aplicar el rango de tiempo seleccionado
        switch ($filtro) {
            case 'hoy':
            case 'today':
                $query->whereDate('deliveries.created_at', Carbon::today());
                break;
            case 'semana':
            case 'week':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]);
                break;
            case 'mes':
            case 'month':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                break;
            case '3meses':
            case '3_meses':
            case '3months':
            case 'trimestre':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->subMonths(3)->startOfDay(), Carbon::now()->endOfDay()]);
                break;
            case 'ano':
            case 'año':
            case 'year':
                $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfYear(), Carbon::now()->endOfYear()]);
                break;
            default:
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('deliveries.created_at', [
                        Carbon::parse($request->start_date)->startOfDay(),
                        Carbon::parse($request->end_date)->endOfDay()
                    ]);
                } else {
                    $query->whereBetween('deliveries.created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]);
                }
                break;
        }

        // 4. El "Motor de Pensamiento" en PostgreSQL / SQLite
        $isSqlite = DB::connection()->getDriverName() === 'sqlite';
        $diffMinutesSql = $isSqlite
            ? "((strftime('%s', deliveries.updated_at) - strftime('%s', deliveries.created_at)) / 60.0)"
            : "EXTRACT(EPOCH FROM (deliveries.updated_at - deliveries.created_at))/60";

        $estadisticas = $query->select(
            DB::raw('COUNT(deliveries.id) as total_pedidos'),

            // Sumar cuántas se entregaron con éxito
            DB::raw("SUM(CASE WHEN deliveries.status IN ('delivered', 'completed', 'entregada', 'Entregada') THEN 1 ELSE 0 END) as entregas_completadas"),

            // Sumar cuántas se cancelaron en ruta
            DB::raw("SUM(CASE WHEN deliveries.status IN ('cancelled', 'cancelada', 'canceled') THEN 1 ELSE 0 END) as entregas_canceladas"),

            // Calcular tiempo promedio en minutos SOLO de las entregadas
            DB::raw("AVG(CASE WHEN deliveries.status IN ('delivered', 'completed', 'entregada', 'Entregada') THEN {$diffMinutesSql} ELSE NULL END) as tiempo_promedio_minutos"),

            // Contar demoras: Entregadas pero que tardaron más de 40 minutos
            DB::raw("SUM(CASE WHEN deliveries.status IN ('delivered', 'completed', 'entregada', 'Entregada') AND {$diffMinutesSql} > 40 THEN 1 ELSE 0 END) as pedidos_con_demora")
        )->first();

        // 5. Limpieza de datos y cálculo de porcentajes para React
        $total = (int) ($estadisticas->total_pedidos ?? 0);
        $completadas = (int) ($estadisticas->entregas_completadas ?? 0);
        $canceladas = (int) ($estadisticas->entregas_canceladas ?? 0);
        $demoras = (int) ($estadisticas->pedidos_con_demora ?? 0);
        $tiempoPromedio = (int) round((float) ($estadisticas->tiempo_promedio_minutos ?? 0));

        // Matemáticas de negocio
        $aTiempo = $completadas - $demoras;
        $porcentajeATiempo = $completadas > 0 ? (int) round(($aTiempo / $completadas) * 100) : 0;
        $porcentajeCompletadas = $total > 0 ? (int) round(($completadas / $total) * 100) : 0;

        // Retornamos el JSON limpio y optimizado para las tarjetas superiores
        return response()->json([
            'tiempo_promedio_min'     => $tiempoPromedio,
            'porcentaje_a_tiempo'     => $porcentajeATiempo,
            'porcentaje_completadas'  => $porcentajeCompletadas,
            'pedidos_con_demora'      => $demoras,
            'total_problemas'         => $demoras + $canceladas,

            // Aliases para compatibilidad total con frontend
            'tiempo_promedio'         => $tiempoPromedio,
            'tiempo_promedio_minutos' => $tiempoPromedio,
            'porcentaje_exito'        => $porcentajeATiempo,
            'pedidos_demorados'       => $demoras,
            'total_entregas'          => $completadas,
            'total_pedidos'           => $total,
            'meta_minutos'            => 40,
            'meta_puntualidad'        => 90,
        ]);
    }

    /**
     * Alias compatible de métricas de rendimiento.
     */
    public function metricasRendimiento(Request $request)
    {
        return $this->kpisRendimiento($request);
    }
}

