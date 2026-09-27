<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactoTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Envío exitoso de formulario de contacto con datos válidos.
     */
    public function test_envio_contacto_exitoso(): void
    {
        $payload = [
            'nombre'   => 'Carlos Mendoza Hernández',
            'email'    => 'carlos@gmail.com',
            'telefono' => '7441234567',
            'asunto'   => 'Consulta para evento privado',
            'mensaje'  => 'Hola, me gustaría cotizar una cena para 20 personas este sábado.',
        ];

        $response = $this->postJson('/api/contacto', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '¡Mensaje enviado con éxito! Nos pondremos en contacto contigo pronto.',
                'data'    => [
                    'nombre'   => 'Carlos Mendoza Hernández',
                    'email'    => 'carlos@gmail.com',
                    'telefono' => '7441234567',
                    'asunto'   => 'Consulta para evento privado',
                    'mensaje'  => 'Hola, me gustaría cotizar una cena para 20 personas este sábado.',
                ],
            ]);
    }

    /**
     * Test 2: Sanitización previa mediante strip_tags elimina etiquetas HTML peligrosas.
     */
    public function test_sanitizacion_elimina_etiquetas_html_y_scripts(): void
    {
        $payload = [
            'nombre'   => '<b>Carlos</b> Mendoza',
            'email'    => 'carlos@gmail.com',
            'telefono' => '7441234567',
            'asunto'   => '<script>alert("xss")</script>Reserva especial',
            'mensaje'  => '<p>Hola, queremos celebrar un cumpleaños <iframe src="evil.com"></iframe> en su terraza.</p>',
        ];

        $response = $this->postJson('/api/contacto', $payload);

        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertEquals('Carlos Mendoza', $data['nombre']);
        $this->assertEquals('alert("xss")Reserva especial', $data['asunto']);
        $this->assertEquals('Hola, queremos celebrar un cumpleaños  en su terraza.', $data['mensaje']);
        $this->assertStringNotContainsString('<', $data['mensaje']);
        $this->assertStringNotContainsString('>', $data['mensaje']);
    }

    /**
     * Test 3: Rechaza nombres con números o caracteres inválidos.
     */
    public function test_rechaza_nombre_invalido(): void
    {
        $payload = [
            'nombre'   => 'Carlos123 Perez',
            'email'    => 'carlos@gmail.com',
            'telefono' => '7441234567',
            'asunto'   => 'Consulta general',
            'mensaje'  => 'Este es un mensaje de prueba con longitud suficiente.',
        ];

        $response = $this->postJson('/api/contacto', $payload);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * Test 4: Rechaza teléfono si no contiene exactamente 10 dígitos.
     */
    public function test_rechaza_telefono_que_no_tenga_10_digitos(): void
    {
        // Menos de 10 dígitos
        $response1 = $this->postJson('/api/contacto', [
            'nombre'   => 'Carlos Mendoza',
            'email'    => 'carlos@gmail.com',
            'telefono' => '74412345',
            'asunto'   => 'Consulta general',
            'mensaje'  => 'Este es un mensaje de prueba con longitud suficiente.',
        ]);
        $response1->assertStatus(422)->assertJsonValidationErrors(['telefono']);

        // Con letras o caracteres no numéricos
        $response2 = $this->postJson('/api/contacto', [
            'nombre'   => 'Carlos Mendoza',
            'email'    => 'carlos@gmail.com',
            'telefono' => '744123456A',
            'asunto'   => 'Consulta general',
            'mensaje'  => 'Este es un mensaje de prueba con longitud suficiente.',
        ]);
        $response2->assertStatus(422)->assertJsonValidationErrors(['telefono']);
    }

    /**
     * Test 5: Rechaza email con formato inválido o dominio inexistente.
     */
    public function test_rechaza_correo_invalido_o_dominio_falso(): void
    {
        $response = $this->postJson('/api/contacto', [
            'nombre'   => 'Carlos Mendoza',
            'email'    => 'no-es-correo',
            'telefono' => '7441234567',
            'asunto'   => 'Consulta general',
            'mensaje'  => 'Este es un mensaje de prueba con longitud suficiente.',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['email']);
    }

    /**
     * Test 6: Rechaza mensaje muy corto (menor a 10 caracteres).
     */
    public function test_rechaza_mensaje_demasiado_corto(): void
    {
        $response = $this->postJson('/api/contacto', [
            'nombre'   => 'Carlos Mendoza',
            'email'    => 'carlos@gmail.com',
            'telefono' => '7441234567',
            'asunto'   => 'Consulta general',
            'mensaje'  => 'Hola', // Menos de 10 caracteres
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors(['mensaje']);
    }

    /**
     * Test 7: Rate Limiting throttle:3,1 bloquea al 4to intento en un minuto.
     */
    public function test_rate_limiting_bloquea_despues_de_tres_peticiones(): void
    {
        $payload = [
            'nombre'   => 'Cliente Frecuente',
            'email'    => 'cliente@gmail.com',
            'telefono' => '7441112233',
            'asunto'   => 'Prueba de saturación',
            'mensaje'  => 'Mensaje de prueba número para verificar rate limiter.',
        ];

        // Peticiones 1, 2 y 3 deben pasar (HTTP 200)
        $this->postJson('/api/contacto', $payload)->assertStatus(200);
        $this->postJson('/api/contacto', $payload)->assertStatus(200);
        $this->postJson('/api/contacto', $payload)->assertStatus(200);

        // Petición 4 debe ser bloqueada por el middleware throttle:3,1 (HTTP 429)
        $response = $this->postJson('/api/contacto', $payload);
        $response->assertStatus(429);
    }

    /**
     * Test 8: El alias '/api/contact-messages' funciona de manera idéntica.
     */
    public function test_alias_contact_messages_funciona_identico(): void
    {
        $payload = [
            'nombre'   => 'Lucía Torres',
            'email'    => 'lucia@gmail.com',
            'telefono' => '7449988776',
            'asunto'   => 'Pregunta sobre menú vegano',
            'mensaje'  => 'Quisiera saber si cuentan con opciones libres de gluten y veganas.',
        ];

        $response = $this->postJson('/api/contact-messages', $payload);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '¡Mensaje enviado con éxito! Nos pondremos en contacto contigo pronto.',
                'data'    => [
                    'nombre' => 'Lucía Torres',
                ],
            ]);
    }

    /**
     * Test 9: Permite email nulo o vacío, pero teléfono es obligatorio.
     */
    public function test_permite_correo_nulo_pero_telefono_es_obligatorio(): void
    {
        // Con email nulo debe ser exitoso (HTTP 200)
        $response = $this->postJson('/api/contacto', [
            'nombre'   => 'Roberto Gómez',
            'email'    => null,
            'telefono' => '7445556677',
            'asunto'   => 'Duda sobre reservación',
            'mensaje'  => 'Quisiera saber si puedo reservar una mesa junto a la ventana.',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => '¡Mensaje enviado con éxito! Nos pondremos en contacto contigo pronto.',
                'data'    => [
                    'nombre'   => 'Roberto Gómez',
                    'email'    => null,
                    'telefono' => '7445556677',
                ],
            ]);

        // Sin teléfono debe ser rechazado (HTTP 422)
        $responseSinTel = $this->postJson('/api/contacto', [
            'nombre'   => 'Roberto Gómez',
            'email'    => 'roberto@gmail.com',
            'telefono' => null,
            'asunto'   => 'Duda sobre reservación',
            'mensaje'  => 'Quisiera saber si puedo reservar una mesa junto a la ventana.',
        ]);

        $responseSinTel->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);
    }

    /**
     * Test 10: Rechaza asunto menor a 4 caracteres (min:4).
     */
    public function test_rechaza_asunto_menor_a_cuatro_caracteres(): void
    {
        $response = $this->postJson('/api/contacto', [
            'nombre'   => 'Roberto Gómez',
            'email'    => 'roberto@gmail.com',
            'telefono' => '7445556677',
            'asunto'   => 'Ola', // 3 caracteres, falla min:4
            'mensaje'  => 'Quisiera saber si puedo reservar una mesa junto a la ventana.',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['asunto']);
    }
}

