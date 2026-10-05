<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IndexIngredientRequest;
use App\Http\Requests\StoreIngredientRequest;
use App\Http\Requests\UpdateIngredientRequest;
use App\Models\Ingredient;
use App\Models\IngredientCategory;
use App\Models\Supplier;
use App\Models\Stock;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class IngredientController extends Controller
{
    /**
     * Format ingredient for JSON output
     */
    private function formatIngredient(Ingredient $ingredient): array
    {
        $ingredient->loadMissing(['supplier', 'stock']);

        $categoryObj = IngredientCategory::where('name', $ingredient->category)->first();

        $minQty = $ingredient->stock 
            ? (float) $ingredient->stock->min_quantity 
            : (float) ($ingredient->stock_minimo ?? 0);

        $currentQty = $ingredient->stock 
            ? (float) $ingredient->stock->quantity 
            : (float) ($ingredient->stock_actual ?? 0);

        return [
            'id'              => $ingredient->id,
            'name'            => $ingredient->name,
            'category_id'     => $categoryObj?->id,
            'category'        => $ingredient->category,
            'unit'            => $ingredient->unit,
            'unit_of_measure' => $ingredient->unit,
            'supplier_id'     => $ingredient->supplier_id,
            'supplier_name'   => $ingredient->supplier ? ($ingredient->supplier->company_name ?? $ingredient->supplier->name) : null,
            'notes'           => $ingredient->notes,
            'stock_actual'    => $currentQty,
            'current_stock'   => $currentQty,
            'stock_minimo'    => $minQty,
            'min_stock'       => $minQty,
            'created_at'      => $ingredient->created_at ? $ingredient->created_at->format('Y-m-d H:i:s') : null,
        ];
    }

    /**
     * GET /api/admin/ingredients
     */
    public function index(IndexIngredientRequest $request)
    {
        $validated = $request->validated();
        $query = Ingredient::with(['supplier', 'stock']);

        if (!empty($validated['supplier_id'])) {
            $query->where('supplier_id', $validated['supplier_id']);
        }

        if (isset($validated['search']) && $validated['search'] !== '') {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhereHas('category', function ($cq) use ($search) {
                      $cq->where('name', 'like', "%{$search}%");
                  })
                  ->orWhereHas('supplier', function ($sq) use ($search) {
                      $sq->where('company_name', 'like', "%{$search}%")
                        ->orWhere('contact_name', 'like', "%{$search}%");
                  });
            });
        }

        $ingredients = $query->orderBy('name', 'asc')->get();
        $formatted = $ingredients->map(fn($item) => $this->formatIngredient($item));

        // Resumen stats
        $totalCount = Ingredient::count();
        
        $distinctCategoriesCount = IngredientCategory::count();
        if ($distinctCategoriesCount === 0) {
            $distinctCategoriesCount = Ingredient::distinct('category')->whereNotNull('category')->count('category');
        }

        $linkedSuppliersCount = Ingredient::whereNotNull('supplier_id')->distinct('supplier_id')->count('supplier_id');

        return response()->json([
            'ingredients' => $formatted,
            'resumen'     => [
                'total'                 => $totalCount,
                'categorias_activas'    => $distinctCategoriesCount,
                'proveedores_vinculados'=> $linkedSuppliersCount,
            ]
        ]);
    }

    /**
     * GET /api/admin/ingredients/categories
     */
    public function getCategories()
    {
        $categories = IngredientCategory::orderBy('name', 'asc')->get(['id', 'name']);

        return response()->json([
            'categories' => $categories
        ]);
    }

    /**
     * POST /api/admin/ingredients/categories
     */
    public function storeCategory(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:ingredient_categories,name',
        ], [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.max'      => 'El nombre no debe superar los 100 caracteres.',
            'name.unique'   => 'Esta categoría ya existe.',
        ]);

        $categoryName = trim($request->name);
        $category = IngredientCategory::create(['name' => $categoryName]);

        return response()->json([
            'message'  => 'Categoría creada correctamente',
            'category' => $category,
        ], 201);
    }

    /**
     * POST /api/admin/ingredients
     */
    public function store(StoreIngredientRequest $request)
    {
        $validated = $request->validated();

        // ── Resolver category_id → nombre de categoría (columna DB: category) ──
        $category = IngredientCategory::findOrFail($validated['category_id']);

        $minStock = isset($validated['min_stock']) 
            ? (float) $validated['min_stock'] 
            : (isset($validated['stock_minimo']) ? (float) $validated['stock_minimo'] : 0.0);

        $ingredient = Ingredient::create([
            'name'         => trim(strip_tags($validated['name'])),
            'category'     => $category->name,
            // unit_of_measure (frontend) → unit (columna DB)
            'unit'         => $validated['unit_of_measure'],
            'base_cost'    => 0.00,
            'supplier_id'  => $validated['supplier_id'] ?? null,
            'stock_actual' => 0.0,
            'stock_minimo' => $minStock,
            'notes'        => isset($validated['notes'])
                ? trim(strip_tags($validated['notes']))
                : null,
        ]);

        // Registrar / sincronizar de inmediato en la tabla 'stock' para control de existencias y alertas
        Stock::updateOrCreate(
            ['ingredient_id' => $ingredient->id],
            [
                'quantity'        => 0.0,
                'min_quantity'    => $minStock,
                'supplier_id'     => $ingredient->supplier_id,
                'last_updated_by' => auth()->id(),
            ]
        );

        return response()->json([
            'message'    => 'Ingrediente creado correctamente',
            'ingredient' => $this->formatIngredient($ingredient),
        ], 201);
    }

    /**
     * PUT /api/admin/ingredients/{id}
     */
    public function update(UpdateIngredientRequest $request, $id)
    {
        $ingredient = Ingredient::findOrFail($id);
        $validated  = $request->validated();

        $data = [];

        if (array_key_exists('name', $validated)) {
            $data['name'] = trim(strip_tags($validated['name']));
        }

        // ── Resolver category_id → nombre de categoría (columna DB: category) ──
        if (array_key_exists('category_id', $validated)) {
            $category         = IngredientCategory::findOrFail($validated['category_id']);
            $data['category'] = $category->name;
        }

        // ── unit_of_measure (frontend) → unit (columna DB) ─────────────────
        if (array_key_exists('unit_of_measure', $validated)) {
            $data['unit'] = $validated['unit_of_measure'];
        }

        if (array_key_exists('supplier_id', $validated)) {
            $data['supplier_id'] = $validated['supplier_id'];
        }

        $minStock = null;
        if (array_key_exists('min_stock', $validated)) {
            $minStock = (float) $validated['min_stock'];
        } elseif (array_key_exists('stock_minimo', $validated)) {
            $minStock = (float) $validated['stock_minimo'];
        }

        if ($minStock !== null) {
            $data['stock_minimo'] = $minStock;
        }

        if (array_key_exists('notes', $validated)) {
            $data['notes'] = $validated['notes'] !== null
                ? trim(strip_tags($validated['notes']))
                : null;
        }

        $ingredient->update($data);

        // Mantener sincronizado el registro en 'stock'
        if ($minStock !== null || array_key_exists('supplier_id', $validated)) {
            $stockData = [
                'supplier_id'     => $ingredient->supplier_id,
                'last_updated_by' => auth()->id(),
            ];
            if ($minStock !== null) {
                $stockData['min_quantity'] = $minStock;
            }
            Stock::updateOrCreate(
                ['ingredient_id' => $ingredient->id],
                $stockData
            );
        }

        return response()->json([
            'message'    => 'Ingrediente actualizado correctamente',
            'ingredient' => $this->formatIngredient($ingredient),
        ]);
    }

    /**
     * DELETE /api/admin/ingredients/{id}
     */
    public function destroy($id)
    {
        $ingredient = Ingredient::findOrFail($id);

        // Check if stock_movements table exists and has movements for this ingredient
        if (Schema::hasTable('stock_movements')) {
            $hasMovements = DB::table('stock_movements')->where('ingredient_id', $ingredient->id)->exists();
            if ($hasMovements) {
                return response()->json([
                    'message' => 'No se puede eliminar un ingrediente con movimientos de stock'
                ], 422);
            }
        }

        $ingredient->delete();

        return response()->json([
            'message' => 'Ingrediente eliminado correctamente'
        ]);
    }
}
