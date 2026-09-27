<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Dish;
use App\Models\Category;
use App\Models\RestaurantSetting;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

echo "=========================================================\n";
echo "   VERIFICACIÓN EN VIVO: ARREGLO VACÍO Y CACHÉ (POSTGRESQL)\n";
echo "=========================================================\n\n";

// 1. Estado de la conexión
echo "[1] Conexión a Base de Datos:\n";
$driver = DB::connection()->getDriverName();
$database = DB::connection()->getDatabaseName();
echo "    Driver: {$driver}\n";
echo "    Database: {$database}\n\n";

// 2. Crear categoría y platillo de prueba si no existen
$category = Category::firstOrCreate(
    ['slug' => 'entradas-test-cache'],
    ['name' => 'Entradas Test Cache', 'active' => true, 'is_active' => true]
);

$dish = Dish::firstOrCreate(
    ['slug' => 'platillo-test-cache'],
    [
        'category_id'  => $category->id,
        'name'         => 'Platillo Test Cache',
        'price'        => 199.00,
        'is_active'    => true,
        'is_available' => true,
        'is_featured'  => true,
    ]
);

echo "[2] Platillo de prueba:\n";
echo "    ID: {$dish->id}, Nombre: {$dish->name}, is_featured: " . ($dish->is_featured ? 'true' : 'false') . "\n\n";

// 3. Simular caché pre-existente
Cache::put('landing_featured_dishes', ['dish_anterior' => $dish->id], 3600);
Cache::put('landing_settings', ['data' => 'antigua'], 3600);
Cache::put('restaurant_settings', ['data' => 'antigua'], 3600);
echo "[3] Estado de Caché Previo:\n";
echo "    Cache 'landing_featured_dishes': " . (Cache::has('landing_featured_dishes') ? 'PRESENTE (OK)' : 'AUSENTE') . "\n";
echo "    Cache 'landing_settings': " . (Cache::has('landing_settings') ? 'PRESENTE (OK)' : 'AUSENTE') . "\n\n";

// 4. Ejecutar actualización con featured_dishes: []
echo "[4] Ejecutando updateFeaturedDishes con featured_dishes: [] ...\n";
$controller = app(SettingsController::class);

$request = Request::create('/api/admin/settings/featured-dishes', 'PUT', [
    'title'               => 'Carta Gourmet',
    'subtitle'            => 'Especialidades',
    'button_text'         => 'Explorar',
    'featured_categories' => [$category->id],
    'featured_dishes'     => [], // ARREGLO VACÍO EXPLÍCITO
]);
$request->headers->set('Accept', 'application/json');

// Crear FormRequest validado
$formRequest = \App\Http\Requests\UpdateFeaturedDishesRequest::createFrom($request);
$formRequest->setContainer($app);
$formRequest->validateResolved();

$response = $controller->updateFeaturedDishes($formRequest);
$responseData = json_decode($response->getContent(), true);

echo "    HTTP Status: {$response->getStatusCode()}\n\n";

// 5. Verificar persistencia en base de datos PostgreSQL
$settings = RestaurantSetting::first();
echo "[5] Verificación en Base de Datos PostgreSQL (restaurant_settings):\n";
echo "    featured_dishes en BD: " . json_encode($settings->featured_dishes) . "\n";
echo "    platillos_seccion.selected_dishes en BD: " . json_encode($settings->platillos_seccion['selected_dishes'] ?? null) . "\n";
echo "    platillos_seccion.featured_dishes en BD: " . json_encode($settings->platillos_seccion['featured_dishes'] ?? null) . "\n";

if ($settings->featured_dishes === [] && ($settings->platillos_seccion['selected_dishes'] ?? null) === []) {
    echo "    >>> RESULTADO BD: ÉXITO (Arreglo vacío persistido como [] en JSON)\n\n";
} else {
    echo "    >>> RESULTADO BD: FALLO (No se guardó arreglo vacío)\n\n";
}

// 6. Verificar sincronización de la columna is_featured en la tabla dishes
$dish->refresh();
echo "[6] Verificación de sincronización de Dish.is_featured:\n";
echo "    is_featured para dish #{$dish->id}: " . ($dish->is_featured ? 'true' : 'false') . "\n";
if ($dish->is_featured === false) {
    echo "    >>> RESULTADO SINCRONIZACIÓN: ÉXITO (Platillo desmarcado como destacado)\n\n";
} else {
    echo "    >>> RESULTADO SINCRONIZACIÓN: FALLO (Platillo sigue marcado como destacado)\n\n";
}

// 7. Verificar destrucción de llaves de caché
echo "[7] Verificación de Invalidación de Caché:\n";
$c1 = Cache::has('landing_featured_dishes');
$c2 = Cache::has('landing_settings');
$c3 = Cache::has('restaurant_settings');
echo "    landing_featured_dishes borrado: " . (!$c1 ? 'SÍ (Destruido)' : 'NO') . "\n";
echo "    landing_settings borrado: " . (!$c2 ? 'SÍ (Destruido)' : 'NO') . "\n";
echo "    restaurant_settings borrado: " . (!$c3 ? 'SÍ (Destruido)' : 'NO') . "\n";

if (!$c1 && !$c2 && !$c3) {
    echo "    >>> RESULTADO CACHÉ: ÉXITO (Todas las llaves fueron invalidadas)\n\n";
} else {
    echo "    >>> RESULTADO CACHÉ: FALLO (Quedaron llaves en caché)\n\n";
}

// 8. Verificar show() y eliminación de fallback
echo "[8] Verificación de SettingsController::show() (Prevención de Fallback):\n";
$showResponse = $controller->show();
$showData = $showResponse->getData(true);
$returnedFeaturedDishes = $showData['featured_dishes'] ?? null;
$returnedPlatillosDishes = $showData['platillos_seccion']['dishes'] ?? null;

echo "    featured_dishes retornado: " . json_encode($returnedFeaturedDishes) . " (Total: " . count($returnedFeaturedDishes ?? []) . ")\n";
echo "    platillos_seccion.dishes retornado: " . json_encode($returnedPlatillosDishes) . " (Total: " . count($returnedPlatillosDishes ?? []) . ")\n";

if ($returnedFeaturedDishes === [] && $returnedPlatillosDishes === []) {
    echo "    >>> RESULTADO SHOW: ÉXITO (Retorna estrictamente [] sin inventar platillos ni recurrir a fallback)\n\n";
} else {
    echo "    >>> RESULTADO SHOW: FALLO (Retornó platillos por fallback)\n\n";
}

echo "=========================================================\n";
echo "           VERIFICACIÓN COMPLETADA CON ÉXITO\n";
echo "=========================================================\n";
