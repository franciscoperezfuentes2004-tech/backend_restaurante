<?php

namespace Database\Seeders;

use App\Models\Reservation;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class ReservationSeeder extends Seeder
{
    public function run(): void
    {
        $reservations = [
            [
                'customer_name' => 'Mariano de la Vega',
                'customer_email' => 'mariano.vega@example.com',
                'customer_phone' => '+34 600 123 456',
                'reservation_date' => Carbon::tomorrow()->toDateString(),
                'reservation_time' => '20:30',
                'guests_count' => 4,
                'table_number' => 'Mesa 8',
                'status' => 'confirmed',
                'special_requests' => 'Celebración de aniversario. Si es posible, una mesa cerca de la ventana.',
            ],
            [
                'customer_name' => 'Beatriz Gómez',
                'customer_email' => 'beatriz.g@example.com',
                'customer_phone' => '+34 611 987 654',
                'reservation_date' => Carbon::tomorrow()->toDateString(),
                'reservation_time' => '14:00',
                'guests_count' => 2,
                'table_number' => 'Mesa 3',
                'status' => 'confirmed',
                'special_requests' => 'Uno de los comensales es celíaco.',
            ],
            [
                'customer_name' => 'Roberto Sánchez',
                'customer_email' => 'roberto.sanchez@example.com',
                'customer_phone' => '+34 622 456 789',
                'reservation_date' => Carbon::now()->addDays(2)->toDateString(),
                'reservation_time' => '21:00',
                'guests_count' => 6,
                'table_number' => null,
                'status' => 'pending',
                'special_requests' => 'Reunión de negocios. Espacio tranquilo por favor.',
            ],
            [
                'customer_name' => 'Helena Martínez',
                'customer_email' => 'helena.m@example.com',
                'customer_phone' => '+34 633 111 222',
                'reservation_date' => Carbon::now()->subDays(1)->toDateString(),
                'reservation_time' => '21:30',
                'guests_count' => 2,
                'table_number' => 'Mesa 1',
                'status' => 'completed',
                'special_requests' => null,
            ],
        ];

        foreach ($reservations as $r) {
            Reservation::create($r);
        }
    }
}
