<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\ReviewImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReviewCaptureTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test 1: Creación exitosa de reseña con auto-publicación (is_approved = true por defecto).
     */
    public function test_creates_review_with_is_approved_true_and_auto_publishes(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $payload = [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'rating'     => 5,
            'comentario' => 'La cena fue espectacular, excelente servicio.',
        ];

        $response = $this->postJson('/api/reviews', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'nombre',
                    'rating',
                    'comentario',
                    'fotos',
                    'is_approved',
                ],
            ]);

        // Verificamos que se guardó en PostgreSQL con los datos de contacto
        $this->assertDatabaseHas('reviews', [
            'nombre'      => 'Carlos Gomez',
            'telefono'    => '7441112233',
            'correo'      => 'carlos@example.com',
            'rating'      => 5,
            'comentario'  => 'La cena fue espectacular, excelente servicio.',
            'is_approved' => true,
        ]);

        $review = Review::where('nombre', 'Carlos Gomez')->first();
        $this->assertNotNull($review);
        $this->assertTrue($review->is_approved);
    }

    /**
     * Test 2: Auto-publicación controlada por el backend (ignora intentos de forzar false o valores manipulados).
     */
    public function test_cannot_force_is_approved_from_request_payload(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $payload = [
            'nombre'      => 'Hacker Intent',
            'telefono'    => '7449998877',
            'correo'      => 'hacker@example.com',
            'rating'      => 5,
            'comentario'  => 'Intento de manipulación de estado',
            'is_approved' => false,
        ];

        $response = $this->postJson('/api/reviews', $payload);
        $response->assertStatus(201);

        $this->assertDatabaseHas('reviews', [
            'nombre'      => 'Hacker Intent',
            'is_approved' => true,
        ]);
    }

    /**
     * Test 3: Validación Zero-Trust de 'nombre' (bloquea símbolos, números, inyecciones y URLs).
     */
    public function test_nombre_validation_rejects_symbols_numbers_and_urls(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = [
            'telefono'   => '7441112233',
            'correo'     => 'test@example.com',
            'rating'     => 5,
            'comentario' => 'Buen servicio',
        ];

        // Con números
        $resNumbers = $this->postJson('/api/reviews', array_merge($base, ['nombre' => 'Carlos 123']));
        $resNumbers->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Con símbolos
        $resSymbols = $this->postJson('/api/reviews', array_merge($base, ['nombre' => 'Carlos @#$ Gomez']));
        $resSymbols->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Con inyección de HTML / scripts
        $resHtml = $this->postJson('/api/reviews', array_merge($base, ['nombre' => '<script>alert(1)</script>']));
        $resHtml->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);

        // Con URLs
        $resUrl = $this->postJson('/api/reviews', array_merge($base, ['nombre' => 'https://spam.com']));
        $resUrl->assertStatus(422)
            ->assertJsonValidationErrors(['nombre']);
    }

    /**
     * Test 4: Validación de 'rating' (solo acepta enteros del 1 al 5, bloquea inyecciones falsas).
     */
    public function test_rating_validation_rejects_invalid_numbers(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'comentario' => 'Calificación falsa',
        ];

        // Rating mayor a 5 (ej. 100)
        $res100 = $this->postJson('/api/reviews', array_merge($base, ['rating' => 100]));
        $res100->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating 0
        $resZero = $this->postJson('/api/reviews', array_merge($base, ['rating' => 0]));
        $resZero->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating negativo
        $resNegative = $this->postJson('/api/reviews', array_merge($base, ['rating' => -1]));
        $resNegative->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);

        // Rating no numérico
        $resString = $this->postJson('/api/reviews', array_merge($base, ['rating' => 'excelente']));
        $resString->assertStatus(422)
            ->assertJsonValidationErrors(['rating']);
    }

    /**
     * Test 5: Validación de 'comentario' (máximo 1000 caracteres y strip_tags).
     */
    public function test_comentario_sanitizes_html_and_rejects_over_1000_characters(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = [
            'nombre'   => 'Carlos Gomez',
            'telefono' => '7441112233',
            'correo'   => 'carlos@example.com',
            'rating'   => 5,
        ];

        // Más de 1000 caracteres
        $resTooLong = $this->postJson('/api/reviews', array_merge($base, [
            'comentario' => str_repeat('A', 1001),
        ]));
        $resTooLong->assertStatus(422)
            ->assertJsonValidationErrors(['comentario']);

        // Comentario de hasta 1000 caracteres es válido
        $resValid = $this->postJson('/api/reviews', array_merge($base, [
            'comentario' => str_repeat('A', 1000),
        ]));
        $resValid->assertStatus(201);

        // Comentario con HTML es sanitizado y guardado limpio
        $resHtmlClean = $this->postJson('/api/reviews', array_merge($base, [
            'comentario' => '<b>Excelente platillo</b> y <a href="http://spam.com">gran</a> atmósfera.',
        ]));
        $resHtmlClean->assertStatus(201);
        $this->assertDatabaseHas('reviews', [
            'nombre'     => 'Carlos Gomez',
            'comentario' => 'Excelente platillo y gran atmósfera.',
        ]);

        // Comentario que solo contiene script malicioso queda vacío y es rechazado
        $resOnlyScript = $this->postJson('/api/reviews', array_merge($base, [
            'comentario' => '<script>alert("hack")</script>',
        ]));
        $resOnlyScript->assertStatus(422)
            ->assertJsonValidationErrors(['comentario']);
    }

    /**
     * Test 6: Subida segura de fotografías con UUID y almacenamiento en arreglo JSON de rutas.
     */
    public function test_file_upload_stores_with_uuid_and_saves_fotos_json_array(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');

        $photo1 = UploadedFile::fake()->image('mi_foto_original_con_inyeccion_1.jpg', 600, 600)->size(500);
        $photo2 = UploadedFile::fake()->image('foto_2.png', 400, 400)->size(800);

        $response = $this->post('/api/reviews', [
            'nombre'     => 'Lucia Mendez',
            'telefono'   => '7442223344',
            'correo'     => 'lucia@example.com',
            'rating'     => 5,
            'comentario' => 'Fotografías del postre y la mesa.',
            'fotos'      => [$photo1, $photo2],
        ]);

        $response->assertStatus(201);

        $review = Review::where('nombre', 'Lucia Mendez')->first();
        $this->assertNotNull($review);
        $this->assertTrue($review->is_approved);

        // Verificamos el casteo y arreglo JSON de rutas de fotos
        $this->assertIsArray($review->fotos);
        $this->assertCount(2, $review->fotos);

        foreach ($review->fotos as $fotoUrl) {
            $this->assertStringContainsString('/storage/reviews/', $fotoUrl);
            $this->assertStringStartsWith('http', $fotoUrl);

            // Nunca debe guardarse con el nombre original del cliente
            $this->assertStringNotContainsString('mi_foto_original', $fotoUrl);

            // Debe guardarse con formato UUID
            $this->assertMatchesRegularExpression('/\/storage\/reviews\/[0-9a-f\-]{36}\.[a-z]+$/', $fotoUrl);

            // Debe existir en el disco 'public'
            $storedPath = ltrim(parse_url($fotoUrl, PHP_URL_PATH), '/');
            $storedPath = str_replace('storage/', '', $storedPath);
            Storage::disk('public')->assertExists($storedPath);
        }
    }

    /**
     * Test 7: Bloqueo de subida de más de 3 fotografías (max:3).
     */
    public function test_file_upload_rejects_more_than_three_photos(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');

        $photos = [
            UploadedFile::fake()->image('f1.jpg'),
            UploadedFile::fake()->image('f2.jpg'),
            UploadedFile::fake()->image('f3.jpg'),
            UploadedFile::fake()->image('f4.jpg'),
        ];

        $response = $this->postJson('/api/reviews', [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'rating'     => 5,
            'comentario' => 'Intento de subir 4 fotos',
            'fotos'      => $photos,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fotos']);
    }

    /**
     * Test 8: Bloqueo de fotografías que exceden los 10MB (max:10240).
     */
    public function test_file_upload_rejects_files_larger_than_5mb(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');

        // Archivo de 12000 KB (mayor a 10240 KB / 10MB)
        $largePhoto = UploadedFile::fake()->image('pesada.jpg')->size(12000);

        $response = $this->postJson('/api/reviews', [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'rating'     => 5,
            'comentario' => 'Foto pesada',
            'fotos'      => [$largePhoto],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fotos.0']);

        // Archivo de 3500 KB (menor a 5120 KB / 5MB) es aceptado
        $validPhoto = UploadedFile::fake()->image('valida_3mb.jpg')->size(3500);

        $resValid = $this->post('/api/reviews', [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'rating'     => 5,
            'comentario' => 'Foto de 3.5MB',
            'fotos'      => [$validPhoto],
        ]);

        $resValid->assertStatus(201);
    }

    /**
     * Test 9: Bloqueo de scripts maliciosos camuflados o archivos no permitidos (ej. .php, .exe).
     */
    public function test_file_upload_rejects_malicious_non_image_files(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');

        $malicious = UploadedFile::fake()->create('malicious.php', 100, 'application/x-php');

        $response = $this->postJson('/api/reviews', [
            'nombre'     => 'Hacker',
            'telefono'   => '7441112233',
            'correo'     => 'hacker@example.com',
            'rating'     => 1,
            'comentario' => 'Intento de exploit',
            'fotos'      => [$malicious],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fotos.0']);
    }

    /**
     * Test 10: Bloqueo de formatos de imagen no autorizados (solo acepta jpeg, png, webp).
     */
    public function test_file_upload_rejects_unauthorized_mimes(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Storage::fake('public');

        $gifPhoto = UploadedFile::fake()->image('animacion.gif');

        $response = $this->postJson('/api/reviews', [
            'nombre'     => 'Carlos Gomez',
            'telefono'   => '7441112233',
            'correo'     => 'carlos@example.com',
            'rating'     => 5,
            'comentario' => 'GIF no permitido',
            'fotos'      => [$gifPhoto],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['fotos.0']);
    }

    /**
     * Test 11: Rate Limiting estricto (throttle:2,1 bloquea al 3er intento en 1 minuto).
     */
    public function test_rate_limiting_blocks_after_two_requests_per_minute(): void
    {
        $payload1 = [
            'nombre'     => 'Cliente Uno',
            'telefono'   => '7441112233',
            'correo'     => 'uno@example.com',
            'rating'     => 5,
            'comentario' => 'Primera reseña válida',
        ];
        $res1 = $this->postJson('/api/reviews', $payload1);
        $res1->assertStatus(201);

        $payload2 = [
            'nombre'     => 'Cliente Dos',
            'telefono'   => '7442223344',
            'correo'     => 'dos@example.com',
            'rating'     => 4,
            'comentario' => 'Segunda reseña válida',
        ];
        $res2 = $this->postJson('/api/reviews', $payload2);
        $res2->assertStatus(201);

        // 3er intento en el mismo minuto desde la misma IP
        $payload3 = [
            'nombre'     => 'Review Bomber',
            'telefono'   => '7443334455',
            'correo'     => 'tres@example.com',
            'rating'     => 1,
            'comentario' => 'Tercer intento que debe ser asfixiado por rate limit',
        ];
        $res3 = $this->postJson('/api/reviews', $payload3);
        $res3->assertStatus(429); // Too Many Requests
    }

    /**
     * Test 12: Borrado lógico (SoftDeletes) en el modelo Review oculta sin destruir datos en PostgreSQL.
     */
    public function test_review_model_soft_deletes_without_destroying_database_evidence(): void
    {
        $review = Review::create([
            'nombre'      => 'Maria Solis',
            'telefono'    => '7445556677',
            'correo'      => 'maria@example.com',
            'rating'      => 4,
            'comentario'  => 'Buena comida pero demoró la cuenta.',
            'fotos'       => ['/storage/reviews/foto1.jpg'],
            'is_approved' => true,
        ]);

        $this->assertNull($review->deleted_at);

        // Ejecutar eliminación
        $review->delete();

        // El registro está marcado como borrado lógico
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);

        // Las consultas estándar no devuelven la reseña eliminada
        $this->assertNull(Review::find($review->id));
        $this->assertEquals(0, Review::where('nombre', 'Maria Solis')->count());

        // Con withTrashed sigue existiendo intacta como evidencia
        $trashed = Review::withTrashed()->find($review->id);
        $this->assertNotNull($trashed);
        $this->assertNotNull($trashed->deleted_at);
        $this->assertEquals('Maria Solis', $trashed->nombre);
    }

    /**
     * Test 13: Admin panel - GET /api/admin/reviews con paginación y filtro por nombre.
     */
    public function test_admin_can_list_reviews_with_pagination_and_search_by_nombre(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        Review::create([
            'nombre'      => 'Alejandro Sanz',
            'telefono'    => '7441112233',
            'correo'      => 'sanz@example.com',
            'rating'      => 5,
            'comentario'  => 'Impresionante experiencia culinaria.',
            'is_approved' => true,
        ]);

        Review::create([
            'nombre'      => 'Beatriz Luengo',
            'telefono'    => '7442223344',
            'correo'      => 'beatriz@example.com',
            'rating'      => 4,
            'comentario'  => 'Muy buen ambiente.',
            'is_approved' => true,
        ]);

        Review::create([
            'nombre'      => 'Carlos Baute',
            'telefono'    => '7443334455',
            'correo'      => 'baute@example.com',
            'rating'      => 5,
            'comentario'  => 'Excelente marisco.',
            'is_approved' => true,
        ]);

        // Consulta sin filtro: debe listar las 3 con paginación
        $responseAll = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reviews?per_page=2');
        $responseAll->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'current_page',
                'last_page',
                'per_page',
                'total',
            ]);
        $this->assertEquals(3, $responseAll->json('total'));
        $this->assertCount(2, $responseAll->json('data'));

        // Consulta filtrada por nombre
        $responseSearch = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reviews?nombre=Alejandro');
        $responseSearch->assertStatus(200);
        $this->assertEquals(1, $responseSearch->json('total'));
        $this->assertEquals('Alejandro Sanz', $responseSearch->json('data.0.nombre'));
        $this->assertEquals('7441112233', $responseSearch->json('data.0.telefono'));
        $this->assertEquals('sanz@example.com', $responseSearch->json('data.0.correo'));
    }

    /**
     * Test 14: Admin panel - DELETE /api/admin/reviews/{id} aplica borrado lógico.
     */
    public function test_admin_can_soft_delete_review(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $review = Review::create([
            'nombre'      => 'Spammer Peligroso',
            'telefono'    => '7449990000',
            'correo'      => 'spam@example.com',
            'rating'      => 1,
            'comentario'  => 'Comentario con difamación o inapropiado.',
            'is_approved' => true,
        ]);

        $response = $this->actingAs($admin, 'sanctum')->deleteJson("/api/admin/reviews/{$review->id}");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Reseña eliminada correctamente (borrado lógico aplicado).',
                'id'      => $review->id,
            ]);

        // Registro eliminado lógicamente
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);

        // GET /api/admin/reviews ya no debe incluirla
        $listResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reviews');
        $this->assertEquals(0, $listResponse->json('total'));
    }

    /**
     * Test 15: Acceso no autorizado o sin rol es bloqueado en las rutas de administración.
     */
    public function test_unauthorized_access_to_admin_review_routes_is_blocked(): void
    {
        $review = Review::create([
            'nombre'      => 'Cliente Real',
            'telefono'    => '7448889900',
            'correo'      => 'real@example.com',
            'rating'      => 5,
            'comentario'  => 'Excelente servicio',
            'is_approved' => true,
        ]);

        // Huésped no autenticado -> 401
        $guestRes = $this->getJson('/api/admin/reviews');
        $guestRes->assertStatus(401);

        $guestDel = $this->deleteJson("/api/admin/reviews/{$review->id}");
        $guestDel->assertStatus(401);

        // Usuario con rol sin privilegios de moderación (ej. repartidor) -> 403
        $driver = User::factory()->create(['role' => 'repartidor']);
        $driverDel = $this->actingAs($driver, 'sanctum')->deleteJson("/api/admin/reviews/{$review->id}");
        $driverDel->assertStatus(403);
    }

    /**
     * Test 16: Doble blindaje en 'telefono' (requerido, string, min:10, max:15, regex:/^[0-9+]+$/).
     */
    public function test_telefono_validation_enforces_required_format_min_10_and_max_15(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = [
            'nombre'     => 'Cliente Test',
            'correo'     => 'cliente@example.com',
            'rating'     => 5,
            'comentario' => 'Excelente experiencia',
        ];

        // Falta teléfono
        $resMissing = $this->postJson('/api/reviews', $base);
        $resMissing->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        // Teléfono demasiado corto (< 10 caracteres)
        $resTooShort = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '74411122',
        ]));
        $resTooShort->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        // Teléfono demasiado largo (> 15 caracteres)
        $resTooLong = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => str_repeat('1', 16),
        ]));
        $resTooLong->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        // Rechaza cualquier letra (ej. "eee" o "74411122ab")
        $resWithEee = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '74411122eee',
        ]));
        $resWithEee->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        $resWithLetters = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '74411122ab',
        ]));
        $resWithLetters->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        // Rechaza inyección de scripts
        $resWithScript = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '<script>1234567890</script>',
        ]));
        $resWithScript->assertStatus(422)
            ->assertJsonValidationErrors(['telefono']);

        // Formatos válidos: solo números y signo +, entre 10 y 15 caracteres
        $resValid1 = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '+527441234567',
        ]));
        $resValid1->assertStatus(201);

        $resValid2 = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '7441112233',
        ]));
        $resValid2->assertStatus(201);

        $resValid3 = $this->postJson('/api/reviews', array_merge($base, [
            'telefono' => '+12345678901234',
        ]));
        $resValid3->assertStatus(201);
    }

    /**
     * Test 17: Validación de 'correo' (requerido, email válido, max:255).
     */
    public function test_correo_validation_enforces_required_valid_email_and_max_255(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $base = [
            'nombre'     => 'Cliente Test',
            'telefono'   => '7441112233',
            'rating'     => 5,
            'comentario' => 'Excelente comida',
        ];

        // Falta correo
        $resMissing = $this->postJson('/api/reviews', $base);
        $resMissing->assertStatus(422)
            ->assertJsonValidationErrors(['correo']);

        // Correo con formato inválido
        $resInvalid = $this->postJson('/api/reviews', array_merge($base, [
            'correo' => 'correo-invalido-sin-arroba',
        ]));
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['correo']);

        // Correo demasiado largo (> 255 caracteres)
        $resTooLong = $this->postJson('/api/reviews', array_merge($base, [
            'correo' => str_repeat('a', 250) . '@example.com',
        ]));
        $resTooLong->assertStatus(422)
            ->assertJsonValidationErrors(['correo']);

        // Correo válido
        $resValid = $this->postJson('/api/reviews', array_merge($base, [
            'correo' => 'gourmet.cliente@dominio.com',
        ]));
        $resValid->assertStatus(201);
    }

    /**
     * Test 18: Seguridad Crítica: Protección contra fuga de datos personales (hidden en endpoints públicos).
     */
    public function test_public_endpoint_and_model_serialization_hides_telefono_and_correo(): void
    {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $review = Review::create([
            'nombre'      => 'Ana Maria Privada',
            'telefono'    => '7449991122',
            'correo'      => 'ana.privada@secreto.com',
            'rating'      => 5,
            'comentario'  => 'Servicio excepcional y muy discreto.',
            'is_approved' => true,
        ]);

        // 1. Verificación a nivel de serialización del modelo Eloquent
        $arrayData = $review->toArray();
        $this->assertArrayNotHasKey('telefono', $arrayData);
        $this->assertArrayNotHasKey('correo', $arrayData);

        $jsonData = $review->toJson();
        $this->assertStringNotContainsString('7449991122', $jsonData);
        $this->assertStringNotContainsString('ana.privada@secreto.com', $jsonData);

        // 2. Verificación en el endpoint público GET /api/reviews
        $publicResponse = $this->getJson('/api/reviews');
        $publicResponse->assertStatus(200);

        $publicContent = $publicResponse->getContent();
        $this->assertStringNotContainsString('7449991122', $publicContent);
        $this->assertStringNotContainsString('ana.privada@secreto.com', $publicContent);

        // 3. Verificación en el endpoint público POST /api/reviews
        $postResponse = $this->postJson('/api/reviews', [
            'nombre'     => 'Cliente Anonimo',
            'telefono'   => '7448881234',
            'correo'     => 'anonimo@empresa.com',
            'rating'     => 5,
            'comentario' => 'Todo perfecto',
        ]);
        $postResponse->assertStatus(201);
        $postContent = $postResponse->getContent();
        $this->assertStringNotContainsString('7448881234', $postContent);
        $this->assertStringNotContainsString('anonimo@empresa.com', $postContent);

        // 4. Verificación de que en el Panel Administrativo (GET /api/admin/reviews) SÍ son visibles
        $admin = User::factory()->create(['role' => 'admin']);
        $adminResponse = $this->actingAs($admin, 'sanctum')->getJson('/api/admin/reviews');
        $adminResponse->assertStatus(200);
        $adminContent = $adminResponse->getContent();
        $this->assertStringContainsString('7449991122', $adminContent);
        $this->assertStringContainsString('ana.privada@secreto.com', $adminContent);
    }

    /**
     * Test 19: Compatibilidad y blindaje en endpoint /api/testimonials (StoreTestimonialRequest).
     */
    public function test_post_testimonials_endpoint_works_with_zero_trust_and_fotos_array(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $foto1 = UploadedFile::fake()->image('food1.jpg', 800, 600)->size(1500);
        $foto2 = UploadedFile::fake()->image('food2.png', 800, 600)->size(2000);

        $payload = [
            'nombre'     => 'Fernanda Ortiz',
            'telefono'   => '+527441234567',
            'correo'     => 'fernanda.ortiz@test.com',
            'rating'     => 5,
            'comentario' => 'Increíble experiencia culinaria en el restaurante.',
            'fotos'      => [$foto1, $foto2],
        ];

        $response = $this->postJson('/api/testimonials', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data',
                'review',
                'status',
            ]);

        // Verificamos en DB que se guardó en reviews
        $this->assertDatabaseHas('reviews', [
            'nombre'      => 'Fernanda Ortiz',
            'telefono'    => '+527441234567',
            'correo'      => 'fernanda.ortiz@test.com',
            'rating'      => 5,
            'comentario'  => 'Increíble experiencia culinaria en el restaurante.',
            'is_approved' => true,
        ]);

        $review = Review::where('nombre', 'Fernanda Ortiz')->first();
        $this->assertNotNull($review);
        $this->assertCount(2, $review->fotos);

        foreach ($review->fotos as $fotoUrl) {
            $rawPath = ltrim(parse_url($fotoUrl, PHP_URL_PATH), '/');
            $rawPath = str_replace('storage/', '', $rawPath);
            Storage::disk('public')->assertExists($rawPath);
        }
    }

    /**
     * Test 20: Envío exacto de 3 fotos con sincronización en PostgreSQL sin violaciones de check constraint.
     */
    public function test_post_testimonials_with_3_photos_persists_in_both_tables(): void
    {
        Storage::fake('public');
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $foto1 = UploadedFile::fake()->image('p1.jpg', 800, 600)->size(1000);
        $foto2 = UploadedFile::fake()->image('p2.webp', 800, 600)->size(1200);
        $foto3 = UploadedFile::fake()->image('p3.png', 800, 600)->size(1400);

        $payload = [
            'nombre'     => 'Francisco perez fuentes',
            'telefono'   => '7421060145',
            'correo'     => 'fp0553453@gmail.com',
            'rating'     => 5,
            'comentario' => 'Excelente experiencia gastronómica.',
            'fotos'      => [$foto1, $foto2, $foto3],
        ];

        $response = $this->postJson('/api/testimonials', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Reseña creada exitosamente',
            ]);

        $this->assertDatabaseHas('reviews', [
            'nombre'      => 'Francisco perez fuentes',
            'telefono'    => '7421060145',
            'correo'      => 'fp0553453@gmail.com',
            'rating'      => 5,
            'comentario'  => 'Excelente experiencia gastronómica.',
            'is_approved' => true,
        ]);

        $this->assertDatabaseHas('testimonials', [
            'customer_name'  => 'Francisco perez fuentes',
            'customer_phone' => '7421060145',
            'customer_email' => 'fp0553453@gmail.com',
            'rating'         => 5,
            'comment'        => 'Excelente experiencia gastronómica.',
            'insignia'       => 'visita_restaurante',
            'status'         => 'aprobada',
        ]);
    }
}
