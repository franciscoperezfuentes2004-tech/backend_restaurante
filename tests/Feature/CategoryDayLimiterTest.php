<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CategoryDayLimiterTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Test'
        ]);
    }

    /**
     * Test store category with switch OFF (limitar_dias = false):
     * forces dias_disponibilidad to null even if React sent days.
     */
    public function test_store_category_with_switch_off_forces_null_days(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/categories', [
            'name'                => 'Desayunos Ejecutivos',
            'limitar_dias'        => false,
            'dias_disponibilidad' => ['Lun', 'Mar', 'Mié'], // Sent by mistake by frontend
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'name'         => 'Desayunos Ejecutivos',
            'limitar_dias' => false,
        ]);

        $cat = Category::where('name', 'Desayunos Ejecutivos')->first();
        $this->assertNull($cat->dias_disponibilidad);
        $this->assertNull($cat->days);
    }

    /**
     * Test store category with switch ON (limitar_dias = true):
     * saves the provided days array.
     */
    public function test_store_category_with_switch_on_saves_days_array(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/categories', [
            'name'                => 'Buffet Fin de Semana',
            'limitar_dias'        => true,
            'dias_disponibilidad' => ['Sáb', 'Dom'],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('categories', [
            'name'         => 'Buffet Fin de Semana',
            'limitar_dias' => true,
        ]);

        $cat = Category::where('name', 'Buffet Fin de Semana')->first();
        $this->assertEquals(['Sáb', 'Dom'], $cat->dias_disponibilidad);
    }

    /**
     * Test update category turning switch OFF:
     * clears dias_disponibilidad to null.
     */
    public function test_update_category_turning_switch_off_clears_days(): void
    {
        $cat = Category::create([
            'name'                => 'Menú de Temporada',
            'slug'                => 'menu-de-temporada',
            'limitar_dias'        => true,
            'dias_disponibilidad' => ['Vie', 'Sáb'],
            'days'                => ['Vie', 'Sáb'],
            'active'              => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/categories/{$cat->id}", [
            'name'                => 'Menú de Temporada Actualizado',
            'limitar_dias'        => false,
            'dias_disponibilidad' => ['Vie', 'Sáb'], // Error frontend data
        ]);

        $response->assertStatus(200);

        $cat->refresh();
        $this->assertFalse($cat->limitar_dias);
        $this->assertNull($cat->dias_disponibilidad);
        $this->assertNull($cat->days);
    }

    /**
     * Test update category turning switch ON:
     * saves new availability days.
     */
    public function test_update_category_turning_switch_on_sets_days(): void
    {
        $cat = Category::create([
            'name'                => 'Cenas Gourmet',
            'slug'                => 'cenas-gourmet',
            'limitar_dias'        => false,
            'dias_disponibilidad' => null,
            'active'              => true,
        ]);

        $response = $this->actingAs($this->admin)->putJson("/api/categories/{$cat->id}", [
            'name'                => 'Cenas Gourmet',
            'limitar_dias'        => true,
            'dias_disponibilidad' => ['Jue', 'Vie', 'Sáb'],
        ]);

        $response->assertStatus(200);

        $cat->refresh();
        $this->assertTrue($cat->limitar_dias);
        $this->assertEquals(['Jue', 'Vie', 'Sáb'], $cat->dias_disponibilidad);
    }
}
