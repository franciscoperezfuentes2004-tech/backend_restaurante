<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAreaRequest;
use App\Http\Requests\UpdateAreaRequest;
use App\Models\Area;
use App\Models\Mesa;
use App\Models\Order;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * GET /api/admin/areas
     * Lista todas las áreas con su conteo de mesas y estado.
     */
    public function index()
    {
        $areas = Area::withCount('mesas')
            ->orderBy('id', 'asc')
            ->get();

        $result = $areas->map(function ($area) {
            $realMesasCount = $area->mesas_count > 0 ? $area->mesas_count : ($area->numero_mesas ?? $area->tables_count ?? 0);
            return [
                'id'                 => $area->id,
                'nombre'             => $area->nombre ?? $area->name ?? 'Área',
                'name'               => $area->nombre ?? $area->name ?? 'Área',
                'capacidad_personas' => (int) ($area->capacidad_personas ?? $area->capacity ?? 0),
                'capacity'           => (int) ($area->capacidad_personas ?? $area->capacity ?? 0),
                'numero_mesas'       => (int) $realMesasCount,
                'tables_count'       => (int) $realMesasCount,
                'mesas_count'        => (int) $realMesasCount,
                'is_active'          => (bool) ($area->is_active ?? $area->active ?? true),
                'active'             => (bool) ($area->is_active ?? $area->active ?? true),
                'status'             => ($area->is_active ?? $area->active ?? true) ? 'activo' : 'inactivo',
                'created_at'         => $area->created_at ? $area->created_at->format('Y-m-d H:i:s') : null,
            ];
        });

        return response()->json($result);
    }

    /**
     * POST /api/admin/areas
     * Crear área con nombre, capacidad y número de mesas.
     */
    public function store(StoreAreaRequest $request)
    {
        $validated = $request->validated();

        $nombre = trim(strip_tags($validated['name'] ?? $validated['nombre']));
        $capacidad = (int) ($validated['capacity'] ?? $validated['capacidad_personas'] ?? 20);
        $numMesas = (int) ($validated['tables_count'] ?? $validated['numero_mesas'] ?? 0);
        $isActive = array_key_exists('is_active', $validated)
            ? (bool) $validated['is_active']
            : (array_key_exists('active', $validated) ? (bool) $validated['active'] : true);

        $area = Area::create([
            'nombre'             => $nombre,
            'name'               => $nombre,
            'capacidad_personas' => $capacidad,
            'capacity'           => $capacidad,
            'numero_mesas'       => $numMesas,
            'tables_count'       => $numMesas,
            'is_active'          => $isActive,
            'active'             => $isActive,
        ]);

        // Auto-create initial tables for the area
        if ($numMesas > 0) {
            $capPerTable = (int) ceil($capacidad / max(1, $numMesas));
            for ($i = 1; $i <= $numMesas; $i++) {
                Mesa::create([
                    'area_id'     => $area->id,
                    'numero_mesa' => $i,
                    'capacidad'   => max(2, $capPerTable),
                    'is_active'   => true,
                ]);
            }
        }

        AuditLogger::log('AREA_CREATED', 'Áreas', "Área '{$area->nombre}' creada por " . auth()->user()?->name, auth()->user(), 'info');

        return response()->json($area, 201);
    }

    /**
     * PUT /api/admin/areas/{id}
     * Editar área.
     */
    public function update(UpdateAreaRequest $request, $id)
    {
        $area = Area::findOrFail($id);
        $validated = $request->validated();

        $updateData = [];

        if (array_key_exists('name', $validated) || array_key_exists('nombre', $validated)) {
            $nombre = trim(strip_tags($validated['name'] ?? $validated['nombre']));
            $updateData['name'] = $nombre;
            $updateData['nombre'] = $nombre;
        }

        if (array_key_exists('capacity', $validated) || array_key_exists('capacidad_personas', $validated)) {
            $capacidad = (int) ($validated['capacity'] ?? $validated['capacidad_personas']);
            $updateData['capacity'] = $capacidad;
            $updateData['capacidad_personas'] = $capacidad;
        }

        if (array_key_exists('tables_count', $validated) || array_key_exists('numero_mesas', $validated)) {
            $numMesas = (int) ($validated['tables_count'] ?? $validated['numero_mesas']);
            $updateData['tables_count'] = $numMesas;
            $updateData['numero_mesas'] = $numMesas;
        }

        if (array_key_exists('is_active', $validated) || array_key_exists('active', $validated)) {
            $isActive = (bool) ($validated['is_active'] ?? $validated['active']);
            $updateData['is_active'] = $isActive;
            $updateData['active'] = $isActive;
        }

        $area->update($updateData);

        AuditLogger::log('AREA_UPDATED', 'Áreas', "Área '{$area->nombre}' actualizada", auth()->user(), 'info');

        return response()->json($area);
    }

    /**
     * PATCH /api/admin/areas/{id}/toggle
     * Activar/desactivar área.
     */
    public function toggle($id)
    {
        $area = Area::findOrFail($id);
        $area->is_active = !($area->is_active ?? $area->active ?? true);
        $area->active = $area->is_active;
        $area->save();

        $statusText = $area->is_active ? 'activada' : 'desactivada';
        AuditLogger::log('AREA_TOGGLED', 'Áreas', "Área '{$area->nombre}' fue {$statusText}", auth()->user(), 'info');

        return response()->json([
            'message'   => "Área '{$area->nombre}' {$statusText} correctamente.",
            'is_active' => $area->is_active,
            'status'    => $area->is_active ? 'activo' : 'inactivo'
        ]);
    }

    /**
     * DELETE /api/admin/areas/{id}
     * Eliminación permanente/soft delete con validación de que no tenga pedidos activos asociados.
     */
    public function destroy($id)
    {
        $area = Area::with('mesas')->findOrFail($id);

        // Extract table numbers for this area
        $tableNumbers = $area->mesas->pluck('numero_mesa')->toArray();

        // Check if there are active orders associated with tables in this area
        if (!empty($tableNumbers)) {
            $hasActiveOrders = Order::whereIn('table_number', $tableNumbers)
                ->whereIn('status', ['pending', 'in_preparation', 'ready', 'en_proceso', 'pendiente', 'en_preparacion'])
                ->exists();

            if ($hasActiveOrders) {
                return response()->json([
                    'message' => "No se puede eliminar el área '{$area->nombre}' porque tiene pedidos activos asociados en sus mesas."
                ], 422);
            }
        }

        $areaName = $area->nombre ?? $area->name;
        $area->delete();

        AuditLogger::log('AREA_DELETED', 'Áreas', "Área '{$areaName}' fue eliminada", auth()->user(), 'warning');

        return response()->json([
            'message' => "Área '{$areaName}' eliminada correctamente."
        ]);
    }

    /**
     * GET /api/admin/areas/{id}/mesas
     * Lista mesas de un área.
     */
    public function getMesas($id)
    {
        $area = Area::findOrFail($id);
        $mesas = Mesa::where('area_id', $area->id)
                     ->orderBy('numero_mesa', 'asc')
                     ->get();

        // Obtener órdenes activas con número de mesa (la mesa sigue ocupada si está pendiente, en preparación o lista para servir)
        $ordenesActivas = \App\Models\Order::whereIn('status', ['pending', 'preparing', 'ready'])
            ->whereNotNull('table_number')
            ->get()
            ->keyBy('table_number');

        $mesasConEstado = $mesas->map(function ($mesa) use ($ordenesActivas) {
            $ordenActiva = $ordenesActivas->get((string) $mesa->numero_mesa);
            return [
                'id'          => $mesa->id,
                'numero_mesa' => $mesa->numero_mesa,
                'capacidad'   => $mesa->capacidad,
                'is_active'   => $mesa->is_active,
                'area_id'     => $mesa->area_id,
                'estado'      => !$mesa->is_active
                    ? 'inactiva'
                    : ($ordenActiva ? 'ocupada' : 'libre'),
                'orden_activa' => $ordenActiva ? [
                    'id'           => $ordenActiva->id,
                    'folio'        => $ordenActiva->folio,
                    'total'        => (float) $ordenActiva->total_amount,
                    'status'       => $ordenActiva->status,
                    'created_at'   => $ordenActiva->created_at?->format('Y-m-d H:i:s'),
                ] : null,
            ];
        });

        return response()->json([
            'area_id' => $area->id,
            'nombre'  => $area->nombre ?? $area->name,
            'mesas'   => $mesasConEstado,
            'total'   => $mesas->count(),
        ]);
    }

    /**
     * POST /api/admin/areas/{id}/mesas
     * Agregar mesa al área.
     */
    public function storeMesa(Request $request, $id)
    {
        $area = Area::findOrFail($id);

        $request->validate([
            'numero_mesa' => 'nullable|integer|min:1',
            'capacidad'   => 'nullable|integer|min:1',
        ]);

        $maxNum = Mesa::where('area_id', $area->id)->max('numero_mesa') ?? 0;
        $numMesa = (int) ($request->input('numero_mesa') ?? ($maxNum + 1));
        $capacidad = (int) ($request->input('capacidad') ?? 4);

        $mesa = Mesa::create([
            'area_id'     => $area->id,
            'numero_mesa' => $numMesa,
            'capacidad'   => $capacidad,
            'is_active'   => true,
        ]);

        // Update area table count
        $newCount = Mesa::where('area_id', $area->id)->count();
        $area->update([
            'numero_mesas' => $newCount,
            'tables_count' => $newCount,
        ]);

        AuditLogger::log('MESA_CREATED', 'Áreas', "Mesa #{$numMesa} agregada al área '{$area->nombre}'", auth()->user(), 'info');

        return response()->json($mesa, 201);
    }
}
