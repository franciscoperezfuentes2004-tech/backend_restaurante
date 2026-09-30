<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Stock;
use Illuminate\Http\Request;

class GetPosMenuController extends Controller
{
    /**
     * Entrega el catálogo de platillos pre-filtrado para el POS y comandas de meseros.
     * 1. Excluye platillos inactivos (where is_active=true / is_available=true) y soft-deleted.
     * 2. Excluye platillos de categorías inactivas.
     * 3. Evalúa el stock en tiempo real y marca is_sold_out => true si algún ingrediente llegó a 0.
     */
    public function index(Request $request)
    {
        // 1. Obtener ingredientes con stock en 0 o menor
        $outOfStockRecords = Stock::with('ingredient')
            ->where('quantity', '<=', 0)
            ->get();

        $outOfStockIds = [];
        $outOfStockNames = [];

        foreach ($outOfStockRecords as $st) {
            $outOfStockIds[$st->ingredient_id] = true;
            if ($st->ingredient && !empty($st->ingredient->name)) {
                $outOfStockNames[mb_strtolower(trim($st->ingredient->name))] = true;
            }
        }

        // 2. Consulta con filtro estricto de disponibilidad y categoría activa
        $query = Dish::with(['category', 'extras'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->where(function ($q) {
                $q->where('is_active', true)
                  ->where('is_available', true);
            })
            ->whereHas('category', function ($catQ) {
                $catQ->where(function ($c) {
                    $c->where('categories.active', true);
                    if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'is_active')) {
                        $c->orWhere('categories.is_active', true);
                    }
                });
            });

        if ($request->filled('category_id') && $request->input('category_id') !== 'all' && $request->input('category_id') !== 'Todos') {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $dishes = $query->orderBy('name', 'asc')->get();

        // 3. Mapeo y evaluación preventiva de inventario (is_sold_out)
        $formattedDishes = $dishes->map(function (Dish $dish) use ($outOfStockIds, $outOfStockNames) {
            $isSoldOut = (bool) ($dish->is_sold_out ?? false);

            if (!$isSoldOut && !empty($dish->ingredients) && is_array($dish->ingredients)) {
                foreach ($dish->ingredients as $ing) {
                    if (is_numeric($ing) && isset($outOfStockIds[(int) $ing])) {
                        $isSoldOut = true;
                        break;
                    }
                    if (is_string($ing)) {
                        $ingLower = mb_strtolower(trim($ing));
                        if (isset($outOfStockNames[$ingLower])) {
                            $isSoldOut = true;
                            break;
                        }
                        foreach (array_keys($outOfStockNames) as $outName) {
                            if (!empty($outName) && str_contains($ingLower, $outName)) {
                                $isSoldOut = true;
                                break 2;
                            }
                        }
                    }
                }
            }

            return [
                'id'                 => $dish->id,
                'name'               => $dish->name,
                'slug'               => $dish->slug,
                'description'        => $dish->description,
                'price'              => (float) $dish->price,
                'image_url'          => $dish->image_url,
                'category_id'        => $dish->category_id,
                'category_name'      => $dish->category?->name ?? 'Sin categoría',
                'allergens'          => $dish->allergens ?? [],
                'ingredients'        => $dish->ingredients ?? [],
                'extras'             => $dish->extras ? $dish->extras->map(fn($e) => [
                    'id'    => $e->id,
                    'name'  => $e->name,
                    'price' => (float) $e->price
                ])->values()->all() : [],
                'allow_observations' => (bool) $dish->allow_observations,
                'allow_spice_level'  => (bool) $dish->allow_spice_level,
                'is_available'       => (bool) $dish->is_available,
                'is_active'          => (bool) ($dish->is_active ?? $dish->is_available),
                'is_sold_out'        => $isSoldOut,
                'reviews_count'      => (int) ($dish->reviews_count ?? 0),
                'reviews_avg_rating' => $dish->reviews_avg_rating !== null ? round((float) $dish->reviews_avg_rating, 1) : null,
            ];
        });

        $categoriesQuery = Category::query();
        if (\Illuminate\Support\Facades\Schema::hasColumn('categories', 'is_active')) {
            $categoriesQuery->where(function ($c) {
                $c->where('active', true)->orWhere('is_active', true);
            });
        } else {
            $categoriesQuery->where('active', true);
        }
        $categories = $categoriesQuery->orderBy('name', 'asc')->get(['id', 'name', 'slug']);

        return response()->json([
            'dishes'     => $formattedDishes,
            'categories' => $categories,
            'total'      => $formattedDishes->count(),
        ]);
    }

    /**
     * Invocación directa del controlador
     */
    public function __invoke(Request $request)
    {
        return $this->index($request);
    }
}
