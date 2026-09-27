<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Order;
use App\Models\Delivery;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DeliveryFolioTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_delivery_folio_of_the_day_starts_at_0001(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $folio = Order::generarFolioUnico();

        $this->assertEquals('DEL202609160001', $folio);
    }

    public function test_delivery_model_generar_folio_unico_delegates_properly(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $folio = Delivery::generarFolioUnico();

        $this->assertEquals('DEL202609160001', $folio);
    }

    public function test_delivery_folio_increments_sequentially(): void
    {
        Carbon::setTestNow('2026-09-16 12:00:00');

        $order1 = Order::create([
            'folio'            => Order::generarFolioUnico(),
            'modality'         => 'delivery',
            'customer_name'    => 'Cliente Uno',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle 1 #100',
            'total_amount'     => 150.00,
        ]);
        $this->assertEquals('DEL202609160001', $order1->folio);

        $order2 = Order::create([
            'folio'            => Order::generarFolioUnico(),
            'modality'         => 'delivery',
            'customer_name'    => 'Cliente Dos',
            'customer_phone'   => '7442223344',
            'customer_address' => 'Calle 2 #200',
            'total_amount'     => 250.00,
        ]);
        $this->assertEquals('DEL202609160002', $order2->folio);

        $order3 = Order::create([
            'folio'            => Order::generarFolioUnico(),
            'modality'         => 'delivery',
            'customer_name'    => 'Cliente Tres',
            'customer_phone'   => '7443334455',
            'customer_address' => 'Calle 3 #300',
            'total_amount'     => 350.00,
        ]);
        $this->assertEquals('DEL202609160003', $order3->folio);
    }

    public function test_delivery_folio_handles_legacy_dashed_format_and_increments(): void
    {
        Carbon::setTestNow('2026-09-16 14:00:00');

        Order::create([
            'folio'            => 'DEL-20260916-0005',
            'modality'         => 'delivery',
            'customer_name'    => 'Cliente Viejo',
            'customer_phone'   => '7449998877',
            'customer_address' => 'Calle Vieja #50',
            'total_amount'     => 100.00,
        ]);

        $nuevoFolio = Order::generarFolioUnico();
        $this->assertEquals('DEL202609160006', $nuevoFolio);
    }

    public function test_delivery_folio_resets_on_next_day(): void
    {
        Carbon::setTestNow('2026-09-16 23:59:00');

        Order::create([
            'folio'            => Order::generarFolioUnico(),
            'modality'         => 'delivery',
            'customer_name'    => 'Cliente Noche',
            'customer_phone'   => '7441112233',
            'customer_address' => 'Calle Noche #10',
            'total_amount'     => 150.00,
        ]);

        // Siguiente día
        Carbon::setTestNow('2026-09-17 08:00:00');

        $folioNuevoDia = Order::generarFolioUnico();
        $this->assertEquals('DEL202609170001', $folioNuevoDia);
    }

    public function test_order_creation_with_modality_delivery_automatically_assigns_del_folio(): void
    {
        Carbon::setTestNow('2026-09-16 15:30:00');

        $order = Order::create([
            'modality'         => 'delivery',
            'customer_name'    => 'Auto Delivery',
            'customer_phone'   => '7445556677',
            'customer_address' => 'Av. Principal #99',
            'total_amount'     => 500.00,
        ]);

        $this->assertEquals('DEL202609160001', $order->folio);
        $this->assertTrue($order->delivery()->exists());
    }
}
