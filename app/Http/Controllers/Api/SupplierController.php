<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Ingredient;
use App\Models\Supplier;
use App\Models\SupplierSpecialty;
use Illuminate\Http\Request;
use Carbon\Carbon;

class SupplierController extends Controller
{
    /**
     * Map day abbreviation in Spanish
     */
    private function getTodayAbbreviation(): string
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

        return $daysMap[Carbon::now()->dayOfWeekIso] ?? 'Lun';
    }

    /**
     * Format supplier object
     */
    private function formatSupplier(Supplier $supplier): array
    {
        return [
            'id'            => $supplier->id,
            'company_name'  => $supplier->company_name,
            'contact_name'  => $supplier->contact_name,
            'specialty'     => $supplier->specialty,
            'phone'         => $supplier->phone,
            'email'         => $supplier->email,
            'delivery_days' => is_array($supplier->delivery_days) ? $supplier->delivery_days : [],
            'active'        => (bool) $supplier->active,
            'is_active'     => (bool) $supplier->active,
            'created_at'    => $supplier->created_at ? $supplier->created_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * GET /api/admin/suppliers
     */
    public function index(Request $request)
    {
        $query = Supplier::query();

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('company_name', 'like', "%{$search}%")
                  ->orWhere('contact_name', 'like', "%{$search}%")
                  ->orWhere('specialty', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Specialty filter
        if ($request->filled('especialidad') && $request->especialidad !== 'all') {
            $query->where('specialty', $request->especialidad);
        }

        // Active status filter
        if ($request->filled('estado') && $request->estado !== 'all') {
            $estado = $request->estado;
            if (in_array($estado, ['activo', 'activos', 'true', '1'])) {
                $query->where('active', true);
            } elseif (in_array($estado, ['inactivo', 'inactivos', 'false', '0'])) {
                $query->where('active', false);
            }
        }

        $suppliers = $query->orderBy('company_name', 'asc')->get();
        $formatted = $suppliers->map(fn($s) => $this->formatSupplier($s));

        // Resumen stats
        $totalCount = Supplier::count();
        $activeCount = Supplier::where('active', true)->count();
        $todayAbbrev = $this->getTodayAbbreviation();

        $activeSuppliers = Supplier::where('active', true)->get();
        $entregasHoy = 0;

        foreach ($activeSuppliers as $s) {
            $days = is_array($s->delivery_days) ? $s->delivery_days : [];
            // Match Spanish day abbreviations (e.g. Lun, Mar, Mié/Mie, Jue, Vie, Sáb/Sab, Dom)
            foreach ($days as $day) {
                if (mb_strtolower($day) === mb_strtolower($todayAbbrev) ||
                    (mb_strtolower($todayAbbrev) === 'mié' && mb_strtolower($day) === 'mie') ||
                    (mb_strtolower($todayAbbrev) === 'sáb' && mb_strtolower($day) === 'sab')) {
                    $entregasHoy++;
                    break;
                }
            }
        }

        return response()->json([
            'suppliers' => $formatted,
            'resumen'   => [
                'total'          => $totalCount,
                'activos'        => $activeCount,
                'entregas_hoy'   => $entregasHoy,
                'dia_semana_hoy' => $todayAbbrev,
            ]
        ]);
    }

    /**
     * GET /api/admin/suppliers/specialties
     */
    public function getSpecialties()
    {
        $specialties = SupplierSpecialty::orderBy('name', 'asc')->pluck('name')->values()->all();

        return response()->json([
            'specialties' => $specialties
        ]);
    }

    /**
     * POST /api/admin/suppliers/specialties
     */
    public function storeSpecialty(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:supplier_specialties,name',
        ], [
            'name.required' => 'El nombre de la especialidad es obligatorio.',
            'name.max'      => 'El nombre no debe superar los 100 caracteres.',
            'name.unique'   => 'Esta especialidad ya existe.',
        ]);

        $name = trim($request->name);
        $specialty = SupplierSpecialty::create(['name' => $name]);

        return response()->json([
            'message'   => 'Especialidad creada correctamente',
            'specialty' => $specialty->name,
        ], 201);
    }

    /**
     * POST /api/admin/suppliers
     */
    public function store(StoreSupplierRequest $request)
    {
        $validated = $request->validated();

        $specialty = SupplierSpecialty::findOrFail($validated['specialty_id']);

        $supplier = Supplier::create([
            'company_name'  => trim(strip_tags($validated['company_name'])),
            'contact_name'  => isset($validated['contact_name']) && $validated['contact_name'] !== null
                ? trim(strip_tags($validated['contact_name']))
                : null,
            'specialty'     => $specialty->name,
            'phone'         => trim($validated['phone']),
            'email'         => isset($validated['email']) && $validated['email'] !== null ? trim($validated['email']) : null,
            'delivery_days' => $validated['delivery_days'],
            'active'        => (bool) ($validated['is_active'] ?? ($validated['active'] ?? true)),
        ]);

        return response()->json([
            'message'  => 'Proveedor creado correctamente',
            'supplier' => $this->formatSupplier($supplier),
        ], 201);
    }

    /**
     * PUT /api/admin/suppliers/{id}
     */
    public function update(UpdateSupplierRequest $request, $id)
    {
        $supplier = Supplier::findOrFail($id);
        $validated = $request->validated();

        $data = [];

        if (array_key_exists('company_name', $validated)) {
            $data['company_name'] = trim(strip_tags($validated['company_name']));
        }

        if (array_key_exists('contact_name', $validated)) {
            $data['contact_name'] = $validated['contact_name'] !== null
                ? trim(strip_tags($validated['contact_name']))
                : null;
        }

        if (array_key_exists('specialty_id', $validated)) {
            $specialty = SupplierSpecialty::findOrFail($validated['specialty_id']);
            $data['specialty'] = $specialty->name;
        }

        if (array_key_exists('phone', $validated)) {
            $data['phone'] = trim($validated['phone']);
        }

        if (array_key_exists('email', $validated)) {
            $data['email'] = $validated['email'] !== null ? trim($validated['email']) : null;
        }

        if (array_key_exists('delivery_days', $validated)) {
            $data['delivery_days'] = $validated['delivery_days'];
        }

        if (array_key_exists('is_active', $validated)) {
            $data['active'] = (bool) $validated['is_active'];
        } elseif (array_key_exists('active', $validated)) {
            $data['active'] = (bool) $validated['active'];
        }

        $supplier->update($data);

        return response()->json([
            'message'  => 'Proveedor actualizado correctamente',
            'supplier' => $this->formatSupplier($supplier),
        ]);
    }

    /**
     * DELETE /api/admin/suppliers/{id}
     */
    public function destroy($id)
    {
        $supplier = Supplier::findOrFail($id);

        // Check if supplier is linked to ingredients
        $hasIngredients = Ingredient::where('supplier_id', $supplier->id)->exists();
        if ($hasIngredients) {
            return response()->json([
                'message' => 'No se puede eliminar un proveedor vinculado a ingredientes'
            ], 422);
        }

        $supplier->delete();

        return response()->json([
            'message' => 'Proveedor eliminado correctamente'
        ]);
    }

    /**
     * PATCH /api/admin/suppliers/{id}/toggle
     */
    public function toggle($id)
    {
        $supplier = Supplier::findOrFail($id);
        $supplier->active = !$supplier->active;
        $supplier->save();

        return response()->json([
            'message'  => 'Estado de proveedor actualizado',
            'supplier' => $this->formatSupplier($supplier),
        ]);
    }
}
