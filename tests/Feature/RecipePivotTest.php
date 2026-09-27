<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Dish;
use App\Models\Product;
use App\Models\Ingredient;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;

class RecipePivotTest extends TestCase
{
    use RefreshDatabase;

    protected Dish $dish;
    protected Ingredient $pan;
    protected Ingredient $carne;
    protected Ingredient $queso;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = Category::create([
            'name'   => 'Hamburguesas',
            'slug'   => 'hamburguesas',
            'active' => true,
        ]);

        $this->dish = Dish::create([
            'category_id'  => $this->category->id,
            'name'         => 'Hamburguesa Clasica',
            'slug'         => 'hamburguesa-clasica',
            'price'        => 120.00,
            'is_available' => true,
        ]);

        $this->pan = Ingredient::create([
            'name'      => 'Pan Brioche',
            'category'  => 'Panaderia',
            'unit'      => 'pieza',
            'base_cost' => 10.00,
        ]);

        $this->carne = Ingredient::create([
            'name'      => 'Carne de Res',
            'category'  => 'Carnes',
            'unit'      => 'kg',
            'base_cost' => 150.00,
        ]);

        $this->queso = Ingredient::create([
            'name'      => 'Queso Cheddar',
            'category'  => 'Lacteos',
            'unit'      => 'rebanada',
            'base_cost' => 5.00,
        ]);
    }

    public function test_assigning_recipe_via_api_syncs_pivot_table_properly(): void
    {
        $payload = [
            'ingredientes' => [
                $this->pan->id   => ['cantidad_requerida' => 1],
                $this->carne->id => ['cantidad_requerida' => 0.150],
                $this->queso->id => ['cantidad_requerida' => 2],
            ]
        ];

        $response = $this->postJson("/api/productos/{$this->dish->id}/receta", $payload);

        $response->assertStatus(200)
                 ->assertJson([
                     'mensaje' => 'Receta asignada exitosamente'
                 ]);

        $this->dish->refresh();
        $this->assertCount(3, $this->dish->ingredientes);

        $carnePivot = $this->dish->ingredientes->firstWhere('id', $this->carne->id)->pivot;
        $this->assertEquals(0.150, (float) $carnePivot->cantidad_requerida);

        $quesoPivot = $this->dish->ingredientes->firstWhere('id', $this->queso->id)->pivot;
        $this->assertEquals(2.0, (float) $quesoPivot->cantidad_requerida);
    }

    public function test_sync_overwrites_old_recipe_with_new_ingredients(): void
    {
        // 1. Asignar receta inicial con Pan y Carne
        $this->dish->ingredientes()->sync([
            $this->pan->id   => ['cantidad_requerida' => 1],
            $this->carne->id => ['cantidad_requerida' => 0.200],
        ]);
        $this->assertCount(2, $this->dish->ingredientes);

        // 2. Sobrescribir receta dejando solo Queso
        $response = $this->postJson("/api/dishes/{$this->dish->id}/receta", [
            'ingredientes' => [
                $this->queso->id => ['cantidad_requerida' => 3],
            ]
        ]);

        $response->assertStatus(200);

        $this->dish->refresh();
        $this->assertCount(1, $this->dish->ingredientes);
        $this->assertEquals($this->queso->id, $this->dish->ingredientes->first()->id);
        $this->assertEquals(3.0, (float) $this->dish->ingredientes->first()->pivot->cantidad_requerida);
    }

    public function test_product_model_alias_has_working_ingredientes_relationship(): void
    {
        $product = Product::findOrFail($this->dish->id);

        $product->ingredientes()->sync([
            $this->pan->id => ['cantidad_requerida' => 2],
        ]);

        $this->assertCount(1, $product->ingredientes);
        $this->assertEquals(2.0, (float) $product->ingredientes->first()->pivot->cantidad_requerida);
    }

    public function test_can_get_recipe_via_get_http_method(): void
    {
        $this->dish->ingredientes()->sync([
            $this->pan->id   => ['cantidad_requerida' => 2],
            $this->carne->id => ['cantidad_requerida' => 0.250],
        ]);

        $response = $this->getJson("/api/productos/{$this->dish->id}/receta");
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'id',
                     'nombre',
                     'receta',
                     'ingredientes',
                 ]);

        $data = $response->json();
        $this->assertCount(2, $data['receta']);

        // Probar también ruta alternativa con dishes
        $responseDishes = $this->getJson("/api/dishes/{$this->dish->id}/receta");
        $responseDishes->assertStatus(200);
    }
}