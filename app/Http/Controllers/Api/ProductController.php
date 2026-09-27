<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Obtiene la receta de ingredientes asignada a un platillo/producto.
     */
    public function getRecipe(Request $request, $id = null)
    {
        // En caso de que se pase el objeto directamente por route-model-binding
        if ($id instanceof Dish || $id instanceof Product) {
            $producto = $id;
        } else {
            $dishId = $id ?? $request->route('producto') ?? $request->route('product') ?? $request->route('dish') ?? $request->route('id');
            if (is_object($dishId)) {
                $producto = $dishId;
            } else {
                $producto = Dish::find($dishId) ?? Product::find($dishId);
                if (!$producto && is_string($dishId)) {
                    $producto = Dish::where('slug', $dishId)->first();
                }
            }
        }

        if (!$producto) {
            return response()->json(['message' => 'Platillo o producto no encontrado'], 404);
        }

        $producto->load(['ingredientes', 'category']);

        $recetaFormatted = $producto->ingredientes->map(function ($ing) use ($producto) {
            return [
                'id'                 => $ing->id,
                'ingredient_id'      => $ing->id,
                'nombre'             => $ing->nombre ?? $ing->name,
                'name'               => $ing->name ?? $ing->nombre,
                'unidad_medida'      => $ing->unidad_medida ?? $ing->unit,
                'unit'               => $ing->unit ?? $ing->unidad_medida,
                'cantidad_requerida' => (float) ($ing->pivot->cantidad_requerida ?? 0),
                'costo_unitario'     => (float) ($ing->base_cost ?? $ing->costo_unitario ?? $ing->unit_cost ?? 0),
                'stock_actual'       => (float) ($ing->stock_actual ?? $ing->stock ?? 0),
                'pivot'              => [
                    'dish_id'            => $producto->id,
                    'ingredient_id'      => $ing->id,
                    'cantidad_requerida' => (float) ($ing->pivot->cantidad_requerida ?? 0),
                ]
            ];
        });

        return response()->json([
            'id'           => $producto->id,
            'nombre'       => $producto->name,
            'name'         => $producto->name,
            'receta'       => $recetaFormatted,
            'ingredientes' => $recetaFormatted,
            'producto'     => $producto,
            'dish'         => $producto,
        ]);
    }

    /**
     * Asigna o actualiza la receta de ingredientes para un platillo/producto.
     * React enviará un arreglo con formato:
     * [ 1 => ['cantidad_requerida' => 0.150], 5 => ['cantidad_requerida' => 2] ]
     */
    public function asignarReceta(Request $request, $id = null)
    {
        if ($id instanceof Dish || $id instanceof Product) {
            $producto = $id;
        } else {
            $dishId = $id ?? $request->route('producto') ?? $request->route('product') ?? $request->route('dish') ?? $request->route('id');
            if (is_object($dishId)) {
                $producto = $dishId;
            } else {
                $producto = Dish::find($dishId) ?? Product::findOrFail($dishId);
            }
        }

        $rawReceta = $request->input('ingredientes') ?? $request->input('receta') ?? $request->all();

        $receta = [];
        if (is_array($rawReceta)) {
            foreach ($rawReceta as $key => $value) {
                if (is_array($value) && (isset($value['cantidad_requerida']) || isset($value['cantidad']))) {
                    $ingId = isset($value['ingredient_id']) ? (int) $value['ingredient_id'] : (isset($value['id']) ? (int) $value['id'] : (int) $key);
                    $cant = isset($value['cantidad_requerida']) ? (float) $value['cantidad_requerida'] : (float) $value['cantidad'];
                    if ($ingId > 0 && $cant >= 0) {
                        $receta[$ingId] = ['cantidad_requerida' => $cant];
                    }
                } elseif (is_numeric($value)) {
                    $ingId = (int) $key;
                    if ($ingId > 0 && (float) $value >= 0) {
                        $receta[$ingId] = ['cantidad_requerida' => (float) $value];
                    }
                }
            }
        }

        // Sync actualiza automáticamente la tabla pivote de forma limpia
        $producto->ingredientes()->sync($receta);

        return response()->json([
            'mensaje'  => 'Receta asignada exitosamente',
            'message'  => 'Receta asignada exitosamente',
            'producto' => $producto->load('ingredientes')
        ]);
    }
}