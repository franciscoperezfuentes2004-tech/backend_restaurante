<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Dish;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageValidationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Test',
        ]);

        $this->category = Category::create([
            'name'   => 'Platillos Principales',
            'slug'   => 'platillos-principales',
            'active' => true,
        ]);
    }

    /**
     * Valida que se rechace un archivo con extensión no permitida o contenido no-imagen.
     */
    public function test_rejects_non_image_or_invalid_mime_file_with_custom_message(): void
    {
        $fakeScript = UploadedFile::fake()->create('exploit.php', 100, 'application/x-php');

        $response = $this->actingAs($this->admin)->postJson('/api/admin/dishes', [
            'name'        => 'Tacos Al Pastor',
            'price'       => 95.00,
            'category_id' => $this->category->id,
            'imagen'      => $fakeScript,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['imagen']);

        $errors = $response->json('errors.imagen');
        $this->assertTrue(
            in_array('El archivo debe ser una imagen válida.', $errors) ||
            in_array('Solo se permiten imágenes en formato JPG, PNG o WEBP.', $errors)
        );
    }

    /**
     * Valida que se rechace un archivo que exceda el nuevo límite global de 10MB (10240 KB).
     */
    public function test_rejects_image_exceeding_10mb_limit(): void
    {
        // 12000 KB > 10240 KB (10MB)
        $heavyImage = UploadedFile::fake()->image('foto_gigante.jpg')->size(12000);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/dishes', [
            'name'        => 'Corte Ribeye Premium',
            'price'       => 450.00,
            'category_id' => $this->category->id,
            'imagen'      => $heavyImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['imagen']);

        $response->assertJsonFragment([
            'imagen' => ['La imagen no debe pesar más de 10MB.'],
        ]);
    }

    /**
     * Valida que se acepte una imagen pesada antes bloqueada por 2MB (ej. 3MB a 5MB <= 10240 KB)
     * y que el backend la procese y almacene comprimida en formato .webp.
     */
    public function test_accepts_valid_image_up_to_10mb_and_compresses_to_webp(): void
    {
        // 3500 KB (3.5MB > 2MB previo, pero <= 10MB permitido)
        $validHeavyImage = UploadedFile::fake()->image('plato_delicioso.jpg', 1600, 1200)->size(3500);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/dishes', [
            'name'        => 'Ensalada César Gourmet',
            'price'       => 120.00,
            'category_id' => $this->category->id,
            'imagen'      => $validHeavyImage,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('dishes', [
            'name' => 'Ensalada César Gourmet',
        ]);

        $dish = Dish::where('name', 'Ensalada César Gourmet')->first();
        $this->assertNotNull($dish);
        $this->assertNotNull($dish->image_url);
        $this->assertStringEndsWith('.webp', $dish->image_url);

        $relativePath = 'dishes/' . basename($dish->image_url);
        Storage::disk('public')->assertExists($relativePath);
    }

    /**
     * Valida que la categoría rechace imágenes mayores a 10MB.
     */
    public function test_category_rejects_heavy_image_over_10mb(): void
    {
        $heavyImage = UploadedFile::fake()->image('cat_pesada.png')->size(12000);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/categories', [
            'name'   => 'Postres Finos',
            'imagen' => $heavyImage,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['imagen']);

        $response->assertJsonFragment([
            'imagen' => ['La imagen no debe pesar más de 10MB.'],
        ]);
    }

    /**
     * Valida que la categoría acepte imagen de hasta 10MB y la comprima a .webp.
     */
    public function test_category_accepts_image_and_compresses_to_webp(): void
    {
        $validImage = UploadedFile::fake()->image('categoria_bebidas.jpg', 1200, 800)->size(3000);

        $response = $this->actingAs($this->admin)->postJson('/api/admin/categories', [
            'name'   => 'Bebidas Artesanales',
            'imagen' => $validImage,
        ]);

        $response->assertStatus(201);
        $cat = Category::where('name', 'Bebidas Artesanales')->first();
        $this->assertNotNull($cat);
        $this->assertNotNull($cat->image_url);
        $this->assertStringEndsWith('.webp', $cat->image_url);

        $relativePath = 'categories/' . basename($cat->image_url);
        Storage::disk('public')->assertExists($relativePath);
    }
}
