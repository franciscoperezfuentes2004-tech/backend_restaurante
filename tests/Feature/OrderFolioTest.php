<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Mesa;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFolioTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $mesero;
    protected Dish $dish;
    protected Mesa $mesa;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-09-17 14:00:00'));

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->mesero = User::factory()->create(['role' => 'mesero']);

        $category = Category::create([
            'name'        => 'Tacos',
            'slug'        => 'tacos',
            'description' => 'Tacos mexicanos',
            'is_active'   => true,
        ]);

        $this->dish = Dish::create([
            'category_id' => $category->id,
            'name'        => 'Tacos de Pastor',
            'slug'        => 'tacos-de-pastor',
            'price'       => 85.00,
            'is_active'   => true,
        ]);

        $area = Area::create([
            'name'        => 'Terraza',
            'description' => 'Área al aire libre',
            'capacity'    => 50,
            'is_active'   => true,
        ]);

        $this->mesa = Mesa::create([
            'area_id'     => $area->id,
            'numero_mesa' => 12,
            'capacidad'   => 4,
            'is_active'   => true,
            'status'      => 'libre',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Test 1: El generador crea el primer folio del día con formato PEDYYYYMMDD0001 (sin guiones).
     */
    public function test_genera_primer_folio_pedido_con_formato_correcto(): void
    {
        $hoy = Carbon::now();
        $esperado = 'PED' . $hoy->format('Ymd') . '0001';

        $folio = Order::generarFolioPedido('PED');

        $this->assertEquals($esperado, $folio);
        $this->assertStringNotContainsString('-', $folio);
        $this->assertMatchesRegularExpression('/^PED\d{12}$/', $folio);
    }

    /**
     * Test 2: Incrementa correlativamente los folios del mismo día sin colisiones.
     */
    public function test_incrementa_correlativamente_folios_de_pedidos(): void
    {
        $hoy = Carbon::now();
        $prefijo = 'PED' . $hoy->format('Ymd');

        $pedido1 = Order::create([
            'customer_name'    => 'Cliente 1',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Mesa 1',
            'modality'         => 'local',
            'total_amount'     => 150.00,
        ]);

        $this->assertEquals($prefijo . '0001', $pedido1->folio);

        $pedido2 = Order::create([
            'customer_name'    => 'Cliente 2',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Mesa 2',
            'modality'         => 'pickup',
            'total_amount'     => 250.00,
        ]);

        $this->assertEquals($prefijo . '0002', $pedido2->folio);

        $pedido3 = Order::create([
            'customer_name'    => 'Cliente 3',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Mesa 3',
            'modality'         => 'local',
            'total_amount'     => 350.00,
        ]);

        $this->assertEquals($prefijo . '0003', $pedido3->folio);
    }

    /**
     * Test 3: El endpoint POST /api/orders genera folios PEDYYYYMMDDXXXX para pedidos de salón o pickup.
     */
    public function test_endpoint_orders_genera_folio_ped_compacto(): void
    {
        $payload = [
            'modality'       => 'local',
            'customer_name'  => 'Carlos Santana',
            'customer_phone' => '7441234567',
            'payment_method' => 'cash',
            'items'          => [
                [
                    'dish_id'  => $this->dish->id,
                    'quantity' => 2,
                ]
            ]
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/orders', $payload);

        $response->assertStatus(201);
        $folio = $response->json('order.folio') ?? $response->json('folio');
        $this->assertNotNull($folio);
        $this->assertStringStartsWith('PED20260917', $folio);
        $this->assertStringNotContainsString('-', $folio);
        $this->assertMatchesRegularExpression('/^PED\d{12}$/', $folio);
    }

    /**
     * Test 4: El endpoint POST /api/tables/open asigna folio PEDYYYYMMDDXXXX sin guiones.
     */
    public function test_endpoint_tables_open_asigna_folio_ped_sin_guiones(): void
    {
        $payload = [
            'table_id'      => $this->mesa->id,
            'customer_name' => 'Familia López',
            'items'         => [
                [
                    'dish_id'  => $this->dish->id,
                    'quantity' => 1,
                ]
            ]
        ];

        $response = $this->actingAs($this->mesero)->postJson('/api/tables/open', $payload);

        $response->assertStatus(201);
        $folio = $response->json('order.folio');
        $this->assertNotNull($folio);
        $this->assertStringStartsWith('PED20260917', $folio);
        $this->assertStringNotContainsString('-', $folio);
        $this->assertMatchesRegularExpression('/^PED\d{12}$/', $folio);
    }
}
