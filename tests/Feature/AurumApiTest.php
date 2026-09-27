<?php

use App\Models\User;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Extra;
use App\Models\Area;
use App\Models\RestaurantSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.turnstile.required' => false]);

    // Seed initial data for testing
    User::create([
        'name' => 'Administrador',
        'email' => 'admin@aurum.com',
        'password' => Hash::make('password'),
        'role' => 'admin',
    ]);

    RestaurantSetting::create([
        'id' => 1,
        'restaurant_name' => 'Aurum',
        'schedule' => [
            ['day' => 'Domingo', 'open' => true, 'active' => 1],
            ['day' => 'Lunes', 'open' => true, 'active' => 1],
            ['day' => 'Martes', 'open' => true, 'active' => 1],
            ['day' => 'Miércoles', 'open' => true, 'active' => 1],
            ['day' => 'Jueves', 'open' => true, 'active' => 1],
            ['day' => 'Viernes', 'open' => true, 'active' => 1],
            ['day' => 'Sábado', 'open' => true, 'active' => 1],
        ],
    ]);

    $area = Area::create([
        'name' => 'Terraza',
        'capacity' => 20,
        'active' => true,
    ]);

    $category = Category::create([
        'name' => 'Entradas Celestiales',
        'slug' => 'entradas-celestiales',
        'active' => true,
    ]);

    $dish = Dish::create([
        'category_id' => $category->id,
        'name' => 'Carpaccio de Wagyu Premium',
        'slug' => 'carpaccio-de-wagyu-premium',
        'description' => 'Finas láminas de Wagyu con trufa',
        'price' => 24.50,
        'is_available' => true,
    ]);

    $extra = Extra::create([
        'name' => 'Queso extra',
        'price' => 2.00,
        'active' => true,
    ]);

    $dish->extras()->attach($extra->id);
});

test('public endpoints return data successfully', function () {
    $this->getJson('/api/menu')->assertStatus(200);
    $this->getJson('/api/dishes')->assertStatus(200);
    $this->getJson('/api/areas')->assertStatus(200);
    $this->getJson('/api/settings')->assertStatus(200);
});

test('clients can create orders with items and extras', function () {
    $dish = Dish::first();
    $extra = Extra::first();

    $orderData = [
        'customer_name' => 'Cliente de Prueba',
        'customer_phone' => '123456789',
        'customer_address' => 'Calle Falsa 123',
        'calle' => 'Calle Falsa',
        'num_ext' => '123',
        'colonia' => 'Centro',
        'cp' => '39300',
        'referencias' => 'Frente al parque',
        'modality' => 'delivery',
        'payment_method' => 'cash',
        'items' => [
            [
                'dish_id' => $dish->id,
                'quantity' => 2,
                'extras' => [$extra->id],
                'notes' => 'Bien sazonado'
            ]
        ]
    ];

    // Total should be: (24.50 * 2) + (2.00 * 2) = 53.00
    $response = $this->postJson('/api/orders', $orderData);
    $response->assertStatus(201)
             ->assertJsonPath('total_amount', 53)
             ->assertJsonStructure([
                 'id', 'customer_name', 'total_amount', 'items' => [
                     '*' => ['dish_id', 'quantity', 'price', 'extras']
                 ]
             ]);
});

test('clients can make reservations', function () {
    $area = Area::first();

    $resData = [
        'customer_name' => 'Carlos Cliente',
        'customer_email' => 'carlos@cliente.com',
        'customer_phone' => '7441112222',
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '20:00',
        'guests_count' => 4,
        'area_id' => $area->id,
    ];

    $response = $this->postJson('/api/reservations', $resData);
    $response->assertStatus(201)
             ->assertJsonPath('customer_name', 'Carlos Cliente')
             ->assertJsonPath('status', 'pending');
});

test('admin dashboard and admin resources require authentication', function () {
    $this->getJson('/api/dashboard')->assertStatus(401);
    $this->getJson('/api/admin/orders')->assertStatus(401);
});

test('admin can log in, receive token, and access protected resources', function () {
    // 1. Login
    $loginResponse = $this->postJson('/api/login', [
        'email' => 'admin@aurum.com',
        'password' => 'password',
    ]);

    $loginResponse->assertStatus(200)
                 ->assertJsonStructure(['token', 'user']);

    $token = $loginResponse->json('token');

    // 2. Access dashboard
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/dashboard');
    $response->assertStatus(200)
             ->assertJsonStructure([
                 'total_sales', 'active_orders', 'dishes_count', 'upcoming_reservations'
             ]);

    // 3. Access kitchen orders
    $response2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/kitchen/orders');
    $response2->assertStatus(200);

    // 4. Access admin list resources
    $this->withHeaders(['Authorization' => "Bearer {$token}"])
         ->getJson('/api/admin/categories')->assertStatus(200);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
         ->getJson('/api/admin/dishes')->assertStatus(200);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
         ->getJson('/api/admin/extras')->assertStatus(200);

    $this->withHeaders(['Authorization' => "Bearer {$token}"])
         ->getJson('/api/admin/areas')->assertStatus(200);
});

test('admin can upload and delete images', function () {
    // 1. Fake the storage disk (by default, Storage::fake() fakes the default local disk)
    Storage::fake('local');
    Storage::fake('public');

    // 2. Login to get token
    $loginResponse = $this->postJson('/api/login', [
        'email' => 'admin@aurum.com',
        'password' => 'password',
    ]);
    $token = $loginResponse->json('token');

    // 3. Create a fake image upload
    $file = UploadedFile::fake()->image('dish_photo.jpg');

    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->postJson('/api/admin/images/upload', [
                         'image' => $file,
                         'folder' => 'dishes'
                     ]);

    $response->assertStatus(201)
             ->assertJsonStructure(['url', 'path']);

    $path = $response->json('path');
    $url = $response->json('url');

    // 4. Assert that the file is stored
    Storage::disk('public')->assertExists($path);

    // 5. Delete the uploaded image
    $deleteResponse = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                           ->deleteJson('/api/admin/images/delete', [
                               'path' => $url
                           ]);

    $deleteResponse->assertStatus(200)
                   ->assertJsonPath('message', 'Imagen eliminada correctamente');

    // 6. Assert that the file is deleted
    Storage::disk('public')->assertMissing($path);
});

test('reservations folio is generated automatically and status can be set optionally', function () {
    $area = Area::first();

    // 1. Create reservation without status or folio -> should generate RES-XXXX or RESYYYYMMDDXXXX and default status 'pending'
    $resData1 = [
        'customer_name' => 'Ana Gomez',
        'customer_email' => 'ana@example.com',
        'customer_phone' => '5551234567',
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '19:00',
        'guests_count' => 2,
        'area_id' => $area->id,
    ];

    $response1 = $this->postJson('/api/reservations', $resData1);
    $response1->assertStatus(201)
              ->assertJsonPath('customer_name', 'Ana Gomez')
              ->assertJsonPath('status', 'pending')
              ->assertJsonStructure(['folio']);

    $folio1 = $response1->json('folio');
    expect($folio1)->toMatch('/^RES-?(\d{8}-?)?\d{4}$/');

    // 2. Create reservation with explicit status 'confirmed'
    $resData2 = [
        'customer_name' => 'Luis Martinez',
        'customer_email' => 'luis@example.com',
        'customer_phone' => '5557654321',
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '21:00',
        'guests_count' => 3,
        'area_id' => $area->id,
        'status' => 'confirmed',
    ];

    $response2 = $this->postJson('/api/reservations', $resData2);
    $response2->assertStatus(201)
              ->assertJsonPath('customer_name', 'Luis Martinez')
              ->assertJsonPath('status', 'confirmed');
});

test('admin can search and filter reservations', function () {
    $area = Area::first();

    // Login as admin
    $loginResponse = $this->postJson('/api/login', [
        'email' => 'admin@aurum.com',
        'password' => 'password',
    ]);
    $token = $loginResponse->json('token');

    // Create a few reservations for searching/filtering
    $res1 = \App\Models\Reservation::create([
        'customer_name' => 'UniqueName SearchTest',
        'customer_email' => 'search1@example.com',
        'customer_phone' => '9991112222',
        'reservation_date' => '2026-07-20',
        'reservation_time' => '18:00',
        'guests_count' => 2,
        'area_id' => $area->id,
        'status' => 'pending',
    ]);

    $res2 = \App\Models\Reservation::create([
        'customer_name' => 'Another Customer',
        'customer_email' => 'search2@example.com',
        'customer_phone' => '9993334444',
        'reservation_date' => '2026-07-21',
        'reservation_time' => '19:00',
        'guests_count' => 4,
        'area_id' => $area->id,
        'status' => 'confirmed',
    ]);

    // 1. Search by name
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/admin/reservations?search=UniqueName');
    $response->assertStatus(200);
    $resList1 = $response->json('reservaciones') ?? $response->json();
    expect(count($resList1))->toBe(1);
    expect($resList1[0]['customer_name'])->toBe('UniqueName SearchTest');

    // 2. Search by phone
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/admin/reservations?search=9993334444');
    $response->assertStatus(200);
    $resList2 = $response->json('reservaciones') ?? $response->json();
    expect(count($resList2))->toBe(1);
    expect($resList2[0]['customer_name'])->toBe('Another Customer');

    // 3. Search by folio
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/admin/reservations?search=' . $res1->folio);
    $response->assertStatus(200);
    $resList3 = $response->json('reservaciones') ?? $response->json();
    expect(count($resList3))->toBe(1);
    expect($resList3[0]['folio'])->toBe($res1->folio);

    // 4. Filter by date
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->getJson('/api/admin/reservations?date=2026-07-21');
    $response->assertStatus(200);
    $resList4 = $response->json('reservaciones') ?? $response->json();
    expect(count($resList4))->toBe(1);
    expect($resList4[0]['customer_name'])->toBe('Another Customer');
});

test('admin can update reservation status to no_show and completed', function () {
    $area = Area::first();

    // Login as admin
    $loginResponse = $this->postJson('/api/login', [
        'email' => 'admin@aurum.com',
        'password' => 'password',
    ]);
    $token = $loginResponse->json('token');

    $reservation = \App\Models\Reservation::create([
        'customer_name' => 'Status Test',
        'customer_email' => 'status@example.com',
        'customer_phone' => '5550000000',
        'reservation_date' => now()->addDay()->toDateString(),
        'reservation_time' => '18:00',
        'guests_count' => 2,
        'area_id' => $area->id,
        'status' => 'pending',
    ]);

    // 1. Update status to completed
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->patchJson("/api/admin/reservations/{$reservation->id}/status", [
                         'status' => 'completed',
                     ]);
    $response->assertStatus(200)
             ->assertJsonPath('status', 'completed');

    // 2. Update status to no_show
    $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
                     ->patchJson("/api/admin/reservations/{$reservation->id}/status", [
                         'status' => 'no_show',
                     ]);
    $response->assertStatus(200)
             ->assertJsonPath('status', 'no_show');
});
