<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Mesa;
use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class MesaController extends Controller
{
    /**
     * PUT /api/admin/mesas/{id}
     * Editar mesa.
     */
    public function update(Request $request, $id)
    {
        $mesa = Mesa::findOrFail($id);

        $request->validate([
            'numero_mesa' => 'sometimes|integer|min:1',
            'capacidad'   => 'sometimes|integer|min:1',
            'is_active'   => 'sometimes|boolean',
        ]);

        $mesa->update($request->only(['numero_mesa', 'capacidad', 'is_active']));

        AuditLogger::log('MESA_UPDATED', 'Áreas', "Mesa #{$mesa->numero_mesa} actualizada", auth()->user(), 'info');

        return response()->json($mesa);
    }

    /**
     * PATCH /api/admin/mesas/{id}/toggle
     * Activar/desactivar mesa individual.
     */
    public function toggle($id)
    {
        $mesa = Mesa::findOrFail($id);
        $mesa->is_active = !$mesa->is_active;
        $mesa->save();

        $statusText = $mesa->is_active ? 'activada' : 'desactivada';
        AuditLogger::log('MESA_TOGGLED', 'Áreas', "Mesa #{$mesa->numero_mesa} fue {$statusText}", auth()->user(), 'info');

        return response()->json([
            'message'   => "Mesa #{$mesa->numero_mesa} {$statusText} correctamente.",
            'is_active' => $mesa->is_active,
            'status'    => $mesa->is_active ? 'activo' : 'inactivo'
        ]);
    }

    /**
     * DELETE /api/admin/mesas/{id}
     * Eliminar mesa con confirmación de que no tenga pedidos activos.
     */
    public function destroy($id)
    {
        $mesa = Mesa::findOrFail($id);

        // Check active orders on this table
        $hasActiveOrders = Order::where('table_number', $mesa->numero_mesa)
            ->whereIn('status', ['pending', 'in_preparation', 'ready', 'en_proceso', 'pendiente', 'en_preparacion'])
            ->exists();

        if ($hasActiveOrders) {
            return response()->json([
                'message' => "No se puede eliminar la Mesa #{$mesa->numero_mesa} porque tiene un pedido activo en curso."
            ], 422);
        }

        $areaId = $mesa->area_id;
        $numMesa = $mesa->numero_mesa;
        $mesa->delete();

        // Update area table count
        if ($areaId) {
            $area = Area::find($areaId);
            if ($area) {
                $newCount = Mesa::where('area_id', $areaId)->count();
                $area->update([
                    'numero_mesas' => $newCount,
                    'tables_count' => $newCount,
                ]);
            }
        }

        AuditLogger::log('MESA_DELETED', 'Áreas', "Mesa #{$numMesa} fue eliminada", auth()->user(), 'warning');

        return response()->json([
            'message' => "Mesa #{$numMesa} eliminada correctamente."
        ]);
    }
}
