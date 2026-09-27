<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use App\Models\RestaurantSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FeaturedDishesEmptyArrayTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;
    protected Dish $dish1;
    protected Dish $dish2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Restaurante'
        ]);

        $this->category = Category::create([
            'name'        => 'Entradas Exclusivas',
            'slug'        => 'entradas-exclusivas',
            'description' => 'Entradas finas',
            'active'      => true,
            'is_active'   => true,
        ]);

        $this->dish1 = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Carpaccio de Res',
            'slug'         => 'carpaccio-de-res',
            'price'        => 220.00,
            'is_active'    => true,
            'is_available' => true,
            'is_featured'  => true,
        ]);

        $this->dish2 = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Tartar de Atún',
            'slug'         => 'tartar-de-atun',
            'price'        => 280.00,
            'is_active'    => true,
            'is_available' => true,
            'is_featured'  => true,
        ]);
    }

    /**
     * 1. Al enviar featured_dishes: [], la base de datos almacena explícitamente []
     * en restaurant_settings.featured_dishes y en platillos_seccion.
     */
    public function test_admin_can_update_featured_dishes_with_empty_array(): void
    {
        $payload = [
            'title'               => 'Nuestra Selección Gourmet',
            'subtitle'            => 'Platillos de Autor',
            'button_text'         => 'Ver Menú',
            'featured_categories' => [$this->category->id],
            'featured_dishes'     => [], // Arreglo vacío explícito
        ];

        $response = $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/featured-dishes', $payload);

        $response->assertStatus(200);

        $settings = RestaurantSetting::first();
        $this->assertNotNull($settings);
        $this->assertSame([], $settings->featured_dishes);

        $platillosSeccion = $settings->platillos_seccion;
        $this->assertIsArray($platillosSeccion);
        $this->assertSame([], $platillosSeccion['selected_dishes']);
        $this->assertSame([], $platillosSeccion['featured_dishes']);

        // Verificar payload retornado
        $response->assertJsonPath('settings.featured_dishes', []);
        $response->assertJsonPath('settings.platillos_seccion.dishes', []);
    }

    /**
     * 2. Sincronización de la columna booleana dishes.is_featured:
     * Al vaciar el arreglo, todos los platillos pasan a is_featured = false.
     * Al incluir un subconjunto, solo los seleccionados quedan con is_featured = true.
     */
    public function test_dishes_is_featured_flag_is_synchronized_on_empty_and_populated(): void
    {
        // Paso A: Asignar solo dish1 como destacado
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/featured-dishes', [
                'title'               => 'Carta de Autor',
                'featured_categories' => [$this->category->id],
                'featured_dishes'     => [$this->dish1->id],
            ])->assertStatus(200);

        $this->assertTrue($this->dish1->fresh()->is_featured);
        $this->assertFalse($this->dish2->fresh()->is_featured);

        // Paso B: Vaciar los platillos destacados con []
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/featured-dishes', [
                'title'               => 'Carta de Autor',
                'featured_categories' => [$this->category->id],
                'featured_dishes'     => [],
            ])->assertStatus(200);

        $this->assertFalse($this->dish1->fresh()->is_featured);
        $this->assertFalse($this->dish2->fresh()->is_featured);
    }

    /**
     * 3. Prevención de Fallback Automático:
     * Si featured_dishes está vacío ([]), el endpoint público /settings/landing
     * retorna estrictamente [] y NO inventa platillos ni recurre a los últimos creados.
     */
    public function test_show_endpoint_returns_strictly_empty_array_without_fallback_queries(): void
    {
        // Guardar explícitamente configuración con lista vacía
        $settings = RestaurantSetting::firstOrCreate([]);
        $settings->update([
            'featured_categories' => [$this->category->id],
            'featured_dishes'     => [],
            'platillos_seccion'   => [
                'selected_dishes'     => [],
                'featured_dishes'     => [],
                'selected_categories' => [$this->category->id],
            ]
        ]);

        // Asegurar que existen platillos disponibles en la base de datos
        $this->assertGreaterThan(0, Dish::where('is_available', true)->count());

        // Petición pública a landing
        $response = $this->getJson('/api/settings/landing');

        $response->assertStatus(200);

        // Comprobar que featured_dishes es un arreglo estrictamente vacío
        $data = $response->json();
        $this->assertIsArray($data['featured_dishes']);
        $this->assertEmpty($data['featured_dishes']);
        $this->assertCount(0, $data['featured_dishes']);

        // Comprobar también en platillos_seccion.dishes
        $this->assertEmpty($data['platillos_seccion']['dishes']);
    }

    /**
     * 4. Destrucción de Caché (Invalidación):
     * Tras la actualización de platillos destacados, las llaves de caché relacionadas
     * se destruyen de inmediato forzando consultas frescas.
     */
    public function test_cache_keys_are_invalidated_on_featured_dishes_update(): void
    {
        // Sembrar valores previos en caché
        Cache::put('landing_featured_dishes', ['platillo_obsoleto_1', 'platillo_obsoleto_2'], 3600);
        Cache::put('landing_settings', ['settings_viejas' => true], 3600);
        Cache::put('restaurant_settings', ['data' => 123], 3600);
        Cache::put('landing_menu', ['menu_cache' => true], 3600);

        $this->assertTrue(Cache::has('landing_featured_dishes'));
        $this->assertTrue(Cache::has('landing_settings'));
        $this->assertTrue(Cache::has('restaurant_settings'));

        // Realizar la actualización con []
        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings/featured-dishes', [
                'title'           => 'Menú Limpio',
                'featured_dishes' => [],
            ])->assertStatus(200);

        // Verificar que la caché fue destruida
        $this->assertFalse(Cache::has('landing_featured_dishes'));
        $this->assertFalse(Cache::has('landing_settings'));
        $this->assertFalse(Cache::has('restaurant_settings'));
        $this->assertFalse(Cache::has('landing_menu'));
    }

    /**
     * 5. Actualización general de settings (PUT /admin/settings):
     * También procesa y persiste correctamente featured_dishes: [].
     */
    public function test_general_settings_update_persists_empty_featured_dishes_and_invalidates_cache(): void
    {
        Cache::put('landing_featured_dishes', 'viejo', 3600);

        $this->actingAs($this->admin, 'sanctum')
            ->putJson('/api/admin/settings', [
                'restaurant_name' => 'AURUM TEST',
                'featured_dishes' => [],
            ])->assertStatus(200);

        $settings = RestaurantSetting::first();
        $this->assertSame([], $settings->featured_dishes);
        $this->assertFalse(Cache::has('landing_featured_dishes'));
    }
}
