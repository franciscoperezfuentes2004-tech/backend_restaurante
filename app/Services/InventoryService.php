<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Order;
use App\Models\Stock;
use App\Models\StockMovement;
use App\Models\Ingredient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryService
{
    /**
     * Descuenta los ingredientes del inventario según la receta de los platillos del pedido.
     * Envuelta en una transacción de base de datos para que, si un ingrediente falla, no se descuente nada a medias.
     *
     * @param int|string|Order|Delivery $pedidoId ID del Delivery, ID del Order o instancia de modelo.
     * @return bool
     */
    public static function descontarInventario($pedidoId): bool
    {
        return DB::transaction(function () use ($pedidoId) {
            $pedido = null;

            if ($pedidoId instanceof Delivery) {
                $pedido = $pedidoId->loadMissing('detalles.producto.ingredientes');
            } elseif ($pedidoId instanceof Order) {
                $pedido = $pedidoId->loadMissing('detalles.producto.ingredientes');
            } elseif (is_numeric($pedidoId) || is_string($pedidoId)) {
                // 1. Intentar buscar primero como Delivery
                $pedido = Delivery::with('detalles.producto.ingredientes')->find($pedidoId);
                if (!$pedido) {
                    // 2. Si no es un ID de Delivery, buscar como Order
                    $pedido = Order::with('detalles.producto.ingredientes')->find($pedidoId);
                }
            }

            if (!$pedido) {
                Log::warning("InventoryService: Pedido no encontrado para ID {$pedidoId}");
                return false;
            }

            $detalles = $pedido->detalles ?? $pedido->items ?? [];

            foreach ($detalles as $detalle) {
                $cantidadPedida = (float) ($detalle->cantidad ?? $detalle->quantity ?? 1);
                $producto = $detalle->producto ?? $detalle->dish ?? $detalle->platillo;

                if (!$producto) {
                    continue;
                }

                $ingredientes = $producto->ingredientes ?? $producto->ingredients ?? [];

                foreach ($ingredientes as $ingrediente) {
                    // Multiplicamos lo que pide la receta por la cantidad de platillos vendidos
                    $cantidadRequerida = (float) ($ingrediente->pivot->cantidad_requerida ?? $ingrediente->pivot->quantity ?? 1);
                    $cantidadUsada = $cantidadRequerida * $cantidadPedida;

                    // Descontamos directamente en la tabla de ingredientes (lock for update)
                    $ingredienteModel = Ingredient::where('id', $ingrediente->id)->lockForUpdate()->first();
                    if ($ingredienteModel) {
                        $ingredienteModel->decrement('stock_actual', $cantidadUsada);
                        $ingredienteModel->refresh();

                        // Descontamos también en la tabla stock si existe
                        $stock = Stock::where('ingredient_id', $ingredienteModel->id)->lockForUpdate()->first();
                        if ($stock) {
                            $stock->decrement('quantity', $cantidadUsada);
                            $stock->refresh();
                        }

                        // Registrar movimiento de auditoría/kardex si aplica
                        try {
                            StockMovement::create([
                                'ingredient_id' => $ingredienteModel->id,
                                'type'          => 'salida',
                                'quantity'      => $cantidadUsada,
                                'cost_per_unit' => $ingredienteModel->base_cost ?? 0,
                                'notes'         => "Consumo por pedido #" . ($pedido->folio ?? ($pedido->order?->folio ?? $pedido->id)),
                                'created_by'    => auth()->id() ?? null,
                            ]);
                        } catch (\Throwable $e) {
                            Log::warning("No se pudo registrar movimiento de stock: " . $e->getMessage());
                        }

                        $stockActual = (float) $ingredienteModel->stock_actual;
                        $stockMinimo = (float) $ingredienteModel->stock_minimo;

                        // Alerta si el stock cae por debajo del nivel de seguridad
                        if ($stockActual < $stockMinimo) {
                            Log::warning("Stock bajo para: " . ($ingredienteModel->nombre ?? $ingredienteModel->name));
                        }
                    }
                }
            }

            return true;
        });
    }
}
