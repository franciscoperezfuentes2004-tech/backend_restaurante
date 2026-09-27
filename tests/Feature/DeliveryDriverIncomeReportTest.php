<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryDriverIncomeReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_ingresos_por_repartidor_aggregates_delivered_orders_by_driver(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        // Repartidor 1: Carlos Méndez
        $driver1 = DeliveryDriver::create([
            'name'          => 'Carlos Méndez',
            'phone'         => '7441112233',
            'email'         => 'carlos@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'moto',
            'license_plate' => 'GRO-1234',
        ]);

        // Repartidor 2: Laura Gómez
        $driver2 = DeliveryDriver::create([
            'name'          => 'Laura Gómez',
            'phone'         => '7442223344',
            'email'         => 'laura@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'moto',
            'license_plate' => 'GRO-5678',
        ]);

        // Carlos - Pedido 1: $300 (Entregado)
        $order1 = Order::create([
            'folio'            => 'DEL202609160001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 300.00,
            'customer_name'    => 'Cliente 1',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 1',
        ]);
        $order1->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'delivered',
        ]);
        $order1->delivery->timestamps = false;
        $order1->delivery->created_at = Carbon::parse('2026-09-16 10:00:00');
        $order1->delivery->save();

        // Carlos - Pedido 2: $250 (Entregado)
        $order2 = Order::create([
            'folio'            => 'DEL202609160002',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 250.00,
            'customer_name'    => 'Cliente 2',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle 2',
        ]);
        $order2->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'delivered',
        ]);
        $order2->delivery->timestamps = false;
        $order2->delivery->created_at = Carbon::parse('2026-09-16 11:00:00');
        $order2->delivery->save();

        // Laura - Pedido 3: $800 (Entregado)
        $order3 = Order::create([
            'folio'            => 'DEL202609160003',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 800.00,
            'customer_name'    => 'Cliente 3',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Calle 3',
        ]);
        $order3->delivery->update([
            'driver_id' => $driver2->id,
            'status'    => 'delivered',
        ]);
        $order3->delivery->timestamps = false;
        $order3->delivery->created_at = Carbon::parse('2026-09-16 11:30:00');
        $order3->delivery->save();

        // Carlos - Pedido 4: $600 (Cancelado) -> NO debe sumarse
        $order4 = Order::create([
            'folio'            => 'DEL202609160004',
            'modality'         => 'delivery',
            'status'           => 'cancelled',
            'total_amount'     => 600.00,
            'customer_name'    => 'Cliente 4',
            'customer_phone'   => '7444445566',
            'customer_address' => 'Calle 4',
        ]);
        $order4->delivery->update([
            'driver_id' => $driver1->id,
            'status'    => 'cancelled',
        ]);
        $order4->delivery->timestamps = false;
        $order4->delivery->created_at = Carbon::parse('2026-09-16 09:00:00');
        $order4->delivery->save();

        $response = $this->getJson('/api/reportes/ingresos-por-repartidor?filtro=mes');

        $response->assertStatus(200)
                 ->assertJsonCount(2);

        $data = $response->json();

        // 1er lugar por ingresos: Laura ($800, 1 pedido)
        $this->assertEquals('Laura Gómez', $data[0]['repartidor']);
        $this->assertEquals($driver2->id, $data[0]['repartidor_id']);
        $this->assertEquals(1, $data[0]['pedidos_realizados']);
        $this->assertEquals(800.00, $data[0]['ingresos_generados']);

        // 2do lugar por ingresos: Carlos ($550, 2 pedidos)
        $this->assertEquals('Carlos Méndez', $data[1]['repartidor']);
        $this->assertEquals($driver1->id, $data[1]['repartidor_id']);
        $this->assertEquals(2, $data[1]['pedidos_realizados']);
        $this->assertEquals(550.00, $data[1]['ingresos_generados']);
    }

    public function test_ingresos_por_repartidor_filters_by_time_periods(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $driver = DeliveryDriver::create([
            'name'          => 'Pedro Soto',
            'phone'         => '7449998877',
            'email'         => 'pedro@restaurante.com',
            'status'        => 'disponible',
            'active'        => true,
            'vehicle_type'  => 'bici',
            'license_plate' => 'N/A',
        ]);

        // Pedido de esta semana
        $orderSemana = Order::create([
            'folio'            => 'DEL202609150001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 120.00,
            'customer_name'    => 'Cliente Semana',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Av Costera 100',
        ]);
        $orderSemana->delivery->update([
            'driver_id' => $driver->id,
            'status'    => 'delivered',
        ]);
        $orderSemana->delivery->timestamps = false;
        $orderSemana->delivery->created_at = Carbon::parse('2026-09-15 14:00:00');
        $orderSemana->delivery->save();

        // Pedido de hace 2 meses (dentro de 3meses)
        $order2Meses = Order::create([
            'folio'            => 'DEL202607150001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 450.00,
            'customer_name'    => 'Cliente Pasado',
            'customer_phone'   => '7445556677',
            'customer_address' => 'Av Costera 500',
        ]);
        $order2Meses->delivery->update([
            'driver_id' => $driver->id,
            'status'    => 'delivered',
        ]);
        $order2Meses->delivery->timestamps = false;
        $order2Meses->delivery->created_at = Carbon::parse('2026-07-15 10:00:00');
        $order2Meses->delivery->save();

        // Filtro semana
        $resSemana = $this->getJson('/api/reports/ingresos-por-repartidor?filtro=semana');
        $resSemana->assertStatus(200);
        $dataSemana = $resSemana->json();
        $this->assertCount(1, $dataSemana);
        $this->assertEquals(120.00, $dataSemana[0]['ingresos_generados']);
        $this->assertEquals(1, $dataSemana[0]['pedidos_realizados']);

        // Filtro 3meses
        $res3Meses = $this->getJson('/api/reports/income-by-driver?filtro=3meses');
        $res3Meses->assertStatus(200);
        $data3Meses = $res3Meses->json();
        $this->assertCount(1, $data3Meses);
        $this->assertEquals(570.00, $data3Meses[0]['ingresos_generados']);
        $this->assertEquals(2, $data3Meses[0]['pedidos_realizados']);
    }
}