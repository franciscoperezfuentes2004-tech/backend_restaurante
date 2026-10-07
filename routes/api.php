<?php
use App\Http\Middleware\EnsurePasswordIsChanged;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\ExtraController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\UpdateOrderStatusController;
use App\Http\Controllers\Api\AssignDeliveryOrderController;
use App\Http\Controllers\Api\CloseDriverShiftController;
use App\Http\Controllers\Api\OpenTableOrderController;
use App\Http\Controllers\Api\GetPosMenuController;
use App\Http\Controllers\Api\AddDishToOrderController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\MesaController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\ConfiguracionGeneralController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ImageController;
use App\Http\Controllers\Api\DeliveryController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\ZoneController;
use App\Http\Controllers\Api\TestimonialController;
use App\Http\Controllers\Api\PromotionController;
use App\Http\Controllers\Api\IngredientController;
use App\Http\Controllers\Api\SupplierController;
use App\Http\Controllers\Api\StockController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\SalesAnalysisController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\CostAnalysisController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\PermissionController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\CashCutController;
use App\Http\Controllers\Api\KpiController;
use App\Http\Controllers\Api\ContactoController;
use App\Http\Controllers\Api\RepartidorController;
use App\Http\Controllers\Api\KitchenController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\PasswordResetController;

// Rutas públicas (sin autenticación)
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
Route::get('/statistics/experiences', [LandingController::class, 'getStatistics']);
Route::get('/reviews/landing',        [LandingController::class, 'getReviews']);
Route::get('/landing/reviews',        [LandingController::class, 'getLandingReviews']);
Route::get('/reviews/landing-reviews', [LandingController::class, 'getLandingReviews']);
Route::post('/password/forgot',       [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:5,1');
Route::post('/password/reset',        [PasswordResetController::class, 'resetPassword'])->middleware('throttle:5,1');
Route::post('/password/reset-temp',   [PasswordResetController::class, 'generateTempPassword']);
Route::post('/password/recuperar',    [PasswordResetController::class, 'enviarRecuperacion']);
Route::post('/recuperar-password',    [PasswordResetController::class, 'enviarRecuperacion']);

// Ruta temporal de prueba para mapear variables en n8n
Route::get('/test-n8n', function () {
    $url = env('N8N_WEBHOOK_URL') ?: 'http://localhost:5678/webhook-test/e42d074b-d07e-4ea9-aab8-b6b695c960f1';

    try {
        $response = Http::timeout(5)->post($url, [
            'email' => 'cajero@restaurante.com',
            'name'  => 'Juan Empleado',
            'code'  => '123456',
        ]);

        if ($response->status() === 404 && str_contains($url, '/webhook/')) {
            $testUrl = str_replace('/webhook/', '/webhook-test/', $url);
            $response = Http::timeout(5)->post($testUrl, [
                'email' => 'cajero@restaurante.com',
                'name'  => 'Juan Empleado',
                'code'  => '123456',
            ]);
        }
    } catch (\Throwable $e) {
        return response()->json([
            'status'  => 'error',
            'message' => 'Error al conectar con n8n: ' . $e->getMessage(),
        ], 500);
    }

    return response()->json([
        'message'      => 'Webhook enviado a n8n',
        'n8n_status'   => $response->status(),
        'n8n_response' => $response->json() ?? $response->body(),
    ]);
});

// Rutas públicas del cliente (web pública)
Route::prefix('public')->group(function () {
    Route::get('/categories',            [CategoryController::class, 'index']);
    Route::get('/categories/{category}', [CategoryController::class, 'show']);
    Route::get('/dishes',                [DishController::class, 'index']);
    Route::get('/dishes/{dish}',         [DishController::class, 'show']);
    Route::get('/menu',                  [CategoryController::class, 'publicMenu']);
    Route::get('/settings',              [SettingsController::class, 'show']);
    Route::get('/landing-config',        [SettingsController::class, 'show']);
    Route::get('/landing/config',        [SettingsController::class, 'show']);
    Route::get('/promotions',            [PromotionController::class, 'publicIndex']);
});

Route::get('/menu',                    [CategoryController::class, 'publicMenu']);
Route::get('/menu/public',             [CategoryController::class, 'publicMenu']);
Route::get('/menu/{category}',         [CategoryController::class, 'show']);
Route::get('/categories',              [CategoryController::class, 'index']);
Route::get('/categories/{category}',   [CategoryController::class, 'show']);
Route::get('/dishes',                  [DishController::class, 'index']);
Route::get('/dishes/{dish}',           [DishController::class, 'show']);
Route::get('/admin/categories',        [CategoryController::class, 'index']);
Route::get('/admin/dishes',            [DishController::class, 'index']);
Route::get('/areas',                   [AreaController::class, 'index']);
Route::get('/settings',                [SettingsController::class, 'show']);
Route::get('/settings/favicon',        [SettingsController::class, 'getFavicon']);
Route::get('/promotions',              [PromotionController::class, 'publicIndex']);
Route::get('/settings/landing',        [SettingsController::class, 'show']);
Route::get('/landing/settings',        [SettingsController::class, 'show']);
Route::get('/landing-config',          [SettingsController::class, 'show']);
Route::get('/landing/config',          [SettingsController::class, 'show']);
Route::get('/configuracion',           [ConfiguracionGeneralController::class, 'show']);
Route::get('/admin/configuracion',     [ConfiguracionGeneralController::class, 'show']);
Route::get('/admin/settings',          [SettingsController::class, 'index']);
Route::post('/orders',                 [OrderController::class, 'store']);
Route::post('/orders/online',          [OrderController::class, 'storeOnline']);
Route::get('/orders/dispatch/{token}', [OrderController::class, 'getByDispatchToken']);
Route::get('/reservations/schedule',   [SettingsController::class, 'getSchedule']);
Route::get('/settings/schedule',       [SettingsController::class, 'getSchedule']);
Route::post('/reservaciones',          [ReservationController::class, 'store'])->middleware('throttle:reservaciones');
Route::post('/reservations',           [ReservationController::class, 'store'])->middleware('throttle:reservaciones');
Route::get('/testimonials',            [TestimonialController::class, 'index']);
Route::get('/listado-experiencias',     [TestimonialController::class, 'index']);
Route::get('/testimonials/stats',      [TestimonialController::class, 'stats']);
Route::get('/testimonials/gallery',    [TestimonialController::class, 'gallery']);
Route::post('/testimonials',           [TestimonialController::class, 'store']);
Route::post('/testimonials/{id}/useful', [TestimonialController::class, 'useful']);
Route::get('/reviews/stats',           [ReviewController::class, 'stats']);
Route::get('/reviews',                 [ReviewController::class, 'publicIndex']);
Route::get('/galeria-destacada',        [ReviewController::class, 'galeriaDiaria']);
Route::get('/reviews/galeria-destacada',[ReviewController::class, 'galeriaDiaria']);
Route::get('/resenas-destacadas',        [ReviewController::class, 'tarjetasDiarias']);
Route::get('/reviews/resenas-destacadas',[ReviewController::class, 'tarjetasDiarias']);
Route::get('/estadisticas-resenas',       [ReviewController::class, 'estadisticas']);
Route::get('/reviews/estadisticas',      [ReviewController::class, 'estadisticas']);
Route::get('/metricas-resenas',           [ReviewController::class, 'metricasResenas']);
Route::get('/reviews/metricas-resenas',   [ReviewController::class, 'metricasResenas']);
Route::get('/reviews/metricas',           [ReviewController::class, 'metricasResenas']);
Route::get('/reviews/distribucion',       [ReviewController::class, 'metricasResenas']);
Route::post('/resenas',                [ReviewController::class, 'store'])->middleware('throttle:resenas');
Route::post('/reviews',                [ReviewController::class, 'store'])->middleware('throttle:resenas');
Route::post('/contacto',               [ContactoController::class, 'enviar'])->middleware('throttle:3,1');
Route::post('/contact-messages',       [ContactoController::class, 'enviar'])->middleware('throttle:3,1');
Route::post('/contact',                [ContactoController::class, 'enviar'])->middleware('throttle:3,1');
Route::get('/productos/{producto}/receta', [DishController::class, 'getRecipe']);
Route::get('/productos/{id}/receta',       [DishController::class, 'getRecipe']);
Route::post('/productos/{id}/receta',      [ProductController::class, 'asignarReceta']);
Route::get('/products/{product}/receta',   [DishController::class, 'getRecipe']);
Route::get('/products/{id}/receta',        [ProductController::class, 'getRecipe']);
Route::post('/products/{id}/receta',       [ProductController::class, 'asignarReceta']);
Route::get('/dishes/{dish}/receta',        [DishController::class, 'getRecipe']);
Route::get('/dishes/{id}/receta',          [DishController::class, 'getRecipe']);
Route::post('/dishes/{id}/receta',         [DishController::class, 'asignarReceta']);
Route::get('/reportes/ingresos-delivery',       [ReportController::class, 'ingresosDelivery']);
Route::get('/reports/ingresos-delivery',        [ReportController::class, 'ingresosDelivery']);
Route::get('/delivery/ingresos',                [ReportController::class, 'ingresosDelivery']);
Route::get('/reportes/ingresos-por-repartidor', [ReportController::class, 'ingresosPorRepartidor']);
Route::get('/reportes/ingresos-repartidor',     [ReportController::class, 'ingresosPorRepartidor']);
Route::get('/reports/ingresos-por-repartidor',  [ReportController::class, 'ingresosPorRepartidor']);
Route::get('/reports/income-by-driver',         [ReportController::class, 'ingresosPorRepartidor']);
Route::get('/reportes/metricas-rendimiento',       [ReportController::class, 'metricasRendimiento']);
Route::get('/reports/metricas-rendimiento',        [ReportController::class, 'metricasRendimiento']);
Route::get('/reports/performance-metrics',         [ReportController::class, 'metricasRendimiento']);
Route::get('/delivery/metricas-rendimiento',       [ReportController::class, 'metricasRendimiento']);
Route::get('/kpis/metricas-rendimiento',           [KpiController::class, 'metricasRendimiento']);
Route::get('/reportes/kpis-rendimiento',           [ReportController::class, 'kpisRendimiento']);
Route::get('/reports/kpis-rendimiento',            [ReportController::class, 'kpisRendimiento']);
Route::get('/delivery/kpis-rendimiento',           [ReportController::class, 'kpisRendimiento']);
Route::get('/kpis/kpis-rendimiento',               [KpiController::class, 'kpisRendimiento']);
Route::get('/kpis-rendimiento',                    [ReportController::class, 'kpisRendimiento']);

// Ruta EXCLUSIVA para cambiar la contraseña temporal (Requiere login, pero NO el guardia bloqueador)
Route::middleware('auth:sanctum')->post('/password/force-change', [PasswordResetController::class, 'forceChange']);

// Rutas protegidas (solo admin autenticado)
Route::middleware(['auth:sanctum', EnsurePasswordIsChanged::class])->group(function () {

    Route::post('/logout',               [AuthController::class, 'logout']);
    Route::get('/me',                    [AuthController::class, 'me']);
    Route::get('/me/using-default-credentials', function (Request $request) {
        return response()->json([
            'using_default_credentials' => (bool) $request->user()?->using_default_credentials
        ]);
    });
    Route::get('/me/sessions',           [AuthController::class, 'activeSessions']);
    Route::post('/me/sessions/revoke',   [AuthController::class, 'revokeSession']);
    Route::post('/me/confirm-password',  [AuthController::class, 'confirmPassword']);
    Route::get('/orders/{id}',                                          [OrderController::class, 'show']);
    Route::match(['patch', 'post', 'put'], '/orders/{order}/status',   [UpdateOrderStatusController::class, 'update']);
    Route::match(['patch', 'post', 'put'], '/orders/status',           [UpdateOrderStatusController::class, 'update']);
    Route::match(['patch', 'post', 'put'], '/orders/update-status',    [UpdateOrderStatusController::class, 'update']);
    Route::get('/admin/areas',           [AreaController::class, 'index']);
    Route::get('/admin/areas/{id}/mesas',[AreaController::class, 'getMesas']);

    // Notificaciones (Aislamiento por usuario autenticado)
    Route::get('/notifications',                  [NotificationController::class, 'index']);
    Route::get('/admin/notifications',            [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read',       [NotificationController::class, 'markAsRead']);
    Route::post('/admin/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all',        [NotificationController::class, 'markAllAsRead']);
    Route::post('/admin/notifications/read-all',  [NotificationController::class, 'markAllAsRead']);
    Route::delete('/notifications',               [NotificationController::class, 'destroyAll']);
    Route::delete('/admin/notifications',         [NotificationController::class, 'destroyAll']);

    // Admin, Super Admin y Gerente – acceso completo (con restricciones en frontend)
    Route::middleware('role:admin,super_admin,gerente')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/admin/dashboard', [DashboardController::class, 'index']);

        // Menú – admin
        Route::get('/admin/categories/indicators',   [CategoryController::class, 'indicators']);
        Route::post('/admin/categories',             [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], '/admin/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/admin/categories/{id}',      [CategoryController::class, 'destroy']);
        Route::patch('/admin/categories/{id}/toggle', [CategoryController::class, 'toggle']);

        Route::post('/categories',             [CategoryController::class, 'store']);
        Route::match(['put', 'patch'], '/categories/{id}', [CategoryController::class, 'update']);
        Route::delete('/categories/{id}',      [CategoryController::class, 'destroy']);

        Route::patch('/admin/dishes/{id}/toggle', [DishController::class, 'toggle']);
        Route::get('/admin/dishes/{id}/receta',    [DishController::class, 'getRecipe']);
        Route::post('/admin/dishes/{id}/receta',   [DishController::class, 'asignarReceta']);
        Route::get('/admin/dishes/{id}/recipe',    [DishController::class, 'getRecipe']);
        Route::post('/admin/dishes/{id}/recipe',   [DishController::class, 'asignarReceta']);
        Route::get('/admin/products/{id}/receta',  [ProductController::class, 'getRecipe']);
        Route::post('/admin/products/{id}/receta', [ProductController::class, 'asignarReceta']);
        Route::get('/admin/productos/{id}/receta', [ProductController::class, 'getRecipe']);
        Route::post('/admin/productos/{id}/receta',[ProductController::class, 'asignarReceta']);
        Route::get('/admin/extras',     [ExtraController::class, 'index']);
        Route::apiResource('/admin/dishes',     DishController::class)->except(['index', 'show']);
        Route::apiResource('/admin/extras',     ExtraController::class)->except(['index']);

        // Pedidos – admin
        Route::get('/admin/orders',                                            [OrderController::class, 'index']);
        Route::get('/admin/orders/dispatch/{token}',                           [OrderController::class, 'getByDispatchToken']);
        Route::get('/admin/orders/{order}',                                    [OrderController::class, 'show']);
        Route::match(['patch', 'post', 'put'], '/admin/orders/{order}/status', [UpdateOrderStatusController::class, 'update']);
        Route::match(['patch', 'post', 'put'], '/admin/orders/status',         [UpdateOrderStatusController::class, 'update']);
        Route::match(['patch', 'post', 'put'], '/admin/orders/update-status',  [UpdateOrderStatusController::class, 'update']);

        // Reservaciones – admin
        Route::get('/admin/reservations',                        [ReservationController::class, 'index']);
        Route::post('/admin/reservations',                       [ReservationController::class, 'store']);
        Route::put('/admin/reservations/{reservation}',          [ReservationController::class, 'update']);
        Route::patch('/admin/reservations/{reservation}/status', [ReservationController::class, 'updateStatus']);
        Route::match(['post', 'put', 'patch'], '/admin/reservations/{reservation}/asignar-mesa', [ReservationController::class, 'asignarMesa']);
        Route::match(['post', 'put', 'patch'], '/admin/reservations/{reservation}/mesa',         [ReservationController::class, 'asignarMesa']);
        Route::patch('/admin/reservations/{reservation}',        [ReservationController::class, 'update']);
        Route::delete('/admin/reservations/{reservation}',       [ReservationController::class, 'destroy']);

        // Áreas y Mesas
        Route::post('/admin/areas',              [AreaController::class, 'store']);
        Route::put('/admin/areas/{id}',          [AreaController::class, 'update']);
        Route::patch('/admin/areas/{id}/toggle',  [AreaController::class, 'toggle']);
        Route::delete('/admin/areas/{id}',       [AreaController::class, 'destroy']);
        Route::post('/admin/areas/{id}/mesas',   [AreaController::class, 'storeMesa']);

        Route::put('/admin/mesas/{id}',          [MesaController::class, 'update']);
        Route::patch('/admin/mesas/{id}/toggle',  [MesaController::class, 'toggle']);
        Route::delete('/admin/mesas/{id}',       [MesaController::class, 'destroy']);

        // Delivery module
        Route::get('/admin/delivery/orders',                 [DeliveryController::class, 'getOrders']);
        Route::get('/admin/delivery/orders/{id}',            [DeliveryController::class, 'getOrderDetails']);
        Route::get('/admin/delivery/qr/{orderId}',           [DeliveryController::class, 'generateQr']);
        Route::patch('/admin/delivery/orders/{id}/status',   [DeliveryController::class, 'updateOrderStatus']);
        Route::get('/admin/delivery/performance',          [DeliveryController::class, 'getPerformance']);

        Route::get('/admin/deliveries', [DeliveryController::class, 'index']);
        Route::patch('/admin/deliveries/{delivery}', [DeliveryController::class, 'update']);
        Route::get('/admin/drivers', [DriverController::class, 'index']);
        Route::post('/admin/drivers', [DriverController::class, 'store']);
        Route::get('/admin/drivers/{id}', [DriverController::class, 'show']);
        Route::put('/admin/drivers/{id}', [DriverController::class, 'update']);
        Route::patch('/admin/drivers/{id}', [DriverController::class, 'update']);
        Route::put('/admin/repartidores/{id}', [RepartidorController::class, 'update']);
        Route::patch('/admin/repartidores/{id}', [RepartidorController::class, 'update']);
        Route::put('/repartidores/{id}', [RepartidorController::class, 'update']);
        Route::patch('/repartidores/{id}', [RepartidorController::class, 'update']);
        Route::delete('/admin/drivers/{id}', [DriverController::class, 'destroy']);
        Route::patch('/admin/drivers/{id}/toggle', [DriverController::class, 'toggle']);
        Route::apiResource('/admin/zones', ZoneController::class);

        // Cortes de caja de repartidores (Cash Cuts)
        Route::get('/admin/cash-cuts',                 [CashCutController::class, 'index']);
        Route::get('/admin/cash-cuts/{id}',            [CashCutController::class, 'show']);
        Route::post('/admin/cash-cuts/{id}/confirmar', [CashCutController::class, 'confirmar']);

        // Configuración
        Route::put('/admin/configuracion',           [ConfiguracionGeneralController::class, 'update']);
        Route::post('/admin/configuracion/logotipo', [ConfiguracionGeneralController::class, 'uploadLogo']);
        Route::put('/admin/settings',                [SettingsController::class, 'update']);
        Route::match(['put', 'post'], '/admin/settings/landing', [SettingsController::class, 'updateLandingPage']);
        Route::match(['put', 'post'], '/settings/landing',        [SettingsController::class, 'updateLandingPage']);
        Route::match(['put', 'post'], '/landing/settings',        [SettingsController::class, 'updateLandingPage']);
        Route::match(['put', 'post'], '/admin/settings/credentials',     [SettingsController::class, 'updateCredentials']);
        Route::match(['put', 'post'], '/admin/settings/payment-methods',  [SettingsController::class, 'updatePaymentMethods']);
        Route::match(['put', 'post'], '/admin/settings/delivery-zone',    [SettingsController::class, 'updateDeliveryZone']);
        Route::match(['put', 'post'], '/admin/settings/featured-dishes',  [SettingsController::class, 'updateFeaturedDishes']);
        Route::match(['put', 'post'], '/settings/featured-dishes',        [SettingsController::class, 'updateFeaturedDishes']);
        Route::match(['put', 'post'], '/admin/settings/exclusive-services', [SettingsController::class, 'updateExclusiveServices']);
        Route::match(['put', 'post'], '/settings/exclusive-services',       [SettingsController::class, 'updateExclusiveServices']);
        Route::match(['put', 'post'], '/admin/settings/promo-banner',       [SettingsController::class, 'updatePromoBanner']);
        Route::match(['put', 'post'], '/settings/promo-banner',             [SettingsController::class, 'updatePromoBanner']);
        Route::match(['put', 'post'], '/admin/settings/landing-reservations', [SettingsController::class, 'updateLandingReservations']);
        Route::match(['put', 'post'], '/settings/landing-reservations',       [SettingsController::class, 'updateLandingReservations']);
        Route::match(['put', 'post'], '/admin/settings/reservations-section', [SettingsController::class, 'updateLandingReservations']);
        Route::match(['put', 'post'], '/settings/reservations-section',       [SettingsController::class, 'updateLandingReservations']);
        Route::match(['put', 'post'], '/admin/settings/landing-contact',      [SettingsController::class, 'updateLandingContact']);
        Route::match(['put', 'post'], '/settings/landing-contact',            [SettingsController::class, 'updateLandingContact']);
        Route::match(['put', 'post'], '/admin/settings/contact-section',       [SettingsController::class, 'updateLandingContact']);
        Route::match(['put', 'post'], '/settings/contact-section',             [SettingsController::class, 'updateLandingContact']);
        Route::match(['put', 'post'], '/admin/settings/landing-delivery',     [SettingsController::class, 'updateLandingDelivery']);
        Route::match(['put', 'post'], '/settings/landing-delivery',           [SettingsController::class, 'updateLandingDelivery']);
        Route::match(['put', 'post'], '/admin/settings/delivery-section',      [SettingsController::class, 'updateLandingDelivery']);
        Route::match(['put', 'post'], '/settings/delivery-section',            [SettingsController::class, 'updateLandingDelivery']);
        Route::post('/admin/settings/logo',          [SettingsController::class, 'uploadLogo']);
        Route::get('/admin/cp-por-ciudad',           [SettingsController::class, 'getCPByCiudad']);
        Route::get('/admin/calles-por-cp',           [SettingsController::class, 'getCallesByCP']);

        // Imágenes
        Route::post('/admin/images/upload', [ImageController::class, 'upload']);
        Route::delete('/admin/images/delete', [ImageController::class, 'delete']);

        // Reseñas - admin
        Route::get('/admin/reviews', [ReviewController::class, 'index']);
        Route::get('/admin/resenas', [ReviewController::class, 'index']);
        Route::get('/admin/reviews/{id}', [ReviewController::class, 'show']);
        Route::get('/admin/resenas/{id}', [ReviewController::class, 'show']);
        Route::post('/admin/reviews/{id}/respond', [ReviewController::class, 'respond']);
        Route::post('/admin/reviews/{id}/responder', [ReviewController::class, 'responderResena']);
        Route::post('/admin/resenas/{id}/respond', [ReviewController::class, 'respond']);
        Route::post('/admin/resenas/{id}/responder', [ReviewController::class, 'responderResena']);
        Route::patch('/admin/reviews/{id}/report', [ReviewController::class, 'report']);
        Route::patch('/admin/resenas/{id}/report', [ReviewController::class, 'report']);
        Route::delete('/admin/reviews/{id}', [ReviewController::class, 'destroy']);
        Route::delete('/admin/resenas/{id}', [ReviewController::class, 'destroy']);

        Route::get('/admin/testimonials', [TestimonialController::class, 'adminIndex']);
        Route::post('/admin/testimonials/{id}/reply', [TestimonialController::class, 'reply']);
        Route::patch('/admin/testimonials/{id}/status', [TestimonialController::class, 'updateStatus']);
        Route::patch('/admin/testimonials/{testimonialId}/images/{imageId}', [TestimonialController::class, 'moderateImage']);

        // Promociones
        Route::get('/admin/promotions', [PromotionController::class, 'index']);
        Route::post('/admin/promotions', [PromotionController::class, 'store']);
        Route::put('/admin/promotions/{id}', [PromotionController::class, 'update']);
        Route::delete('/admin/promotions/{id}', [PromotionController::class, 'destroy']);
        Route::patch('/admin/promotions/{id}/toggle', [PromotionController::class, 'toggle']);

        // Ingredientes & Proveedores
        Route::get('/admin/ingredients/categories', [IngredientController::class, 'getCategories']);
        Route::post('/admin/ingredients/categories', [IngredientController::class, 'storeCategory']);
        Route::get('/admin/ingredients', [IngredientController::class, 'index']);
        Route::post('/admin/ingredients', [IngredientController::class, 'store']);
        Route::put('/admin/ingredients/{id}', [IngredientController::class, 'update']);
        Route::delete('/admin/ingredients/{id}', [IngredientController::class, 'destroy']);

        Route::get('/admin/suppliers/specialties', [SupplierController::class, 'getSpecialties']);
        Route::post('/admin/suppliers/specialties', [SupplierController::class, 'storeSpecialty']);
        Route::get('/admin/suppliers', [SupplierController::class, 'index']);
        Route::post('/admin/suppliers', [SupplierController::class, 'store']);
        Route::put('/admin/suppliers/{id}', [SupplierController::class, 'update']);
        Route::delete('/admin/suppliers/{id}', [SupplierController::class, 'destroy']);
        Route::patch('/admin/suppliers/{id}/toggle', [SupplierController::class, 'toggle']);

        // Stock e Inventario
        Route::get('/admin/stock', [StockController::class, 'index']);
        Route::post('/admin/stock/entry', [StockController::class, 'storeEntry']);
        Route::post('/admin/stock/adjustment', [StockController::class, 'storeAdjustment']);
        Route::patch('/admin/stock/{ingredient_id}/min', [StockController::class, 'updateMin']);
        Route::get('/admin/stock/movements', [StockController::class, 'getMovements']);
        Route::get('/admin/stock/metrics', [StockController::class, 'getMetrics']);

        // Análisis de Ventas
        Route::get('/admin/sales', [SalesAnalysisController::class, 'index']);

        // Pagos
        Route::get('/admin/payments', [PaymentController::class, 'index']);
        Route::get('/admin/payments/{id}', [PaymentController::class, 'show']);

        // Reportes
        Route::get('/admin/reports/scheduled', [ReportController::class, 'getScheduled']);
        Route::post('/admin/reports/scheduled', [ReportController::class, 'storeScheduled']);
        Route::put('/admin/reports/scheduled/{id}', [ReportController::class, 'updateScheduled']);
        Route::delete('/admin/reports/scheduled/{id}', [ReportController::class, 'destroyScheduled']);
        Route::patch('/admin/reports/scheduled/{id}/toggle', [ReportController::class, 'toggleScheduled']);
        Route::get('/admin/reports', [ReportController::class, 'index']);
        Route::get('/admin/reports/{id}', [ReportController::class, 'show']);
        Route::post('/admin/reports/generate', [ReportController::class, 'generate']);
        Route::post('/admin/reports/{id}/send', [ReportController::class, 'send']);
        Route::get('/admin/reports/ingresos-delivery',        [ReportController::class, 'ingresosDelivery']);
        Route::get('/admin/reportes/ingresos-delivery',       [ReportController::class, 'ingresosDelivery']);
        Route::get('/admin/delivery/ingresos',                 [ReportController::class, 'ingresosDelivery']);
        Route::get('/admin/reports/ingresos-por-repartidor',  [ReportController::class, 'ingresosPorRepartidor']);
        Route::get('/admin/reportes/ingresos-por-repartidor', [ReportController::class, 'ingresosPorRepartidor']);
        Route::get('/admin/delivery/ingresos-por-repartidor', [ReportController::class, 'ingresosPorRepartidor']);
        Route::get('/admin/cash-cuts/ingresos-por-repartidor',[CashCutController::class, 'ingresosPorRepartidor']);
        Route::get('/admin/reports/metricas-rendimiento',     [ReportController::class, 'metricasRendimiento']);
        Route::get('/admin/reportes/metricas-rendimiento',    [ReportController::class, 'metricasRendimiento']);
        Route::get('/admin/delivery/metricas-rendimiento',    [ReportController::class, 'metricasRendimiento']);
        Route::get('/admin/reports/performance-metrics',      [ReportController::class, 'metricasRendimiento']);
        Route::get('/admin/reports/kpis-rendimiento',         [ReportController::class, 'kpisRendimiento']);
        Route::get('/admin/reportes/kpis-rendimiento',        [ReportController::class, 'kpisRendimiento']);
        Route::get('/admin/delivery/kpis-rendimiento',        [ReportController::class, 'kpisRendimiento']);
        Route::get('/admin/kpis/kpis-rendimiento',            [KpiController::class, 'kpisRendimiento']);
        Route::delete('/admin/reports/{id}', [ReportController::class, 'destroy']);

        // Costos
        Route::get('/admin/costs', [CostAnalysisController::class, 'index']);

        // Bitácora de Auditoría
        Route::get('/admin/bitacora', [AuditLogController::class, 'index']);
        Route::get('/admin/bitacora/modulos', [AuditLogController::class, 'getModulos']);
        Route::get('/admin/bitacora/export/{format}', [AuditLogController::class, 'export']);

        // Usuarios y Roles
        Route::get('/admin/usuarios/stats', [UserController::class, 'stats']);
        Route::get('/admin/usuarios', [UserController::class, 'index']);
        Route::post('/admin/usuarios', [UserController::class, 'store']);
        Route::get('/admin/usuarios/{id}', [UserController::class, 'show']);
        Route::put('/admin/usuarios/{id}', [UserController::class, 'update']);
        Route::match(['patch', 'post', 'put'], '/admin/usuarios/{id}/toggle', [UserController::class, 'toggle']);
        Route::delete('/admin/usuarios/{id}', [UserController::class, 'destroy']);
        Route::match(['patch', 'post'], '/admin/usuarios/{id}/reset-password', [UserController::class, 'resetPassword']);

        // Matriz de Permisos y Roles
        Route::get('/admin/permisos/usuario/{id}', [PermissionController::class, 'getUserPermissions']);
        Route::put('/admin/permisos/usuario/{id}', [PermissionController::class, 'updateUserPermissions']);
        Route::get('/admin/permisos/{rol}/usuarios', [PermissionController::class, 'getRoleUsers']);
        Route::get('/admin/permisos/{rol}', [PermissionController::class, 'getRolePermissions']);
        Route::put('/admin/permisos/{rol}', [PermissionController::class, 'updateRolePermissions']);
    });

    // Vista cocina – cocina, kitchen, admin, super_admin y gerente
    Route::middleware('role:cocina,kitchen,admin,super_admin,gerente')->group(function () {
        Route::get('/kitchen/orders',                                              [KitchenController::class, 'orders']);
        Route::match(['patch', 'post', 'put'], '/kitchen/orders/{order}/status',   [KitchenController::class, 'updateStatus']);
        Route::match(['patch', 'post', 'put'], '/kitchen/orders/{id}/status',      [KitchenController::class, 'updateStatus']);
        Route::match(['patch', 'post', 'put'], '/kitchen/orders/status',           [KitchenController::class, 'updateStatus']);
        Route::match(['patch', 'post', 'put'], '/kitchen/orders/update-status',    [KitchenController::class, 'updateStatus']);
    });

    // Rutas mesero / POS / cajero
    Route::middleware('role:mesero,cajero,admin,super_admin,gerente')->group(function () {
        Route::get('/waiter/orders',                   [OrderController::class, 'kitchenOrders']);
        Route::patch('/waiter/orders/{order}/status',  [OrderController::class, 'waiterUpdateStatus']);
        Route::patch('/waiter/orders/{order}/payment', [OrderController::class, 'updatePayment']);
        Route::post('/waiter/tables/open',             [OpenTableOrderController::class, 'open']);
        Route::post('/waiter/mesas/abrir',             [OpenTableOrderController::class, 'open']);
        Route::post('/tables/open',                    [OpenTableOrderController::class, 'open']);
        Route::post('/mesas/abrir',                    [OpenTableOrderController::class, 'open']);
        Route::get('/pos/menu',                        [GetPosMenuController::class, 'index']);
        Route::get('/waiter/menu',                     [GetPosMenuController::class, 'index']);
        Route::get('/menu/pos',                        [GetPosMenuController::class, 'index']);
        Route::post('/orders/{order}/items',           [AddDishToOrderController::class, 'add']);
        Route::post('/orders/{order}/dishes',          [AddDishToOrderController::class, 'add']);
        Route::post('/orders/items',                   [AddDishToOrderController::class, 'add']);
        Route::post('/orders/add-dish',                [AddDishToOrderController::class, 'add']);
        Route::post('/waiter/orders/{order}/items',    [AddDishToOrderController::class, 'add']);
        Route::post('/waiter/orders/add-dish',         [AddDishToOrderController::class, 'add']);
    });

    // Rutas gerente
    Route::middleware('role:gerente,admin,super_admin')->group(function () {
        // próximamente
    });

    // Rutas repartidor
    Route::middleware('role:repartidor,admin,super_admin,gerente')->group(function () {
        Route::post('/driver/orders/{token}/accept', [AssignDeliveryOrderController::class, 'assign']);
        Route::post('/driver/orders/assign',         [AssignDeliveryOrderController::class, 'assign']);
        Route::post('/orders/{id}/assign-driver',    [AssignDeliveryOrderController::class, 'assign']);
        Route::post('/delivery/assign-by-folio',     [AssignDeliveryOrderController::class, 'assign']);
        Route::post('/driver/scan-qr',               [AssignDeliveryOrderController::class, 'assign']);
        Route::get('/delivery/active',               [DeliveryController::class, 'myActiveDelivery']);
        Route::get('/delivery/mi-entrega-activa',    [DeliveryController::class, 'myActiveDelivery']);
        Route::patch('/delivery/{id}/status',        [DeliveryController::class, 'updateOrderStatus']);
        Route::get('/driver/corte',                  [DeliveryController::class, 'getMyCut']);
        Route::get('/driver/my-cut',                 [DeliveryController::class, 'getMyCut']);
        Route::get('/driver/mi-corte',               [DeliveryController::class, 'getMyCut']);
        Route::get('/delivery/my-cut',               [DeliveryController::class, 'getMyCut']);
        Route::get('/delivery/mi-corte',             [DeliveryController::class, 'getMyCut']);
        Route::post('/driver/corte/notificar',       [CloseDriverShiftController::class, 'close']);
        Route::post('/driver/shift/close',           [CloseDriverShiftController::class, 'close']);
        Route::post('/delivery/notify-cut',          [CloseDriverShiftController::class, 'close']);
        Route::post('/delivery/notificar-corte',     [CloseDriverShiftController::class, 'close']);
        Route::put('/repartidores/{id}',             [RepartidorController::class, 'update']);
        Route::patch('/repartidores/{id}',           [RepartidorController::class, 'update']);
        Route::put('/drivers/{id}',                  [DriverController::class, 'update']);
        Route::patch('/drivers/{id}',                [DriverController::class, 'update']);
    });
});