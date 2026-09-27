<?php

namespace Tests\Feature;

use App\Models\Review;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EstadisticasResenasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Retorna valores en cero cuando la base de datos está vacía (Protección división entre cero).
     */
    public function test_returns_zero_stats_when_no_reviews_exist(): void
    {
        $response = $this->getJson('/api/estadisticas-resenas');

        $response->assertStatus(200)
            ->assertExactJson([
                'promedio'     => '0.0',
                'total'        => 0,
                'satisfaccion' => 0,
            ]);
    }

    /**
     * Test 2: Calcula estadísticas precisas con reseñas aprobadas de diferentes calificaciones.
     */
    public function test_calculates_stats_accurately_with_approved_reviews(): void
    {
        // 4 reseñas aprobadas: 5, 5, 4, 2
        Review::create([
            'nombre'      => 'Cliente 1',
            'telefono'    => '7441112233',
            'correo'      => 'c1@test.com',
            'rating'      => 5,
            'comentario'  => 'Excelente',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente 2',
            'telefono'    => '7442223344',
            'correo'      => 'c2@test.com',
            'rating'      => 5,
            'comentario'  => 'Maravilloso',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente 3',
            'telefono'    => '7443334455',
            'correo'      => 'c3@test.com',
            'rating'      => 4,
            'comentario'  => 'Muy bueno',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente 4',
            'telefono'    => '7444445566',
            'correo'      => 'c4@test.com',
            'rating'      => 2,
            'comentario'  => 'Regular',
            'is_approved' => true,
        ]);

        $response = $this->getJson('/api/estadisticas-resenas');

        $response->assertStatus(200)
            ->assertExactJson([
                'promedio'     => '4.0', // (5+5+4+2)/4 = 4.0
                'total'        => 4,
                'satisfaccion' => 75,    // (3 / 4) * 100 = 75%
            ]);
    }

    /**
     * Test 3: Ignora completamente reseñas no aprobadas (Zero-Trust Moderation).
     */
    public function test_ignores_unapproved_reviews_in_all_calculations(): void
    {
        // 2 reseñas aprobadas de 5 estrellas
        Review::create([
            'nombre'      => 'Cliente Aprobado 1',
            'telefono'    => '7441112233',
            'correo'      => 'aprobado1@test.com',
            'rating'      => 5,
            'comentario'  => 'Excelente comida',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente Aprobado 2',
            'telefono'    => '7442223344',
            'correo'      => 'aprobado2@test.com',
            'rating'      => 5,
            'comentario'  => 'Excelente servicio',
            'is_approved' => true,
        ]);

        // 3 reseñas NO aprobadas de 1 estrella (spam/troll)
        Review::create([
            'nombre'      => 'Troll 1',
            'telefono'    => '7449991122',
            'correo'      => 'troll1@test.com',
            'rating'      => 1,
            'comentario'  => 'Pésimo spam',
            'is_approved' => false,
        ]);
        Review::create([
            'nombre'      => 'Troll 2',
            'telefono'    => '7449991133',
            'correo'      => 'troll2@test.com',
            'rating'      => 1,
            'comentario'  => 'Pésimo spam 2',
            'is_approved' => false,
        ]);

        $response = $this->getJson('/api/estadisticas-resenas');

        $response->assertStatus(200)
            ->assertExactJson([
                'promedio'     => '5.0',
                'total'        => 2,
                'satisfaccion' => 100,
            ]);
    }

    /**
     * Test 4: Formato decimal redondeado y porcentaje entero consistente.
     */
    public function test_formats_decimals_and_percentage_correctly(): void
    {
        // 3 reseñas aprobadas: 5, 3, 2 -> Promedio: 10/3 = 3.3333 -> "3.3", Satisfaccion: (1/3)*100 = 33.3333 -> 33
        Review::create([
            'nombre'      => 'Cliente A',
            'telefono'    => '7441112233',
            'correo'      => 'a@test.com',
            'rating'      => 5,
            'comentario'  => 'Genial',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente B',
            'telefono'    => '7442223344',
            'correo'      => 'b@test.com',
            'rating'      => 3,
            'comentario'  => 'Normal',
            'is_approved' => true,
        ]);
        Review::create([
            'nombre'      => 'Cliente C',
            'telefono'    => '7443334455',
            'correo'      => 'c@test.com',
            'rating'      => 2,
            'comentario'  => 'Mejorable',
            'is_approved' => true,
        ]);

        $response = $this->getJson('/api/estadisticas-resenas');

        $response->assertStatus(200)
            ->assertExactJson([
                'promedio'     => '3.3',
                'total'        => 3,
                'satisfaccion' => 33,
            ]);
    }

    /**
     * Test 5: Endpoint alternativo '/api/reviews/estadisticas' responde idéntico.
     */
    public function test_alias_endpoint_returns_identical_statistics(): void
    {
        Review::create([
            'nombre'      => 'Cliente Alias',
            'telefono'    => '7441112233',
            'correo'      => 'alias@test.com',
            'rating'      => 4,
            'comentario'  => 'Muy rico',
            'is_approved' => true,
        ]);

        $responseDirect = $this->getJson('/api/estadisticas-resenas');
        $responseAlias  = $this->getJson('/api/reviews/estadisticas');

        $responseDirect->assertStatus(200);
        $responseAlias->assertStatus(200);

        $this->assertEquals($responseDirect->json(), $responseAlias->json());
    }
}
