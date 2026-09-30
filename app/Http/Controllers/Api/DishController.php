<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDishRequest;
use App\Http\Requests\UpdateDishRequest;
use App\Models\Dish;
use App\Services\AuditLogger;
use App\Services\ImageCompressionService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class DishController extends Controller
{
    private function formatDish(Dish $dish): array
    {
        $dish->loadMissing('category', 'extras');

        $reviewsCount = $dish->reviews_count !== null
            ? (int) $dish->reviews_count
            : (int) $dish->reviews()->count();

        $reviewsAvgRating = $dish->reviews_avg_rating !== null
            ? round((float) $dish->reviews_avg_rating, 1)
            : ($reviewsCount > 0 ? round((float) $dish->reviews()->avg('rating'), 1) : null);

        return [
            'id'                 => $dish->id,
            'name'               => $dish->name,
            'slug'               => $dish->slug,
            'description'        => $dish->description,
            'price'              => (float) $dish->price,
            'image_url'          => $dish->image_url,
            'category_id'        => $dish->category_id,
            'category_name'      => $dish->category ? $dish->category->name : 'Sin categoría',
            'allergens'          => $dish->allergens ?? [],
            'ingredients'        => $dish->ingredients ?? [],
            'extras'             => $dish->extras ? $dish->extras->map(fn($e) => [
                'id'    => $e->id,
                'name'  => $e->name,
                'price' => (float) $e->price
            ])->values()->all() : [],
            'allow_observations'  => (bool) $dish->allow_observations,
            'allow_spice_level'   => (bool) $dish->allow_spice_level,
            'is_available'        => (bool) $dish->is_available,
            'is_featured'         => (bool) $dish->is_featured,
            'reviews_count'       => $reviewsCount,
            'reviews_avg_rating'  => $reviewsAvgRating,
            'limitar_dias'        => (bool) $dish->limitar_dias,
            'dias_disponibilidad' => $dish->dias_disponibilidad,
            'total_pedidos'       => (int) ($dish->order_items_sum_quantity ?? 0),
        ];
    }

    public function index(Request $request)
    {
        $query = Dish::with('category', 'extras')
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->withSum('orderItems', 'quantity');

        if ($request->category_id && $request->category_id !== 'all') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->search) {
            $query->where('name', 'like', "%{$request->search}%");
        }

        $dishes = $query->orderBy('name', 'asc')->get();

        $mapped = $dishes->map(fn($dish) => $this->formatDish($dish));

        return response()->json(['dishes' => $mapped]);
    }

    private function resolveCategoryId(Request $request): void
    {
        $catInput = $request->input('category_id') ?? $request->input('category') ?? $request->input('category_name');

        if (!empty($catInput)) {
            if (is_numeric($catInput)) {
                $request->merge(['category_id' => (int) $catInput]);
            } else {
                $cat = \App\Models\Category::where('name', 'like', $catInput)
                    ->orWhere('slug', Str::slug($catInput))
                    ->first();

                if (!$cat) {
                    $cat = \App\Models\Category::create([
                        'name'   => trim($catInput),
                        'slug'   => Str::slug($catInput),
                        'active' => true,
                    ]);
                }

                $request->merge(['category_id' => $cat->id]);
            }
        }
    }

    private function sanitizeExtras(Request $request): void
    {
        if ($request->has('extras') && is_array($request->input('extras'))) {
            $rawExtras = $request->input('extras');
            $cleanExtras = [];

            foreach ($rawExtras as $item) {
                if (is_numeric($item)) {
                    $cleanExtras[] = (int) $item;
                } elseif (is_string($item)) {
                    $ex = \App\Models\Extra::where('name', 'like', trim($item))->first();
                    if ($ex) {
                        $cleanExtras[] = $ex->id;
                    }
                } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    $cleanExtras[] = (int) $item['id'];
                }
            }

            $request->merge(['extras' => array_values(array_unique($cleanExtras))]);
        }
    }

    public function store(StoreDishRequest $request)
    {
        $this->resolveCategoryId($request);
        $this->sanitizeExtras($request);

        $data = $request->validated();

        if (isset($data['description'])) {
            $data['description'] = trim(strip_tags($data['description']));
        }

        $data['slug'] = Str::slug($data['name']);

        $imageFile = $request->file('imagen') ?? $request->file('image') ?? $request->file('foto');
        if ($imageFile) {
            $compressed = ImageCompressionService::compressAndStore($imageFile, 'dishes', 800, 75);
            $data['image_url'] = $compressed['url'];
        }

        if (!isset($data['allow_observations'])) {
            $data['allow_observations'] = true;
        }

        if (!isset($data['allow_spice_level'])) {
            $data['allow_spice_level'] = false;
        }

        if (!isset($data['is_available'])) {
            $data['is_available'] = true;
        }

        $extras = $data['extras'] ?? [];
        unset($data['extras'], $data['image'], $data['imagen'], $data['foto']);
        $dish = new Dish();
        $dish->fill($data);

        // 1. Leer el estado exacto del interruptor (convierte 'true'/'false' o 1/0 a booleano real)
        if ($request->has('limitar_dias') || $request->has('dias_disponibilidad')) {
            $limitarDias = $request->boolean('limitar_dias');

            // 2. Asignar el booleano a la base de datos
            $dish->limitar_dias = $limitarDias;

            // 3. LA REGLA DE ORO DEL INTERRUPTOR:
            // Si el interruptor está APAGADO (!), forzamos los días a NULL en la base de datos.
            // Si está ENCENDIDO, guardamos el arreglo de días que mandó React.
            if (!$limitarDias) {
                $dish->dias_disponibilidad = null;
            } else {
                $dish->dias_disponibilidad = $request->input('dias_disponibilidad', []);
            }
        }

        $dish->save();

        if (!empty($extras)) {
            $dish->extras()->sync($extras);
        }

        NotificationService::create(
            'dish_created',
            'Platillo Creado',
            "Se creó el platillo '{$dish->name}'",
            ['dish_id' => $dish->id]
        );

        AuditLogger::log('DISH_CREATED', 'Platillos', "Platillo '{$dish->name}' creado", auth()->user(), 'info');

        return response()->json($this->formatDish($dish), 201);
    }

    public function show(Dish $dish)
    {
        $dish->loadCount('reviews');
        $dish->loadAvg('reviews', 'rating');
        AuditLogger::log('DISH_VIEWED', 'Platillos', "Platillo '{$dish->name}' consultado", auth()->user(), 'info');

        return response()->json($this->formatDish($dish));
    }

    public function update(UpdateDishRequest $request, $id)
    {
        $this->resolveCategoryId($request);
        $this->sanitizeExtras($request);

        $dish = Dish::findOrFail($id);

        $data = $request->validated();

        if (isset($data['description'])) {
            $data['description'] = trim(strip_tags($data['description']));
        }

        if (isset($data['name'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        $imageFile = $request->file('imagen') ?? $request->file('image') ?? $request->file('foto');
        if ($imageFile) {
            ImageCompressionService::deleteOldImage($dish->image_url, 'dishes');
            $compressed = ImageCompressionService::compressAndStore($imageFile, 'dishes', 800, 75);
            $data['image_url'] = $compressed['url'];
        }

        $extras = $data['extras'] ?? null;
        unset($data['extras'], $data['image'], $data['imagen'], $data['foto']);

        $dish->fill($data);

        // 1. Leer el estado exacto del interruptor (convierte 'true'/'false' o 1/0 a booleano real)
        if ($request->has('limitar_dias') || $request->has('dias_disponibilidad')) {
            $limitarDias = $request->boolean('limitar_dias');

            // 2. Asignar el booleano a la base de datos
            $dish->limitar_dias = $limitarDias;

            // 3. LA REGLA DE ORO DEL INTERRUPTOR:
            // Si el interruptor está APAGADO (!), forzamos los días a NULL en la base de datos.
            // Si está ENCENDIDO, guardamos el arreglo de días que mandó React.
            if (!$limitarDias) {
                $dish->dias_disponibilidad = null;
            } else {
                $dish->dias_disponibilidad = $request->input('dias_disponibilidad', []);
            }
        }

        $dish->save();

        if ($extras !== null) {
            $dish->extras()->sync($extras);
        }

        NotificationService::create(
            'dish_updated',
            'Platillo Editado',
            "Se actualizó el platillo '{$dish->name}'",
            ['dish_id' => $dish->id]
        );

        AuditLogger::log('DISH_UPDATED', 'Platillos', "Platillo '{$dish->name}' actualizado", auth()->user(), 'info');

        return response()->json($this->formatDish($dish));
    }

    public function destroy($id)
    {
        $dish = Dish::findOrFail($id);

        ImageCompressionService::deleteOldImage($dish->image_url, 'dishes');

        $name = $dish->name;
        $dish->delete();

        NotificationService::create(
            'dish_deleted',
            'Platillo Eliminado',
            "Se eliminó el platillo '{$name}'",
            ['dish_id' => $id]
        );

        AuditLogger::log('DISH_DELETED', 'Platillos', "Platillo '{$name}' eliminado", auth()->user(), 'warning');

        return response()->json(['message' => 'Platillo eliminado correctamente']);
    }

    public function toggle($id)
    {
        $dish = Dish::findOrFail($id);
        $dish->is_available = !$dish->is_available;
        $dish->save();

        $statusStr = $dish->is_available ? 'activado' : 'desactivado';

        NotificationService::create(
            'dish_toggled',
            'Platillo Actualizado',
            "El platillo '{$dish->name}' fue {$statusStr}",
            ['dish_id' => $dish->id, 'is_available' => $dish->is_available]
        );

        AuditLogger::log('DISH_TOGGLED', 'Platillos', "Platillo '{$dish->name}' fue {$statusStr}", auth()->user(), 'info');

        return response()->json($this->formatDish($dish));
    }

    public function getRecipe(Request $request, $id = null)
    {
        return app(ProductController::class)->getRecipe($request, $id);
    }

    public function asignarReceta(Request $request, $id = null)
    {
        return app(ProductController::class)->asignarReceta($request, $id);
    }
}
