<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ResenasDestacadasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Retorna arreglo vacío cuando no existen reseñas en la base de datos.
     */
    public function test_returns_empty_array_when_no_reviews_exist(): void
    {
        $response = $this->getJson('/api/resenas-destacadas');

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    /**
     * Test 2: Retorna tarjetas únicas con nombre ofuscado, URLs absolutas y tiempo relativo.
     */
    public function test_returns_unique_cards_with_privacy_and_absolute_urls(): void
    {
        // 1. Reseña 5 estrellas aprobada (DEBE SALIR)
        $r1 = Review::create([
            'nombre'      => 'Pancho Hernandez',
            'telefono'    => '7441112233',
            'correo'      => 'pancho@test.com',
            'rating'      => 5,
            'comentario'  => 'El corte de carne estuvo impecable.',
            'fotos'       => ['/storage/reviews/corte.jpg'],
            'is_approved' => true,
        ]);

        // 2. Reseña 4 estrellas aprobada sin fotos (DEBE SALIR)
        $r2 = Review::create([
            'nombre'      => 'Valeria Castro Morales',
            'telefono'    => '7442223344',
            'correo'      => 'valeria@test.com',
            'rating'      => 4,
            'comentario'  => 'Excelente ambiente y música en vivo.',
            'fotos'       => null,
            'is_approved' => true,
        ]);

        // 3. Reseña 3 estrellas aprobada (NO DEBE SALIR por rating < 4)
        Review::create([
            'nombre'      => 'Rodrigo Luna',
            'telefono'    => '7443334455',
            'correo'      => 'rodrigo@test.com',
            'rating'      => 3,
            'comentario'  => 'Servicio tardado',
            'fotos'       => null,
            'is_approved' => true,
        ]);

        // 4. Reseña 5 estrellas NO aprobada (NO DEBE SALIR por is_approved = false)
        Review::create([
            'nombre'      => 'Usuario No Aprobado',
            'telefono'    => '7444445566',
            'correo'      => 'spam@test.com',
            'rating'      => 5,
            'comentario'  => 'Spam',
            'fotos'       => null,
            'is_approved' => false,
        ]);

        $response = $this->getJson('/api/resenas-destacadas');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(2, $data);

        $ids = array_column($data, 'id');
        $nombres = array_column($data, 'nombre');

        $this->assertContains($r1->id, $ids);
        $this->assertContains($r2->id, $ids);

        // Ofuscación de apellidos
        $this->assertContains('Pancho H.', $nombres);
        $this->assertContains('Valeria C.', $nombres);
        $this->assertNotContains('Pancho Hernandez', $nombres);

        // Estructura de cada tarjeta
        foreach ($data as $card) {
            $this->assertArrayHasKey('id', $card);
            $this->assertArrayHasKey('nombre', $card);
            $this->assertArrayHasKey('rating', $card);
            $this->assertArrayHasKey('comentario', $card);
            $this->assertArrayHasKey('fotos', $card);
            $this->assertArrayHasKey('tiempo', $card);
            $this->assertIsArray($card['fotos']);
            $this->assertGreaterThanOrEqual(4, $card['rating']);
        }

        // Validación de URLs absolutas en fotos
        $cardPancho = collect($data)->firstWhere('id', $r1->id);
        $this->assertCount(1, $cardPancho['fotos']);
        $this->assertStringStartsWith('http', $cardPancho['fotos'][0]);
        $this->assertStringContainsString('/storage/reviews/corte.jpg', $cardPancho['fotos'][0]);
    }

    /**
     * Test 3: Limita a un máximo de 10 tarjetas únicas y congela en caché bajo 'tarjetas_landing_diarias'.
     */
    public function test_caches_up_to_10_unique_cards_daily(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            Review::create([
                'nombre'      => "Cliente Regular {$i}",
                'telefono'    => "74412300{$i}",
                'correo'      => "cliente{$i}@test.com",
                'rating'      => 5,
                'comentario'  => "Opinión positiva número {$i}",
                'fotos'       => ["/storage/reviews/card_{$i}.jpg"],
                'is_approved' => true,
            ]);
        }

        $this->assertFalse(Cache::has('tarjetas_landing_diarias'));

        $response1 = $this->getJson('/api/resenas-destacadas');
        $response1->assertStatus(200);
        $data1 = $response1->json();

        $this->assertCount(10, $data1);
        $this->assertTrue(Cache::has('tarjetas_landing_diarias'));

        // Garantiza IDs únicos sin duplicados
        $ids = array_column($data1, 'id');
        $this->assertCount(10, array_unique($ids));

        // Segunda llamada devuelve exactamente el mismo snapshot congelado del caché
        $response2 = $this->getJson('/api/resenas-destacadas');
        $response2->assertStatus(200);
        $data2 = $response2->json();

        $this->assertEquals($data1, $data2);
    }
}
