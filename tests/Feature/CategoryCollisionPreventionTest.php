<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Dish;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryCollisionPreventionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $mesero;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Restaurante'
        ]);

        $this->mesero = User::factory()->create([
            'role' => 'mesero',
            'name' => 'Carlos Mesero'
        ]);
    }

    /**
     * 1. No se puede eliminar una categoría que tiene platillos activos asociados.
     * Debe retornar HTTP 422 con el mensaje claro solicitado, evitando colisión 500.
     */
    public function test_cannot_delete_category_with_active_dishes_returns_422(): void
    {
        $category = Category::create([
            'name'        => 'Bebidas Artesanales',
            'slug'        => 'bebidas-artesanales',
            'description' => 'Bebidas de la casa',
            'active'      => true,
        ]);

        $dish = Dish::create([
            'category_id'  => $category->id,
            'name'         => 'Cerveza de la Casa',
            'slug'         => 'cerveza-de-la-casa',
            'price'        => 75.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se puede eliminar esta categoría porque contiene platillos asociados. Reasigna o elimina los platillos primero.'
        ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /**
     * 2. No se puede eliminar una categoría que tiene platillos inactivos (is_active = false).
     * Los platillos inactivos siguen existiendo en el catálogo y deben ser reasignados o eliminados.
     */
    public function test_cannot_delete_category_with_inactive_dishes_returns_422(): void
    {
        $category = Category::create([
            'name'        => 'Platillos Inactivos Test',
            'slug'        => 'platillos-inactivos-test',
            'description' => 'Categoría con platillos apagados',
            'active'      => true,
        ]);

        $dish = Dish::create([
            'category_id'  => $category->id,
            'name'         => 'Platillo Apagado',
            'slug'         => 'platillo-apagado',
            'price'        => 95.00,
            'is_active'    => false,
            'is_available' => false,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(422);
        $response->assertJson([
            'message' => 'No se puede eliminar esta categoría porque contiene platillos asociados. Reasigna o elimina los platillos primero.'
        ]);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    /**
     * 3. Si los platillos asociados ya fueron eliminados a la papelera (Soft-Deleted),
     * la categoría puede eliminarse con éxito purgando los huérfanos de la papelera sin colapsar PostgreSQL.
     */
    public function test_can_delete_category_with_soft_deleted_dishes_without_fk_error(): void
    {
        $category = Category::create([
            'name'        => 'Postres Finos',
            'slug'        => 'postres-finos',
            'description' => 'Postres artesanales',
            'active'      => true,
        ]);

        $dish = Dish::create([
            'category_id'  => $category->id,
            'name'         => 'Tiramisú',
            'slug'         => 'tiramisu',
            'price'        => 120.00,
            'is_active'    => true,
            'is_available' => true,
        ]);

        // Soft-delete del platillo (enviado a papelera por el usuario)
        $dish->delete();
        $this->assertSoftDeleted('dishes', ['id' => $dish->id]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Categoría eliminada correctamente'
        ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * 3. Se puede eliminar correctamente una categoría sin ningún platillo vinculado.
     */
    public function test_can_delete_category_without_dishes(): void
    {
        $category = Category::create([
            'name'        => 'Categoría Vacía',
            'slug'        => 'categoria-vacia',
            'description' => 'Sin platillos',
            'active'      => true,
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Categoría eliminada correctamente'
        ]);

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * 4. Usuario no autorizado (mesero) no puede eliminar categorías.
     */
    public function test_unauthorized_user_cannot_delete_category(): void
    {
        $category = Category::create([
            'name'        => 'Categoría Protegida',
            'slug'        => 'categoria-protegida',
            'active'      => true,
        ]);

        $response = $this->actingAs($this->mesero, 'sanctum')
            ->deleteJson("/api/admin/categories/{$category->id}");

        $response->assertStatus(403);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }
}
