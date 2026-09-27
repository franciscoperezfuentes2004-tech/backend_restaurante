<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCut;
use App\Models\Order;
use App\Services\NotificationService;
use App\Events\CashCutConfirmed;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CashCutController extends Controller
{
    /**
     * Listar cortes de caja con filtro (pendientes / 30dias / 60dias / confirmados / todos)
     * y paginación estricta Server-Side de 8 registros.
     */
    public function index(Request $request)
    {
        $filtro = strtolower(trim((string) $request->input('filtro', $request->input('status', 'pendientes'))));

        // Iniciamos la consulta
        $query = CashCut::with(['user', 'confirmedBy']);

        if (in_array($filtro, ['pendientes', 'pendiente', 'pending'], true)) {
            $query->whereIn('status', ['pendiente', 'pending']);
        } elseif (in_array($filtro, ['confirmados', 'confirmado', 'confirmed'], true)) {
            $query->whereIn('status', ['confirmado', 'confirmed']);
        } elseif (in_array($filtro, ['30dias', '30_dias', '30-dias', '30'], true)) {
            // Historial de los últimos 30 días
            $query->where('created_at', '>=', Carbon::now()->subDays(30));
        } elseif (in_array($filtro, ['60dias', '60_dias', '60-dias', '60'], true)) {
            // Historial de los últimos 60 días
            $query->where('created_at', '>=', Carbon::now()->subDays(60));
        } else {
            // Fallback para 'todos' o filtros no reconocidos: máximo 60 días de seguridad
            $query->where('created_at', '>=', Carbon::now()->subDays(60));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->query('user_id'));
        }

        if ($request->filled('cut_type')) {
            $query->where('cut_type', $request->query('cut_type'));
        }

        // EL BLINDAJE FINAL: Paginación estricta desde la base de datos (8 filas por página)
        $perPage = (int) $request->input('per_page', 8);
        $cortes = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json($cortes);
    }

    /**
     * Detalle de un corte específico incluyendo desglose de órdenes.
     * Expone cash_declared, expected_cash y received_cash.
     */
    public function show($id)
    {
        $cashCut = CashCut::with(['user', 'confirmedBy'])->findOrFail($id);

        // Fecha del corte en zona horaria de México
        $cutDate = $cashCut->created_at
            ? $cashCut->created_at->copy()->setTimezone('America/Mexico_City')
            : Carbon::today('America/Mexico_City');

        $startOfDay = $cutDate->copy()->startOfDay();
        $endOfDay   = $cutDate->copy()->endOfDay();
        $startUtc   = $startOfDay->copy()->setTimezone('UTC');
        $endUtc     = $endOfDay->copy()->setTimezone('UTC');

        $userId = $cashCut->user_id;

        $orders = Order::with(['items.dish', 'items.extras.extra'])
            ->where(function ($stQ) {
                $stQ->whereIn('status', ['completed', 'delivered', 'entregada', 'entregado'])
                    ->orWhereHas('delivery', function ($delQ) {
                        $delQ->whereIn('status', ['delivered', 'completed', 'entregada']);
                    });
            })
            ->where(function ($dateQ) use ($startOfDay, $endOfDay, $startUtc, $endUtc, $cutDate) {
                $dateQ->whereBetween('updated_at', [$startOfDay, $endOfDay])
                      ->orWhereBetween('updated_at', [$startUtc, $endUtc])
                      ->orWhereDate('updated_at', $cutDate)
                      ->orWhereDate('created_at', $cutDate);
            })
            ->where(function ($q) use ($userId) {
                $q->whereHas('delivery.driver', function ($dq) use ($userId) {
                    $dq->where('user_id', $userId);
                })
                ->orWhere('user_id', $userId);
            })
            ->latest('updated_at')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $amount = (float) $order->total_amount;
            $pm = strtolower(trim((string) ($order->payment_method ?? 'cash')));

            if (in_array($pm, ['card', 'tarjeta', 'terminal', 'stripe', 'tarjeta_debito', 'tarjeta_credito'])) {
                $metodoStr = 'terminal';
            } elseif (in_array($pm, ['transfer', 'transferencia', 'spei', 'banco'])) {
                $metodoStr = 'transferencia';
            } else {
                $metodoStr = 'efectivo';
            }

            $updatedAt = $order->updated_at ? $order->updated_at->copy()->setTimezone('America/Mexico_City') : null;

            return [
                'id'             => $order->id,
                'folio'          => $order->folio ?? "#{$order->id}",
                'dispatch_token' => $order->dispatch_token,
                'cliente'        => $order->customer_name ?: 'Cliente General',
                'customer_name'  => $order->customer_name ?: 'Cliente General',
                'telefono'       => $order->customer_phone ?: '',
                'direccion'      => $order->customer_address ?: '',
                'metodo_pago'    => $order->payment_method ?: 'cash',
                'metodoPago'     => $metodoStr,
                'total'          => $amount,
                'total_amount'   => $amount,
                'hora'           => $updatedAt?->format('H:i') ?? '',
                'fecha'          => $updatedAt?->format('d/m/Y') ?? '',
                'status'         => $order->status,
            ];
        });

        return response()->json([
            'cash_cut'      => $cashCut,
            'user'          => $cashCut->user,
            'orders'        => $formattedOrders,
            'cash_declared' => $cashCut->cash_declared,
            'expected_cash' => $cashCut->expected_cash,
            'received_cash' => $cashCut->received_cash,
        ]);
    }

    /**
     * Confirmar un corte de caja por parte del admin/gerente.
     * Recibe received_cash y notes (opcional).
     */
    public function confirmar(Request $request, $id)
    {
        $request->validate([
            'received_cash' => 'nullable|numeric|min:0',
            'notes'         => 'nullable|string',
        ]);

        $cashCut = CashCut::with(['user'])->findOrFail($id);

        if ($cashCut->status === 'confirmado') {
            return response()->json([
                'message'  => 'Este corte ya ha sido confirmado previamente.',
                'cash_cut' => $cashCut,
            ], 422);
        }

        $adminUser = auth()->user() ?? $request->user();

        $receivedCash = $request->has('received_cash') && $request->input('received_cash') !== null && $request->input('received_cash') !== ''
            ? round((float) $request->input('received_cash'), 2)
            : null;

        $notes = $request->has('notes') ? trim((string) $request->input('notes')) : (trim((string) $cashCut->notes) ?: '');

        if ($receivedCash !== null) {
            $expected = (float) $cashCut->expected_cash;
            $diff = round($expected - $receivedCash, 2);

            if (abs($diff) >= 0.01) {
                $xFormatted = number_format($expected, 2);
                $yFormatted = number_format($receivedCash, 2);
                $zFormatted = number_format($diff, 2);
                $autoNote = "Discrepancia detectada: se esperaban \${$xFormatted}, se recibieron \${$yFormatted}. Diferencia: \${$zFormatted}.";

                if ($notes !== '') {
                    $notes .= "\n" . $autoNote;
                } else {
                    $notes = $autoNote;
                }
            }
        }

        $finalNotes = $notes !== '' ? $notes : null;

        $cashCut->update([
            'status'        => 'confirmado',
            'confirmed_by'  => $adminUser?->id,
            'confirmed_at'  => Carbon::now(),
            'received_cash' => $receivedCash,
            'notes'         => $finalNotes,
        ]);

        try {
            NotificationService::create(
                'corte_confirmado',
                'Corte de Caja Confirmado',
                "El corte #{$cashCut->id} de " . ($cashCut->user?->name ?? 'Usuario') . " fue confirmado por " . ($adminUser?->name ?? 'Administración') . ".",
                [
                    'cash_cut_id'   => $cashCut->id,
                    'user_id'       => $cashCut->user_id,
                    'admin_id'      => $adminUser?->id,
                    'received_cash' => $receivedCash,
                ]
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('NotificationService error confirming cash cut: ' . $e->getMessage());
        }

        try {
            broadcast(new CashCutConfirmed(
                cashCutId: $cashCut->id,
                userId: $cashCut->user_id,
                userName: $cashCut->user?->name ?? 'Usuario',
                confirmedBy: $adminUser?->name ?? 'Administración',
                receivedCash: $receivedCash,
            ))->toOthers();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Broadcast CashCutConfirmed error: ' . $e->getMessage());
        }

        return response()->json([
            'message'  => 'Corte confirmado exitosamente.',
            'success'  => true,
            'cash_cut' => $cashCut->fresh()->load(['user', 'confirmedBy']),
        ]);
    }

    public function ingresosPorRepartidor(Request $request)
    {
        return app(ReportController::class)->ingresosPorRepartidor($request);
    }
}
