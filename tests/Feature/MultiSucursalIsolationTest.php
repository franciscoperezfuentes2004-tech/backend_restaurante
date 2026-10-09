<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\Order;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiSucursalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Sucursal $sucursalA;
    private Sucursal $sucursalB;
    private User $userA;
    private User $userB;
    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Sucursales
        $this->sucursalA = Sucursal::find(1) ?? Sucursal::create([
            'id'        => 1,
            'nombre'    => 'Sucursal Matriz Centro',
            'codigo'    => 'SUC-001',
            'is_active' => true,
        ]);

        $this->sucursalB = Sucursal::create([
            'nombre'    => 'Sucursal Costera',
            'codigo'    => 'SUC-002',
            'is_active' => true,
        ]);

        // Usuarios
        $this->userA = User::factory()->create([
            'name'        => 'Gerente Sucursal A',
            'email'       => 'gerente.a@aurum.com',
            'role'        => 'gerente',
            'sucursal_id' => $this->sucursalA->id,
            'branch_id'   => $this->sucursalA->id,
        ]);

        $this->userB = User::factory()->create([
            'name'        => 'Gerente Sucursal B',
            'email'       => 'gerente.b@aurum.com',
            'role'        => 'gerente',
            'sucursal_id' => $this->sucursalB->id,
            'branch_id'   => $this->sucursalB->id,
        ]);

        $this->superAdmin = User::factory()->create([
            'name'        => 'Súper Admin Global',
            'email'       => 'superadmin@aurum.com',
            'role'        => 'super_admin',
            'sucursal_id' => null,
        ]);
    }

    public function test_authenticated_user_only_sees_records_from_their_own_sucursal(): void
    {
        // Crear categoría en Sucursal A
        $catA = Category::create([
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Entradas Sucursal A',
            'slug'        => 'entradas-sucursal-a',
            'active'      => true,
        ]);

        // Crear categoría en Sucursal B
        $catB = Category::create([
            'sucursal_id' => $this->sucursalB->id,
            'name'        => 'Bebidas Sucursal B',
            'slug'        => 'bebidas-sucursal-b',
            'active'      => true,
        ]);

        // Crear platillos
        $dishA = Dish::create([
            'sucursal_id' => $this->sucursalA->id,
            'category_id' => $catA->id,
            'name'        => 'Tacos Gobernador A',
            'slug'        => 'tacos-gobernador-a',
            'price'       => 150.00,
            'is_active'   => true,
        ]);

        $dishB = Dish::create([
            'sucursal_id' => $this->sucursalB->id,
            'category_id' => $catB->id,
            'name'        => 'Ceviche Costeño B',
            'slug'        => 'ceviche-costeno-b',
            'price'       => 180.00,
            'is_active'   => true,
        ]);

        // 1. Usuario A consulta categorías y platillos: solo ve los de su sucursal
        $this->actingAs($this->userA);
        $categoriesSeenByA = Category::pluck('id')->toArray();
        $dishesSeenByA     = Dish::pluck('id')->toArray();

        $this->assertContains($catA->id, $categoriesSeenByA);
        $this->assertNotContains($catB->id, $categoriesSeenByA);
        $this->assertContains($dishA->id, $dishesSeenByA);
        $this->assertNotContains($dishB->id, $dishesSeenByA);

        // 2. Usuario B consulta categorías y platillos: solo ve los de su sucursal
        $this->actingAs($this->userB);
        $categoriesSeenByB = Category::pluck('id')->toArray();
        $dishesSeenByB     = Dish::pluck('id')->toArray();

        $this->assertContains($catB->id, $categoriesSeenByB);
        $this->assertNotContains($catA->id, $categoriesSeenByB);
        $this->assertContains($dishB->id, $dishesSeenByB);
        $this->assertNotContains($dishA->id, $dishesSeenByB);
    }

    public function test_super_admin_can_see_records_from_all_sucursales(): void
    {
        $cat = Category::create([
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Categoría General',
            'slug'        => 'categoria-general',
            'active'      => true,
        ]);

        $dishA = Dish::create([
            'category_id' => $cat->id,
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Platillo A',
            'slug'        => 'platillo-a',
            'price'       => 100,
            'is_active'   => true,
        ]);

        $dishB = Dish::create([
            'category_id' => $cat->id,
            'sucursal_id' => $this->sucursalB->id,
            'name'        => 'Platillo B',
            'slug'        => 'platillo-b',
            'price'       => 200,
            'is_active'   => true,
        ]);

        // Súper Administrador Global ve ambos platillos
        $this->actingAs($this->superAdmin);
        $allDishes = Dish::pluck('id')->toArray();

        $this->assertContains($dishA->id, $allDishes);
        $this->assertContains($dishB->id, $allDishes);
    }

    public function test_belongs_to_sucursal_trait_automatically_assigns_authenticated_user_sucursal_on_creation(): void
    {
        $cat = Category::create([
            'sucursal_id' => $this->sucursalB->id,
            'name'        => 'Categoría B',
            'slug'        => 'categoria-b',
            'active'      => true,
        ]);

        $this->actingAs($this->userB);

        // Creación sin especificar sucursal_id
        $newDish = Dish::create([
            'category_id' => $cat->id,
            'name'        => 'Platillo Automático B',
            'slug'        => 'platillo-auto-b',
            'price'       => 99.50,
            'is_active'   => true,
        ]);

        $this->assertEquals($this->sucursalB->id, $newDish->sucursal_id);
        $this->assertEquals($this->sucursalB->nombre, $newDish->sucursal->nombre);
    }

    public function test_belongs_to_sucursal_relation_returns_correct_sucursal_instance(): void
    {
        $cat = Category::create([
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Categoría A',
            'slug'        => 'categoria-a',
            'active'      => true,
        ]);

        $dish = Dish::create([
            'category_id' => $cat->id,
            'sucursal_id' => $this->sucursalA->id,
            'name'        => 'Platillo Relación',
            'slug'        => 'platillo-relacion',
            'price'       => 120,
            'is_active'   => true,
        ]);

        $this->assertInstanceOf(Sucursal::class, $dish->sucursal);
        $this->assertEquals($this->sucursalA->id, $dish->sucursal->id);
        $this->assertEquals('SUC-001', $dish->sucursal->codigo);
    }
}
