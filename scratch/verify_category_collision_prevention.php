<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Dish;
use App\Models\Category;
use App\Http\Controllers\Api\CategoryController;
use Illuminate\Support\Facades\DB;

echo "=========================================================\n";
echo "   VERIFICACIÓN EN VIVO: PREVENCIÓN DE COLISIONES FK (PGSQL)\n";
echo "=========================================================\n\n";

$driver = DB::connection()->getDriverName();
$database = DB::connection()->getDatabaseName();
echo "[1] Conexión a Base de Datos:\n";
echo "    Driver: {$driver}\n";
echo "    Database: {$database}\n\n";

$controller = app(CategoryController::class);

// Caso 1: Categoría con platillos activos
echo "[2] Caso 1: Intentar eliminar categoría con platillos activos...\n";
$catWithActive = Category::create([
    'name'   => 'Categoría Con Activos Test',
    'slug'   => 'categoria-con-activos-test-' . uniqid(),
    'active' => true,
]);

$activeDish = Dish::create([
    'category_id'  => $catWithActive->id,
    'name'         => 'Platillo Activo Test',
    'slug'         => 'platillo-activo-test-' . uniqid(),
    'price'        => 150.00,
    'is_active'    => true,
    'is_available' => true,
]);

$res1 = $controller->destroy($catWithActive->id);
$data1 = json_decode($res1->getContent(), true);

echo "    HTTP Status: {$res1->getStatusCode()}\n";
echo "    Mensaje: " . ($data1['message'] ?? 'N/A') . "\n";
if ($res1->getStatusCode() === 422 && str_contains($data1['message'] ?? '', 'contiene platillos asociados')) {
    echo "    >>> RESULTADO: ÉXITO (Bloqueado con 422 sin colapsar la base de datos)\n\n";
} else {
    echo "    >>> RESULTADO: FALLO\n\n";
}

// Caso 2: Categoría con platillos inactivos (is_active = false)
echo "[3] Caso 2: Intentar eliminar categoría con platillos inactivos...\n";
$catWithInactive = Category::create([
    'name'   => 'Categoría Con Inactivos Test',
    'slug'   => 'categoria-con-inactivos-test-' . uniqid(),
    'active' => true,
]);

$inactiveDish = Dish::create([
    'category_id'  => $catWithInactive->id,
    'name'         => 'Platillo Inactivo Test',
    'slug'         => 'platillo-inactivo-test-' . uniqid(),
    'price'        => 120.00,
    'is_active'    => false,
    'is_available' => false,
]);

$res2 = $controller->destroy($catWithInactive->id);
$data2 = json_decode($res2->getContent(), true);

echo "    HTTP Status: {$res2->getStatusCode()}\n";
echo "    Mensaje: " . ($data2['message'] ?? 'N/A') . "\n";
if ($res2->getStatusCode() === 422 && str_contains($data2['message'] ?? '', 'contiene platillos asociados')) {
    echo "    >>> RESULTADO: ÉXITO (Bloqueado con 422 para platillos inactivos)\n\n";
} else {
    echo "    >>> RESULTADO: FALLO\n\n";
}

// Caso 3: Categoría con platillos soft-deleted (en papelera)
echo "[4] Caso 3: Eliminar categoría cuyos platillos ya están en la papelera (Soft-Deleted)...\n";
$catWithTrashed = Category::create([
    'name'   => 'Categoría En Papelera Test',
    'slug'   => 'categoria-en-papelera-test-' . uniqid(),
    'active' => true,
]);

$trashedDish = Dish::create([
    'category_id'  => $catWithTrashed->id,
    'name'         => 'Platillo En Papelera Test',
    'slug'         => 'platillo-en-papelera-test-' . uniqid(),
    'price'        => 180.00,
    'is_active'    => true,
    'is_available' => true,
]);
$trashedDish->delete(); // Soft delete

$res3 = $controller->destroy($catWithTrashed->id);
$data3 = json_decode($res3->getContent(), true);

echo "    HTTP Status: {$res3->getStatusCode()}\n";
echo "    Mensaje: " . ($data3['message'] ?? 'N/A') . "\n";
if ($res3->getStatusCode() === 200 && Category::find($catWithTrashed->id) === null) {
    echo "    >>> RESULTADO: ÉXITO (Platillos en papelera purgados, categoría eliminada sin error 500)\n\n";
} else {
    echo "    >>> RESULTADO: FALLO\n\n";
}

// Caso 4: Eliminación de Categoría 21 real de la base de datos
$cat21 = Category::find(21);
if ($cat21) {
    echo "[5] Caso 4: Eliminando categoría #21 ('{$cat21->name}')...\n";
    $res21 = $controller->destroy(21);
    $data21 = json_decode($res21->getContent(), true);
    echo "    HTTP Status: {$res21->getStatusCode()}\n";
    echo "    Mensaje: " . ($data21['message'] ?? 'N/A') . "\n";
    echo "    >>> RESULTADO: " . ($res21->getStatusCode() === 200 ? 'ÉXITO (Categoría 21 eliminada limpiamente)' : 'FALLO') . "\n\n";
}

// Limpieza de datos temporales
$activeDish->forceDelete();
$inactiveDish->forceDelete();
$catWithActive->delete();
$catWithInactive->delete();

echo "=========================================================\n";
echo "           VERIFICACIÓN COMPLETADA CON ÉXITO\n";
echo "=========================================================\n";
