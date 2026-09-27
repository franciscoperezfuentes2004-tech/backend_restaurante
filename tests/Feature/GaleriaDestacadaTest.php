<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GaleriaDestacadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * Test 1: Retorna arreglo vacío si no hay reseñas registradas.
     */
    public function test_returns_empty_array_when_no_reviews_exist(): void
    {
        $response = $this->getJson('/api/galeria-destacada');

        $response->assertStatus(200)
            ->assertExactJson([]);
    }

    /**
     * Test 2: Extrae fotos exclusivamente de reseñas aprobadas con calificación de 4 o 5 estrellas.
     */
    public function test_extracts_photos_only_from_approved_4_and_5_star_reviews(): void
    {
        // 1. Reseña 5 estrellas aprobada con 2 fotos (DEBE INCLUIRSE)
        Review::create([
            'nombre'      => 'Sofia Reyes',
            'telefono'    => '7441112233',
            'correo'      => 'sofia@test.com',
            'rating'      => 5,
            'comentario'  => 'Maravilloso',
            'fotos'       => ['/storage/reviews/sofia1.jpg', '/storage/reviews/sofia2.jpg'],
            'is_approved' => true,
        ]);

        // 2. Reseña 4 estrellas aprobada con 1 foto (DEBE INCLUIRSE)
        Review::create([
            'nombre'      => 'Diego Perez',
            'telefono'    => '7442223344',
            'correo'      => 'diego@test.com',
            'rating'      => 4,
            'comentario'  => 'Muy buena comida',
            'fotos'       => ['/storage/reviews/diego1.jpg'],
            'is_approved' => true,
        ]);

        // 3. Reseña 3 estrellas aprobada (NO DEBE INCLUIRSE por rating < 4)
        Review::create([
            'nombre'      => 'Raul Diaz',
            'telefono'    => '7443334455',
            'correo'      => 'raul@test.com',
            'rating'      => 3,
            'comentario'  => 'Regular',
            'fotos'       => ['/storage/reviews/raul1.jpg'],
            'is_approved' => true,
        ]);

        // 4. Reseña 5 estrellas NO aprobada (NO DEBE INCLUIRSE por is_approved = false)
        Review::create([
            'nombre'      => 'Hacker Oculto',
            'telefono'    => '7444445566',
            'correo'      => 'hacker@test.com',
            'rating'      => 5,
            'comentario'  => 'No aprobado',
            'fotos'       => ['/storage/reviews/hacker1.jpg'],
            'is_approved' => false,
        ]);

        // 5. Reseña 5 estrellas aprobada sin fotos (NO DEBE INCLUIRSE)
        Review::create([
            'nombre'      => 'Elena Vega',
            'telefono'    => '7445556677',
            'correo'      => 'elena@test.com',
            'rating'      => 5,
            'comentario'  => 'Sin fotos pero excelente',
            'fotos'       => null,
            'is_approved' => true,
        ]);

        $response = $this->getJson('/api/galeria-destacada');

        $response->assertStatus(200);
        $data = $response->json();

        $this->assertCount(3, $data);

        $urls = array_column($data, 'url');
        $clientes = array_column($data, 'cliente');

        // Verificación de URL absoluta
        $this->assertContains(url('/storage/reviews/sofia1.jpg'), $urls);
        $this->assertContains(url('/storage/reviews/sofia2.jpg'), $urls);
        $this->assertContains(url('/storage/reviews/diego1.jpg'), $urls);
        $this->assertNotContains(url('/storage/reviews/raul1.jpg'), $urls);
        $this->assertNotContains(url('/storage/reviews/hacker1.jpg'), $urls);

        // Verificación de algoritmo de privacidad (Nombre ofuscado: Sofia R. / Diego P.)
        $this->assertContains('Sofia R.', $clientes);
        $this->assertContains('Diego P.', $clientes);
        $this->assertNotContains('Sofia Reyes', $clientes);
        $this->assertNotContains('Diego Perez', $clientes);

        // Estructura de cada elemento
        foreach ($data as $item) {
            $this->assertArrayHasKey('url', $item);
            $this->assertArrayHasKey('rating', $item);
            $this->assertArrayHasKey('cliente', $item);
            $this->assertStringStartsWith('http', $item['url']);
            $this->assertGreaterThanOrEqual(4, $item['rating']);
        }
    }

    /**
     * Test 3: Limita a un máximo de 10 elementos y guarda en caché 'galeria_premium_diaria'.
     */
    public function test_caches_result_under_galeria_premium_diaria_and_limits_to_10(): void
    {
        // Creamos 15 reseñas con fotos
        for ($i = 1; $i <= 15; $i++) {
            Review::create([
                'nombre'      => "Cliente {$i}",
                'telefono'    => "74410000{$i}",
                'correo'      => "cliente{$i}@test.com",
                'rating'      => 5,
                'comentario'  => "Excelente servicio {$i}",
                'fotos'       => ["/storage/reviews/photo{$i}.jpg"],
                'is_approved' => true,
            ]);
        }

        $this->assertFalse(Cache::has('galeria_premium_diaria'));

        $response1 = $this->getJson('/api/galeria-destacada');
        $response1->assertStatus(200);
        $data1 = $response1->json();

        $this->assertCount(10, $data1);
        $this->assertTrue(Cache::has('galeria_premium_diaria'));

        // Segunda llamada recupera exactamente la misma colección del caché
        $response2 = $this->getJson('/api/galeria-destacada');
        $response2->assertStatus(200);
        $data2 = $response2->json();

        $this->assertEquals($data1, $data2);
    }
}
