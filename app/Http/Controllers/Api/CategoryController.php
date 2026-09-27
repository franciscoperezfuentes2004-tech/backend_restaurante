<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Models\Category;
use App\Services\AuditLogger;
use App\Services\ImageCompressionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('dishes')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($cat) {
                return [
                    'id'                  => $cat->id,
                    'name'                => $cat->name,
                    'slug'                => $cat->slug,
                    'image_url'           => $cat->image_url,
                    'active'              => (bool) $cat->active,
                    'limitar_dias'        => (bool) ($cat->limitar_dias ?? ($cat->days !== null && count($cat->days ?? []) < 7)),
                    'dias_disponibilidad' => $cat->dias_disponibilidad ?? $cat->days,
                    'time_start'          => $cat->time_start,
                    'time_end'            => $cat->time_end,
                    'days'                => $cat->days ?? $cat->dias_disponibilidad ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
                    'dishes_count'        => (int) $cat->dishes_count,
                ];
            });

        return response()->json(['categories' => $categories]);
    }

    public function publicMenu(Request $request)
    {
        $categories = Category::where('active', true)
            ->with(['dishes' => function ($query) {
                // NO excluir los platillos sin stock o no disponibles; el frontend muestra 'NO DISPONIBLE'
                $query->with('extras')->orderBy('id', 'asc');
            }])
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($cat) {
                return [
                    'id'           => $cat->id,
                    'name'         => $cat->name,
                    'slug'         => $cat->slug,
                    'image_url'    => $cat->image_url,
                    'active'       => (bool) $cat->active,
                    'time_start'   => $cat->time_start,
                    'time_end'     => $cat->time_end,
                    'days'         => $cat->days ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
                    'dishes'       => $cat->dishes->map(function ($dish) {
                        return [
                            'id'                  => $dish->id,
                            'category_id'         => $dish->category_id,
                            'name'                => $dish->name,
                            'nombre'              => $dish->name,
                            'slug'                => $dish->slug,
                            'description'         => $dish->description,
                            'descripcion'         => $dish->description,
                            'price'               => (float) $dish->price,
                            'precio'              => (float) $dish->price,
                            'image_url'           => $dish->image_url,
                            'imagen'              => $dish->image_url,
                            'allergens'           => $dish->allergens ?? [],
                            'ingredients'         => $dish->ingredients ?? [],
                            'allow_extras'        => (bool) $dish->allow_extras,
                            'allow_observations'  => (bool) $dish->allow_observations,
                            'allow_spice_level'   => (bool) $dish->allow_spice_level,
                            'is_available'        => (bool) $dish->is_available,
                            'disponible'          => (bool) $dish->is_available,
                            'is_featured'         => (bool) $dish->is_featured,
                            'extras'              => $dish->extras->map(fn($e) => [
                                'id'       => $e->id,
                                'name'     => $e->name,
                                'nombre'   => $e->name,
                                'price'    => (float) $e->price,
                                'precio'   => (float) $e->price,
                                'required' => (bool) $e->required,
                            ])->values()->all(),
                        ];
                    })->values()->all(),
                ];
            });

        return response()->json([
            'categories' => $categories,
            'menu'       => $categories,
        ]);
    }

    public function indicators()
    {
        $sub30Days = now()->subDays(30);

        $categories = Category::all();

        if ($categories->isEmpty()) {
            return response()->json([
                'mas_vendida'     => ['name' => null, 'pedidos' => 0],
                'poco_movimiento' => ['name' => null, 'pedidos' => 0],
                'sin_ventas'      => ['name' => null, 'pedidos' => 0],
            ]);
        }

        $stats = $categories->map(function ($cat) use ($sub30Days) {
            $totalPedidos = (int) DB::table('order_items')
                ->join('dishes', 'order_items.dish_id', '=', 'dishes.id')
                ->join('orders', 'order_items.order_id', '=', 'orders.id')
                ->where('dishes.category_id', $cat->id)
                ->where('orders.created_at', '>=', $sub30Days)
                ->where('orders.status', '!=', 'cancelled')
                ->sum('order_items.quantity');

            return [
                'name'    => $cat->name,
                'pedidos' => $totalPedidos,
            ];
        });

        // 1. Más vendida: mayor count de pedidos (> 0)
        $masVendidaItem = $stats->where('pedidos', '>', 0)->sortByDesc('pedidos')->first();
        $masVendida = $masVendidaItem
            ? ['name' => $masVendidaItem['name'], 'pedidos' => $masVendidaItem['pedidos']]
            : ['name' => null, 'pedidos' => 0];

        // 2. Poco movimiento: entre 1 y 5 pedidos
        $pocoMovimientoItem = $stats->filter(fn($item) => $item['pedidos'] >= 1 && $item['pedidos'] <= 5)
            ->sortBy('pedidos')
            ->first();
        $pocoMovimiento = $pocoMovimientoItem
            ? ['name' => $pocoMovimientoItem['name'], 'pedidos' => $pocoMovimientoItem['pedidos']]
            : ['name' => null, 'pedidos' => 0];

        // 3. Sin ventas: 0 pedidos
        $sinVentasItem = $stats->filter(fn($item) => $item['pedidos'] === 0)->first();
        $sinVentas = $sinVentasItem
            ? ['name' => $sinVentasItem['name'], 'pedidos' => 0]
            : ['name' => null, 'pedidos' => 0];

        return response()->json([
            'mas_vendida'     => $masVendida,
            'poco_movimiento' => $pocoMovimiento,
            'sin_ventas'      => $sinVentas,
        ]);
    }

    private function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($name);
        if (empty($baseSlug)) {
            $baseSlug = 'categoria';
        }

        $slug = $baseSlug;
        $counter = 1;

        while (
            Category::where('slug', $slug)
                ->when($ignoreId, fn($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $counter++;
            $slug = "{$baseSlug}-{$counter}";
        }

        return $slug;
    }

    public function store(StoreCategoryRequest $request)
    {
        $data = $request->validated();

        $data['slug'] = $this->makeUniqueSlug($data['name']);
        if (!isset($data['active'])) {
            $data['active'] = true;
        }

        $imageFile = $request->file('imagen') ?? $request->file('image') ?? $request->file('foto');
        if ($imageFile) {
            $compressed = ImageCompressionService::compressAndStore($imageFile, 'categories', 800, 75);
            $data['image_url'] = $compressed['url'];
        }
        unset($data['image'], $data['imagen'], $data['foto']);

        $category = new Category();
        $category->fill($data);

        // 1. Leer el estado exacto del interruptor (convierte 'true'/'false' o 1/0 a booleano real)
        $limitarDias = $request->has('limitar_dias')
            ? $request->boolean('limitar_dias')
            : ($request->has('days') && !empty($request->input('days')));

        // 2. Asignar el booleano a la base de datos
        $category->limitar_dias = $limitarDias;

        // 3. LA REGLA DE ORO DEL INTERRUPTOR:
        // Si el interruptor está APAGADO (!), forzamos los días a NULL en la base de datos.
        // Si está ENCENDIDO, guardamos el arreglo de días que mandó React.
        if (!$limitarDias) {
            $category->dias_disponibilidad = null;
            $category->days = null;
        } else {
            $dias = $request->input('dias_disponibilidad', $request->input('days', []));
            $category->dias_disponibilidad = $dias;
            $category->days = $dias;
        }

        $category->save();

        NotificationService::create(
            'category_created',
            'Categoría Creada',
            "Se creó la categoría '{$category->name}'",
            ['category_id' => $category->id]
        );

        AuditLogger::log('CATEGORY_CREATED', 'Categorías', "Categoría '{$category->name}' creada", auth()->user(), 'info');

        return response()->json([
            'id'                  => $category->id,
            'name'                => $category->name,
            'slug'                => $category->slug,
            'image_url'           => $category->image_url,
            'active'              => (bool) $category->active,
            'limitar_dias'        => (bool) $category->limitar_dias,
            'dias_disponibilidad' => $category->dias_disponibilidad,
            'time_start'          => $category->time_start,
            'time_end'            => $category->time_end,
            'days'                => $category->days ?? $category->dias_disponibilidad ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
            'dishes_count'        => 0,
        ], 201);
    }

    public function show(Category $category)
    {
        $category->loadCount('dishes');

        return response()->json([
            'id'                  => $category->id,
            'name'                => $category->name,
            'slug'                => $category->slug,
            'image_url'           => $category->image_url,
            'active'              => (bool) $category->active,
            'limitar_dias'        => (bool) ($category->limitar_dias ?? ($category->days !== null && count($category->days ?? []) < 7)),
            'dias_disponibilidad' => $category->dias_disponibilidad ?? $category->days,
            'time_start'          => $category->time_start,
            'time_end'            => $category->time_end,
            'days'                => $category->days ?? $category->dias_disponibilidad ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
            'dishes_count'        => (int) $category->dishes_count,
        ]);
    }

    public function update(UpdateCategoryRequest $request, $id)
    {
        $category = Category::findOrFail($id);
        $data = $request->validated();

        if (isset($data['name'])) {
            $data['slug'] = $this->makeUniqueSlug($data['name'], $category->id);
        }

        $imageFile = $request->file('imagen') ?? $request->file('image') ?? $request->file('foto');
        if ($imageFile) {
            ImageCompressionService::deleteOldImage($category->image_url, 'categories');
            $compressed = ImageCompressionService::compressAndStore($imageFile, 'categories', 800, 75);
            $data['image_url'] = $compressed['url'];
        }
        unset($data['image'], $data['imagen'], $data['foto']);

        $category->fill($data);

        // 1. Leer el estado exacto del interruptor (convierte 'true'/'false' o 1/0 a booleano real)
        $limitarDias = $request->has('limitar_dias')
            ? $request->boolean('limitar_dias')
            : ($request->has('days') ? !empty($request->input('days')) : (bool) $category->limitar_dias);

        // 2. Asignar el booleano a la base de datos
        $category->limitar_dias = $limitarDias;

        // 3. LA REGLA DE ORO DEL INTERRUPTOR:
        // Si el interruptor está APAGADO (!), forzamos los días a NULL en la base de datos.
        // Si está ENCENDIDO, guardamos el arreglo de días que mandó React.
        if (!$limitarDias) {
            $category->dias_disponibilidad = null;
            $category->days = null;
        } else {
            $dias = $request->input('dias_disponibilidad', $request->input('days', $category->days ?? []));
            $category->dias_disponibilidad = $dias;
            $category->days = $dias;
        }

        $category->save();
        $category->loadCount('dishes');

        NotificationService::create(
            'category_updated',
            'Categoría Editada',
            "Se actualizó la categoría '{$category->name}'",
            ['category_id' => $category->id]
        );

        AuditLogger::log('CATEGORY_UPDATED', 'Categorías', "Categoría '{$category->name}' actualizada", auth()->user(), 'info');

        return response()->json([
            'id'                  => $category->id,
            'name'                => $category->name,
            'slug'                => $category->slug,
            'image_url'           => $category->image_url,
            'active'              => (bool) $category->active,
            'limitar_dias'        => (bool) $category->limitar_dias,
            'dias_disponibilidad' => $category->dias_disponibilidad,
            'time_start'          => $category->time_start,
            'time_end'            => $category->time_end,
            'days'                => $category->days ?? $category->dias_disponibilidad ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
            'dishes_count'        => (int) $category->dishes_count,
        ]);
    }

    public function destroy($id)
    {
        $category = Category::findOrFail($id);

        // 1. Si existen platillos asociados vigentes (activos o inactivos), bloquear la eliminación
        if ($category->dishes()->exists()) {
            return response()->json([
                'message' => 'No se puede eliminar esta categoría porque contiene platillos asociados. Reasigna o elimina los platillos primero.'
            ], 422);
        }

        // 2. Si solo contiene platillos previamente eliminados en la papelera (Soft-Deleted),
        // purgarlos definitivamente para que PostgreSQL no choque con la restricción de llave foránea (ON DELETE RESTRICT)
        try {
            $trashedDishes = \App\Models\Dish::onlyTrashed()->where('category_id', $category->id)->get();
            foreach ($trashedDishes as $trashedDish) {
                $hasOrders = \Illuminate\Support\Facades\DB::table('order_items')
                    ->where('dish_id', $trashedDish->id)
                    ->exists();

                if (!$hasOrders) {
                    $trashedDish->forceDelete();
                }
            }

            $name = $category->name;
            $imageUrl = $category->image_url;
            $category->delete();
            ImageCompressionService::deleteOldImage($imageUrl, 'categories');
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => 'No se puede eliminar esta categoría porque contiene platillos asociados. Reasigna o elimina los platillos primero.'
            ], 422);
        }

        Cache::forget('landing_menu');
        Cache::forget('public_menu');
        Cache::forget('landing_settings');
        Cache::forget('restaurant_settings');

        NotificationService::create(
            'category_deleted',
            'Categoría Eliminada',
            "Se eliminó la categoría '{$name}'",
            ['category_id' => $id]
        );

        AuditLogger::log('CATEGORY_DELETED', 'Categorías', "Categoría '{$name}' eliminada", auth()->user(), 'warning');

        return response()->json(['message' => 'Categoría eliminada correctamente']);
    }

    public function toggle($id)
    {
        $category = Category::findOrFail($id);
        $category->active = !$category->active;
        $category->save();
        $category->loadCount('dishes');

        $statusStr = $category->active ? 'activada' : 'desactivada';
        NotificationService::create(
            'category_toggled',
            'Categoría Actualizada',
            "La categoría '{$category->name}' fue {$statusStr}",
            ['category_id' => $category->id, 'active' => $category->active]
        );

        AuditLogger::log('CATEGORY_TOGGLED', 'Categorías', "Categoría '{$category->name}' fue {$statusStr}", auth()->user(), 'info');

        return response()->json([
            'id'                  => $category->id,
            'name'                => $category->name,
            'slug'                => $category->slug,
            'image_url'           => $category->image_url,
            'active'              => (bool) $category->active,
            'limitar_dias'        => (bool) ($category->limitar_dias ?? ($category->days !== null && count($category->days ?? []) < 7)),
            'dias_disponibilidad' => $category->dias_disponibilidad ?? $category->days,
            'time_start'          => $category->time_start,
            'time_end'            => $category->time_end,
            'days'                => $category->days ?? $category->dias_disponibilidad ?? ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"],
            'dishes_count'        => (int) $category->dishes_count,
        ]);
    }
}
