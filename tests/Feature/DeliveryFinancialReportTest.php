<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryFinancialReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingresos_delivery_aggregates_delivered_orders_by_repartidor(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $driver1 = DeliveryDriver::create([
            'name'          => 'Carlos Méndez',
            'phone'         => '7441112233',
            'email'         => 'carlos@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'moto',
            'license_plate' => 'GRO-1234',
        ]);

        $driver2 = DeliveryDriver::create([
            'name'          => 'Laura Gómez',
            'phone'         => '7442223344',
            'email'         => 'laura@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'moto',
            'license_plate' => 'GRO-5678',
        ]);

        // Pedido 1 Entregado - Carlos ($150)
        $order1 = Order::create([
            'folio'            => 'DEL202609160001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 150.00,
            'customer_name'    => 'Cliente 1',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 1',
        ]);
        $order1->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'delivered',
        ]);

        // Pedido 2 Entregado - Carlos ($250)
        $order2 = Order::create([
            'folio'            => 'DEL202609160002',
            'modality'         => 'delivery',
            'status'           => 'completed',
            'total_amount'     => 250.00,
            'customer_name'    => 'Cliente 2',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle 2',
        ]);
        $order2->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'delivered',
        ]);

        // Pedido 3 Cancelado - Carlos ($500) -> NO debe sumarse
        $order3 = Order::create([
            'folio'            => 'DEL202609160003',
            'modality'         => 'delivery',
            'status'           => 'cancelled',
            'total_amount'     => 500.00,
            'customer_name'    => 'Cliente 3',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Calle 3',
        ]);
        $order3->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'cancelled',
        ]);

        // Pedido 4 Entregado - Laura ($800)
        $order4 = Order::create([
            'folio'            => 'DEL202609160004',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 800.00,
            'customer_name'    => 'Cliente Laura',
            'customer_phone'   => '7445556677',
            'customer_address' => 'Calle Laura',
        ]);
        $order4->delivery->update([
            'driver_id' => $driver2->id,
            'status'    => 'delivered',
        ]);

        $response = $this->getJson('/api/reportes/ingresos-delivery?filtro=mes');

        $response->assertStatus(200)
                 ->assertJsonCount(2);

        $data = $response->json();

        // 1er lugar por ingresos: Laura ($800, 1 pedido)
        $this->assertEquals('Laura Gómez', $data[0]['repartidor']);
        $this->assertEquals(1, $data[0]['pedidos_realizados']);
        $this->assertEquals(800.00, $data[0]['ingresos_generados']);

        // 2do lugar por ingresos: Carlos ($400, 2 pedidos)
        $this->assertEquals('Carlos Méndez', $data[1]['repartidor']);
        $this->assertEquals(2, $data[1]['pedidos_realizados']);
        $this->assertEquals(400.00, $data[1]['ingresos_generados']);
    }

    public function test_ingresos_delivery_supports_filters_semana_and_3meses(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00'); // Miércoles

        $driver = DeliveryDriver::create([
            'name'          => 'Pedro Soto',
            'phone'         => '7449998877',
            'email'         => 'pedro@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'bici',
            'license_plate' => 'N/A',
        ]);

        // Pedido de esta semana (hace 2 días)
        $order1 = Order::create([
            'folio'            => 'DEL202609140001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 100.00,
            'customer_name'    => 'Cliente Semana',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 1',
        ]);
        $order1->delivery->update([
            'driver_id' => $driver->id,
            'status'    => 'delivered',
        ]);
        $order1->delivery->timestamps = false;
        $order1->delivery->created_at = Carbon::parse('2026-09-14 10:00:00');
        $order1->delivery->save();

        // Pedido de hace 2 meses (dentro de 3meses)
        $order2 = Order::create([
            'folio'            => 'DEL202607140001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 350.00,
            'customer_name'    => 'Cliente 2 Meses',
            'customer_phone'   => '7449998877',
            'customer_address' => 'Calle Vieja',
        ]);
        $order2->delivery->update([
            'driver_id' => $driver->id,
            'status'    => 'delivered',
        ]);
        $order2->delivery->timestamps = false;
        $order2->delivery->created_at = Carbon::parse('2026-07-14 10:00:00');
        $order2->delivery->save();

        // Filtro semana: solo incluye el de hace 2 días
        $resSemana = $this->getJson('/api/reports/ingresos-delivery?filtro=semana');
        $resSemana->assertStatus(200);
        $dataSemana = $resSemana->json();
        $this->assertCount(1, $dataSemana);
        $this->assertEquals('Pedro Soto', $dataSemana[0]['repartidor']);
        $this->assertEquals(100.00, $dataSemana[0]['ingresos_generados']);
        $this->assertEquals(1, $dataSemana[0]['pedidos_realizados']);

        // Filtro 3meses: incluye ambos pedidos sumados
        $res3Meses = $this->getJson('/api/reportes/ingresos-delivery?filtro=3meses');
        $res3Meses->assertStatus(200);
        $data3Meses = $res3Meses->json();
        $this->assertCount(1, $data3Meses);
        $this->assertEquals(450.00, $data3Meses[0]['ingresos_generados']);
        $this->assertEquals(2, $data3Meses[0]['pedidos_realizados']);
    }
}