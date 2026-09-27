<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use App\Models\Ingredient;
use App\Models\Stock;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GetPosMenuTest extends TestCase
{
    use RefreshDatabase;

    protected User $mesero;
    protected User $cajero;
    protected User $repartidor;
    protected Category $activeCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mesero = User::factory()->create([
            'role' => 'mesero',
            'name' => 'Carlos Mesero'
        ]);

        $this->cajero = User::factory()->create([
            'role' => 'cajero',
            'name' => 'Ana Cajera'
        ]);

        $this->repartidor = User::factory()->create([
            'role' => 'repartidor',
            'name' => 'Beto Repartidor'
        ]);

        $this->activeCategory = Category::create([
            'name'        => 'Platillos Principales',
            'slug'        => 'platillos-principales',
            'description' => 'Platos fuertes de la casa',
            'active'      => true,
            'is_active'   => true,
        ]);
    }

    public function test_inactive_and_unavailable_dishes_are_excluded(): void
    {
        // Dish activo y disponible
        $activeDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Corte Rib Eye',
            'slug'         => 'corte-rib-eye',
            'price'        => 350.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        // Dish inactivo
        $inactiveDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Corte New York Inactivo',
            'slug'         => 'corte-new-york-inactivo',
            'price'        => 320.00,
            'is_active'    => false,
            'is_available' => false,
        ]);

        // Dish activo pero marcado no disponible
        $unavailableDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Tomahawk No Disponible',
            'slug'         => 'tomahawk-no-disponible',
            'price'        => 800.00,
            'is_active'    => false,
            'is_available' => false,
        ]);

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'dishes',
            'categories',
            'total'
        ]);

        $dishNames = collect($response->json('dishes'))->pluck('name')->all();

        $this->assertContains('Corte Rib Eye', $dishNames);
        $this->assertNotContains('Corte New York Inactivo', $dishNames);
        $this->assertNotContains('Tomahawk No Disponible', $dishNames);
    }

    public function test_soft_deleted_dishes_are_excluded(): void
    {
        $deletedDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Pescado Zarandeado Borrado',
            'slug'         => 'pescado-zarandeado-borrado',
            'price'        => 280.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        $deletedDish->delete(); // Soft delete

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $dishNames = collect($response->json('dishes'))->pluck('name')->all();
        $this->assertNotContains('Pescado Zarandeado Borrado', $dishNames);
    }

    public function test_dishes_from_inactive_categories_are_excluded(): void
    {
        $inactiveCategory = Category::create([
            'name'        => 'Temporada Navideña',
            'slug'        => 'temporada-navidena',
            'description' => 'Platillos fuera de temporada',
            'active'      => false,
            'is_active'   => false,
        ]);

        $seasonalDish = Dish::create([
            'category_id'  => $inactiveCategory->id,
            'name'         => 'Pavo Navideño',
            'slug'         => 'pavo-navideno',
            'price'        => 400.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $dishNames = collect($response->json('dishes'))->pluck('name')->all();
        $this->assertNotContains('Pavo Navideño', $dishNames);
    }

    public function test_dish_with_zero_or_negative_stock_ingredient_is_marked_sold_out(): void
    {
        // Ingrediente Cecina con stock 0
        $cecina = Ingredient::create([
            'name'      => 'Cecina de Yecapixtla',
            'category'  => 'Carnes',
            'unit'      => 'kg',
            'base_cost' => 180.00,
        ]);

        Stock::create([
            'ingredient_id' => $cecina->id,
            'quantity'      => 0,
            'min_quantity'  => 5,
        ]);

        $cecinaDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Tacos de Cecina',
            'slug'         => 'tacos-de-cecina',
            'price'        => 120.00,
            'is_active'    => true,
            'is_available' => true,
            'ingredients'  => ['Cecina de Yecapixtla', 'Tortillas', 'Cebolla'],
        ]);

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $dishes = collect($response->json('dishes'));
        $dishData = $dishes->firstWhere('id', $cecinaDish->id);

        $this->assertNotNull($dishData);
        $this->assertTrue($dishData['is_sold_out'], 'El platillo con cecina en stock 0 debe tener is_sold_out => true');
    }

    public function test_dish_with_sufficient_stock_has_is_sold_out_false(): void
    {
        $arrachera = Ingredient::create([
            'name'      => 'Arrachera Marinada',
            'category'  => 'Carnes',
            'unit'      => 'kg',
            'base_cost' => 220.00,
        ]);

        Stock::create([
            'ingredient_id' => $arrachera->id,
            'quantity'      => 25.0,
            'min_quantity'  => 5.0,
        ]);

        $arracheraDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Tacos de Arrachera',
            'slug'         => 'tacos-de-arrachera',
            'price'        => 160.00,
            'is_active'    => true,
            'is_available' => true,
            'ingredients'  => ['Arrachera Marinada', 'Tortillas'],
        ]);

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $dishes = collect($response->json('dishes'));
        $dishData = $dishes->firstWhere('id', $arracheraDish->id);

        $this->assertNotNull($dishData);
        $this->assertFalse($dishData['is_sold_out'], 'El platillo con stock suficiente debe tener is_sold_out => false');
    }

    public function test_dish_with_manual_sold_out_flag_returns_true(): void
    {
        $manualSoldOutDish = Dish::create([
            'category_id'  => $this->activeCategory->id,
            'name'         => 'Especial del Chef Agotado',
            'slug'         => 'especial-chef-agotado',
            'price'        => 290.00,
            'is_active'    => true,
            'is_available' => true,
            'is_sold_out'  => true,
        ]);

        $response = $this->actingAs($this->mesero)->getJson('/api/pos/menu');

        $response->assertStatus(200);
        $dishes = collect($response->json('dishes'));
        $dishData = $dishes->firstWhere('id', $manualSoldOutDish->id);

        $this->assertNotNull($dishData);
        $this->assertTrue($dishData['is_sold_out']);
    }

    public function test_roles_authorization_and_unauthenticated_restrictions(): void
    {
        // 1. Sin autenticar -> 401
        $guestRes = $this->getJson('/api/pos/menu');
        $guestRes->assertStatus(401);

        // 2. Repartidor no autorizado para menú POS -> 403
        $driverRes = $this->actingAs($this->repartidor)->getJson('/api/pos/menu');
        $driverRes->assertStatus(403);

        // 3. Cajero y mesero autorizados -> 200
        $cajeroRes = $this->actingAs($this->cajero)->getJson('/api/pos/menu');
        $cajeroRes->assertStatus(200);

        $meseroRes = $this->actingAs($this->mesero)->getJson('/api/pos/menu');
        $meseroRes->assertStatus(200);
    }
}
