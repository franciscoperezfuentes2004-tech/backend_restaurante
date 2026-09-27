<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CashCut;
use App\Models\Order;
use App\Models\DeliveryDriver;
use App\Services\NotificationService;
use App\Events\DeliveryCutNotified;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CloseDriverShiftController extends Controller
{
    /**
     * Cierra el turno del repartidor y genera el corte de caja con estricta integridad financiera.
     * REGLA DE ORO FINANCIERA: El backend ignora cualquier monto numérico enviado por el cliente
     * y calcula la deuda en efectivo 100% en PostgreSQL como única fuente de verdad.
     */
    public function close(Request $request)
    {
        $user = auth()->user() ?? $request->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado.'], 401);
        }

        return DB::transaction(function () use ($user, $request) {
            // 1. VALIDACIÓN DE ESTADO E IDEMPOTENCIA
            // Si el repartidor ya notificó su corte y está pendiente de confirmación,
            // o si llega una petición duplicada, abortar con HTTP 409 Conflict.
            $pendingCut = CashCut::where('user_id', $user->id)
                ->where('status', 'pendiente')
                ->lockForUpdate()
                ->first();

            if ($pendingCut) {
                return response()->json([
                    'message'  => 'Tu corte ya ha sido notificado y cerrado.',
                    'cash_cut' => $pendingCut,
                ], 409);
            }

            // 2. RESOLVER PERFIL DE REPARTIDOR
            $driver = DeliveryDriver::where('user_id', $user->id)->first();
            if (!$driver) {
                $driver = DeliveryDriver::where('email', $user->email)->orWhere('name', $user->name)->first();
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
                    Log::warning('DeliveryDriver auto-create in CloseDriverShiftController: ' . $e->getMessage());
                    $driver = DeliveryDriver::where('user_id', $user->id)->first();
                }
            }

            $driverName = $driver?->name ?? $user->name;
            $driverId = $driver?->id;
            $userId = $user->id;

            // 3. DEFINIR RANGO DE TURNO (SINGLE SOURCE OF TRUTH)
            $todayMexico = Carbon::today('America/Mexico_City');
            $startOfDay  = $todayMexico->copy()->startOfDay();
            $endOfDay    = $todayMexico->copy()->endOfDay();
            $startUtc    = $startOfDay->copy()->setTimezone('UTC');
            $endUtc      = $endOfDay->copy()->setTimezone('UTC');

            // Si el repartidor ya tuvo un corte confirmado hoy, solo se consideran pedidos posteriores a ese corte
            $lastConfirmedCut = CashCut::where('user_id', $userId)
                ->where('status', 'confirmado')
                ->latest('confirmed_at')
                ->first();

            $ordersQuery = Order::with(['delivery'])
                ->where(function ($stQ) {
                    $stQ->whereIn('status', ['completed', 'delivered', 'entregada', 'entregado'])
                        ->orWhereHas('delivery', function ($delQ) {
                            $delQ->whereIn('status', ['delivered', 'completed', 'entregada']);
                        });
                })
                ->where(function ($q) use ($driverId, $userId) {
                    if ($driverId) {
                        $q->whereHas('delivery', function ($dq) use ($driverId) {
                            $dq->where('driver_id', $driverId);
                        });
                    }
                    if ($userId) {
                        $q->orWhere('user_id', $userId)
                          ->orWhereHas('delivery.driver', function ($dq) use ($userId) {
                              $dq->where('user_id', $userId);
                          });
                    }
                });

            if ($lastConfirmedCut && $lastConfirmedCut->confirmed_at && Carbon::parse($lastConfirmedCut->confirmed_at)->isToday()) {
                $ordersQuery->where('updated_at', '>', $lastConfirmedCut->confirmed_at);
            } else {
                $ordersQuery->where(function ($dateQ) use ($startOfDay, $endOfDay, $startUtc, $endUtc, $todayMexico) {
                    $dateQ->whereBetween('updated_at', [$startOfDay, $endOfDay])
                          ->orWhereBetween('updated_at', [$startUtc, $endUtc])
                          ->orWhereDate('updated_at', $todayMexico)
                          ->orWhereDate('created_at', $todayMexico);
                });
            }

            $orders = $ordersQuery->get();

            // 4. CÁLCULO ESTRICTO EN SERVIDOR
            $totalCash = 0.0;
            $totalCard = 0.0;
            $totalTransfer = 0.0;

            foreach ($orders as $order) {
                $amount = (float) $order->total_amount;
                $pm = strtolower(trim((string) ($order->payment_method ?? 'cash')));

                if (in_array($pm, ['card', 'tarjeta', 'terminal', 'stripe', 'tarjeta_debito', 'tarjeta_credito'])) {
                    $totalCard += $amount;
                } elseif (in_array($pm, ['transfer', 'transferencia', 'spei', 'banco'])) {
                    $totalTransfer += $amount;
                } else {
                    $totalCash += $amount;
                }
            }

            $totalAmount = $totalCash + $totalCard + $totalTransfer;
            $completedCount = $orders->count();
            $nowMexico = Carbon::now('America/Mexico_City');

            // REGLA DE ORO FINANCIERA:
            // Si el frontend envía {"cash_declared": 0} o {"efectivo_a_entregar": 0},
            // se descarta por completo. Ambos valores se extraen 100% de la BD.
            $rawNotes = $request->input('notes');
            $notes = $rawNotes ? strip_tags(trim((string) $rawNotes)) : null;

            $cashCut = CashCut::create([
                'user_id'       => $user->id,
                'cut_type'      => 'repartidor',
                'cash_declared' => round($totalCash, 2),
                'expected_cash' => round($totalCash, 2),
                'total_orders'  => $completedCount,
                'status'        => 'pendiente',
                'notes'         => $notes,
            ]);

            $cutData = [
                'id'                  => $cashCut->id,
                'cash_cut_id'         => $cashCut->id,
                'user_id'             => $user->id,
                'user_name'           => $user->name,
                'cut_type'            => 'repartidor',
                'cash_declared'       => round($totalCash, 2),
                'expected_cash'       => round($totalCash, 2),
                'total_cash'          => round($totalCash, 2),
                'total_card'          => round($totalCard, 2),
                'total_transfer'      => round($totalTransfer, 2),
                'total_amount'        => round($totalAmount, 2),
                'total_efectivo'      => round($totalCash, 2),
                'total_terminal'      => round($totalCard, 2),
                'total_transferencia' => round($totalTransfer, 2),
                'completed_count'     => $completedCount,
                'total_orders'        => $completedCount,
                'status'              => 'pendiente',
                'fecha_corte'         => $nowMexico->format('Y-m-d H:i:s'),
                'hora_corte'          => $nowMexico->format('H:i'),
            ];

            // 5. NOTIFICACIONES Y EVENTOS
            try {
                NotificationService::create(
                    'corte_repartidor',
                    'Corte de Caja de Repartidor',
                    "El repartidor {$driverName} ha solicitado el corte de caja: \${$totalCash} MXN en efectivo ({$completedCount} entregas).",
                    $cutData
                );
            } catch (\Throwable $e) {
                Log::warning('NotificationService cut notification error: ' . $e->getMessage());
            }

            try {
                broadcast(new DeliveryCutNotified($cutData));
            } catch (\Throwable $e) {
                Log::warning('Broadcast DeliveryCutNotified error: ' . $e->getMessage());
            }

            return response()->json([
                'message'  => 'Corte notificado con éxito. El encargado ha sido avisado.',
                'success'  => true,
                'data'     => $cutData,
                'cash_cut' => $cashCut,
            ], 200);
        });
    }

    /**
     * Invocación directa del controlador
     */
    public function __invoke(Request $request)
    {
        return $this->close($request);
    }
}
