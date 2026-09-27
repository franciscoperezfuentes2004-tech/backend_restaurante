<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Delivery;
use App\Models\DeliveryDriver;
use App\Models\User;
use App\Models\CashCut;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryPerformanceMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metricas_rendimiento_calculates_correctly_with_filters(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        // 1. Order 1: Hoy, duration 20 minutes (entregado a etiempo,<=40)
        $order1 = Order::create([
            'folio'            => 'DET202609160001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 250.00,
            'customer_name'    => 'Cliente 1',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 1',
        ]);
        $order1->delivery->status = 'delivered';
        $order1->delivery->timestamps = false;
        $order1->delivery->created_at = Carbon::parse('2026-09-16 10:00:00');
        $order1->delivery->updated_at = Carbon::parse('2026-09-16 10:20:00'); // 20 mins
        $order1->delivery->save();

        // 2. Order 2: Hoy, duration 50 minutes (demorado, >40)
        $order2 = Order::create([
            'folio'            => 'DET202609160002',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 350.00,
            'customer_name'    => 'Cliente 2',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle 2',
        ]);
        $order2->delivery->status = 'delivered';
        $order2->delivery->timestamps = false;
        $order2->delivery->created_at = Carbon::parse('2026-09-16 11:00:00');
        $order2->delivery->updated_at = Carbon::parse('2026-09-16 11:50:00'); // 50 mins
        $order2->delivery->save();

        // 3. Order 3: Cancelado (no debe contarse)
        $order3 = Order::create([
            'folio'            => 'DET202609160003',
            'modality'         => 'delivery',
            'status'           => 'cancelled',
            'total_amount'     => 150.00,
            'customer_name'    => 'Cliente 3',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Calle 3',
        ]);
        $order3->delivery->status = 'cancelled';
        $order3->delivery->timestamps = false;
        $order3->delivery->created_at = Carbon::parse('2026-09-16 11:30:00');
        $order3->delivery->updated_at = Carbon::parse('2026-09-16 12:20:00');
        $order3->delivery->save();

        // Test filtro=hoy
        $response = $this->getJson('/api/reportes/metricas-rendimiento?filtro=hoy');
        $response->assertStatus(200);
        $response->assertJson([
            'tiempo_promedio'   => 35, // (20 + 50) / 2 = 35
            'pedidos_demorados' => 1,
            'porcentaje_exito'  => 50, // (2 - 1)/2 * 100 = 50%
            'total_entregas'    => 2,
        ]);

        // Test alias /api/kpis/metricas-rendimiento
        $responseKpi = $this->getJson('/api/kpis/metricas-rendimiento?filtro=hoy');
        $responseKpi->assertStatus(200);
        $responseKpi->assertJson([
            'tiempo_promedio'   => 35,
            'pedidos_demorados' => 1,
            'porcentaje_exito'  => 50,
            'total_entregas'    => 2,
        ]);

        // Test alias /api/delivery/metricas-rendimiento
        $responseDelivery = $this->getJson('/api/delivery/metricas-rendimiento?filtro=hoy');
        $responseDelivery->assertStatus(200);
        $responseDelivery->assertJson([
            'tiempo_promedio'   => 35,
            'pedidos_demorados' => 1,
            'porcentaje_exito'  => 50,
            'total_entregas'    => 2,
        ]);
    }

    public function test_metricas_rendimiento_with_semana_and_mes_filters(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00'); // Wednesday

        // Order last week (2026-09-08)
        $orderLastWeek = Order::create([
            'folio'            => 'DEL202609080001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 100.00,
            'customer_name'    => 'Cliente Pasado',
            'customer_phone'   => '7440001122',
            'customer_address' => 'Calle Pasada',
        ]);
        $orderLastWeek->delivery->status = 'delivered';
        $orderLastWeek->delivery->timestamps = false;
        $orderLastWeek->delivery->created_at = Carbon::parse('2026-09-08 10:00:00');
        $orderLastWeek->delivery->updated_at = Carbon::parse('2026-09-08 10:25:00'); // 25 mins
        $orderLastWeek->delivery->save();

        // Order this week (2026-09-15)
        $orderThisWeek = Order::create([
            'folio'            => 'DEL202609150001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 150.00,
            'customer_name'    => 'Cliente Semana',
            'customer_phone'   => '7440003344',
            'customer_address' => 'Calle Semana',
        ]);
        $orderThisWeek->delivery->status = 'delivered';
        $orderThisWeek->delivery->timestamps = false;
        $orderThisWeek->delivery->created_at = Carbon::parse('2026-09-15 10:00:00');
        $orderThisWeek->delivery->updated_at = Carbon::parse('2026-09-15 10:30:00'); // 30 mins
        $orderThisWeek->delivery->save();

        // Filtro semana: only this week (1 order, 30 min, 0 delayed, 100% success)
        $resSemana = $this->getJson('/api/reportes/metricas-rendimiento?filtro=semana');
        $resSemana->assertStatus(200);
        $resSemana->assertJson([
            'tiempo_promedio'   => 30,
            'pedidos_demorados' => 0,
            'porcentaje_exito'  => 100,
            'total_entregas'    => 1,
        ]);

        // Filtro mes: both September orders (2 orders: avg (25+30)/2 = 27.5 -> 28, 0 delayed, 100% success)
        $resMes = $this->getJson('/api/reportes/metricas-rendimiento?filtro=mes');
        $resMes->assertStatus(200);
        $resMes->assertJson([
            'tiempo_promedio'   => 28,
            'pedidos_demorados' => 0,
            'porcentaje_exito'  => 100,
            'total_entregas'    => 2,
        ]);
    }

    public function test_kpis_rendimiento_endpoint_filters_properly(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        // Order today (20 mins)
        $orderToday = Order::create([
            'folio'            => 'DEL202609160010',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 200.00,
            'customer_name'    => 'Cliente Hoy',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle Hoy',
        ]);
        $orderToday->delivery->status = 'delivered';
        $orderToday->delivery->timestamps = false;
        $orderToday->delivery->created_at = Carbon::parse('2026-09-16 11:00:00');
        $orderToday->delivery->updated_at = Carbon::parse('2026-09-16 11:20:00');
        $orderToday->delivery->save();

        // Order last month (2026-08-10, 1550 mins diff)
        $orderOld = Order::create([
            'folio'            => 'DEL202608100001',
            'modality'         => 'delivery',
            'status'           => 'delivered',
            'total_amount'     => 500.00,
            'customer_name'    => 'Cliente Viejo',
            'customer_phone'   => '7449998877',
            'customer_address' => 'Calle Vieja',
        ]);
        $orderOld->delivery->status = 'delivered';
        $orderOld->delivery->timestamps = false;
        $orderOld->delivery->created_at = Carbon::parse('2026-08-10 10:00:00');
        $orderOld->delivery->updated_at = Carbon::parse('2026-08-11 11:50:00'); // 1550 mins!
        $orderOld->delivery->save();

        // Calling /api/reportes/kpis-rendimiento?filtro=hoy -> must only show today (20 min, NOT 1550 min)
        $resHoy = $this->getJson('/api/reportes/kpis-rendimiento?filtro=hoy');
        $resHoy->assertStatus(200);
        $resHoy->assertJson([
            'tiempo_promedio_min'    => 20,
            'porcentaje_a_tiempo'    => 100,
            'porcentaje_completadas' => 100,
            'pedidos_con_demora'     => 0,
            'total_problemas'        => 0,
            'tiempo_promedio'        => 20,
            'total_entregas'         => 1,
        ]);

        // Calling /api/delivery/kpis-rendimiento?filtro=mes -> must only show September (20 min)
        $resMes = $this->getJson('/api/delivery/kpis-rendimiento?filtro=mes');
        $resMes->assertStatus(200);
        $resMes->assertJson([
            'tiempo_promedio_min'    => 20,
            'porcentaje_a_tiempo'    => 100,
            'porcentaje_completadas' => 100,
            'pedidos_con_demora'     => 0,
            'total_problemas'        => 0,
            'tiempo_promedio'        => 20,
            'total_entregas'         => 1,
        ]);
    }

    public function test_kpis_rendimiento_calculates_all_business_metrics_with_delays_and_cancellations(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        // Order 1: On-time delivery (20 min)
        $order1 = Order::create([
            'folio' => 'DEL202609160021', 'modality' => 'delivery', 'status' => 'delivered', 'total_amount' => 100,
            'customer_name' => 'C1', 'customer_phone' => '111', 'customer_address' => 'A1'
        ]);
        $order1->delivery->status = 'delivered';
        $order1->delivery->timestamps = false;
        $order1->delivery->created_at = Carbon::parse('2026-09-16 10:00:00');
        $order1->delivery->updated_at = Carbon::parse('2026-09-16 10:20:00'); // 20m
        $order1->delivery->save();

        // Order 2: Delayed delivery (60 min > 40)
        $order2 = Order::create([
            'folio' => 'DEL202609160022', 'modality' => 'delivery', 'status' => 'delivered', 'total_amount' => 200,
            'customer_name' => 'C2', 'customer_phone' => '222', 'customer_address' => 'A2'
        ]);
        $order2->delivery->status = 'delivered';
        $order2->delivery->timestamps = false;
        $order2->delivery->created_at = Carbon::parse('2026-09-16 10:00:00');
        $order2->delivery->updated_at = Carbon::parse('2026-09-16 11:00:00'); // 60m
        $order2->delivery->save();

        // Order 3: Cancelled delivery
        $order3 = Order::create([
            'folio' => 'DEL202609160023', 'modality' => 'delivery', 'status' => 'cancelled', 'total_amount' => 150,
            'customer_name' => 'C3', 'customer_phone' => '333', 'customer_address' => 'A3'
        ]);
        $order3->delivery->status = 'cancelled';
        $order3->delivery->timestamps = false;
        $order3->delivery->created_at = Carbon::parse('2026-09-16 10:30:00');
        $order3->delivery->updated_at = Carbon::parse('2026-09-16 11:15:00');
        $order3->delivery->save();

        // Query today:
        // Total = 3
        // Completadas = 2
        // Canceladas = 1
        // Demoras = 1 (order 2)
        // Tiempo promedio = (20 + 60) / 2 = 40m
        // aTiempo = 2 - 1 = 1
        // % a tiempo = (1 / 2) * 100 = 50%
        // % completadas = (2 / 3) * 100 = 67%
        // total_problemas = demoras (1) + canceladas (1) = 2
        $res = $this->getJson('/api/reportes/kpis-rendimiento?filtro=hoy');
        $res->assertStatus(200);
        $res->assertJson([
            'tiempo_promedio_min'    => 40,
            'porcentaje_a_tiempo'    => 50,
            'porcentaje_completadas' => 67,
            'pedidos_con_demora'     => 1,
            'total_problemas'        => 2,
        ]);
    }

    public function test_cash_cuts_pagination_and_60_days_filter(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $admin = User::factory()->create(['role' => 'admin']);
        $repartidor = User::factory()->create(['role' => 'repartidor']);

        for ($i = 1; $i <= 10; $i++) {
            $cut = new CashCut();
            $cut->user_id = $repartidor->id;
            $cut->cut_type = 'repartidor';
            $cut->cash_declared = 100 + $i;
            $cut->expected_cash = 100 + $i;
            $cut->received_cash = 100 + $i;
            $cut->total_orders = 2;
            $cut->status = 'pendiente';
            $cut->timestamps = false;
            $cut->created_at = Carbon::parse('2026-09-16 08:00:00')->addMinutes($i);
            $cut->save();
        }

        // Cut from 45 days ago (should be included in 60-day window)
        $cut45Days = new CashCut();
        $cut45Days->user_id = $repartidor->id;
        $cut45Days->cut_type = 'repartidor';
        $cut45Days->cash_declared = 300;
        $cut45Days->expected_cash = 300;
        $cut45Days->received_cash = 300;
        $cut45Days->total_orders = 3;
        $cut45Days->status = 'confirmado';
        $cut45Days->timestamps = false;
        $cut45Days->created_at = Carbon::parse('2026-09-16 12:00:00')->subDays(45);
        $cut45Days->save();

        // Cut from 70 days ago (should be excluded from 60-day window)
        $cut70Days = new CashCut();
        $cut70Days->user_id = $repartidor->id;
        $cut70Days->cut_type = 'repartidor';
        $cut70Days->cash_declared = 500;
        $cut70Days->expected_cash = 500;
        $cut70Days->received_cash = 500;
        $cut70Days->total_orders = 5;
        $cut70Days->status = 'confirmado';
        $cut70Days->timestamps = false;
        $cut70Days->created_at = Carbon::parse('2026-09-16 12:00:00')->subDays(70);
        $cut70Days->save();

        $response = $this->actingAs($admin)->getJson('/api/admin/cash-cuts?filtro=pendientes');
        $response->assertStatus(200);
        $response->assertJsonPath('per_page', 8);
        $response->assertJsonPath('total', 10);
        $this->assertCount(8, $response->json('data'));

        // '30dias' includes only the 10 from today (excludes 45 and 70 days ago)
        $res30 = $this->actingAs($admin)->getJson('/api/admin/cash-cuts?filtro=30dias');
        $res30->assertStatus(200);
        $res30->assertJsonPath('total', 10);

        // '60dias' includes 10 today + 1 from 45 days ago = 11 total (excludes 70 days ago)
        $res60 = $this->actingAs($admin)->getJson('/api/admin/cash-cuts?filtro=60dias');
        $res60->assertStatus(200);
        $res60->assertJsonPath('total', 11);

        // 'todos' fallback includes up to 60 days (11 total)
        $resTodos = $this->actingAs($admin)->getJson('/api/admin/cash-cuts?filtro=todos');
        $resTodos->assertStatus(200);
        $resTodos->assertJsonPath('total', 11);
    }
}
