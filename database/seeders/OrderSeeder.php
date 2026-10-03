<?php

namespace Database\Seeders;

use App\Models\Dish;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    public function run(): void
    {
        $dishes = Dish::all()->keyBy('name');

        if ($dishes->isEmpty()) {
            return;
        }

        // Order 1: Completed
        $order1 = Order::create([
            'customer_name' => 'Francisco Pérez',
            'customer_phone' => '+34 600 555 555',
            'customer_address' => 'Calle de Alcalá 45, 3ºB, Madrid',
            'customer_email' => 'francisco.perez@example.com',
            'table_number' => 'Mesa 4',
            'status' => 'completed',
            'payment_status' => 'paid',
            'payment_method' => 'card',
            'total_amount' => 0.00, // Will calculate below
            'notes' => 'El cliente solicitó cubiertos adicionales.',
        ]);

        $items1 = [
            ['name' => 'Carpaccio de Wagyu Premium', 'qty' => 1, 'notes' => null],
            ['name' => 'Ribeye Black Angus a la Leña', 'qty' => 1, 'notes' => 'Término medio.'],
            ['name' => 'Gin Tonic Restaurante Signature', 'qty' => 2, 'notes' => null],
        ];

        $total1 = 0;
        foreach ($items1 as $itemData) {
            $dish = $dishes[$itemData['name']] ?? null;
            if ($dish) {
                OrderItem::create([
                    'order_id' => $order1->id,
                    'dish_id' => $dish->id,
                    'quantity' => $itemData['qty'],
                    'price' => $dish->price,
                    'notes' => $itemData['notes'],
                ]);
                $total1 += $dish->price * $itemData['qty'];
            }
        }
        $order1->update(['total_amount' => $total1]);

        // Order 2: Preparing
        $order2 = Order::create([
            'customer_name' => 'Isabel de la Fuente',
            'customer_phone' => '+34 699 888 777',
            'customer_address' => 'Avenida de la Constitución 12, Sevilla',
            'customer_email' => 'isabel.fuente@example.com',
            'table_number' => 'Mesa 12',
            'status' => 'preparing',
            'payment_status' => 'pending',
            'payment_method' => 'cash',
            'total_amount' => 0.00,
            'notes' => 'Sin hielo en las bebidas.',
        ]);

        $items2 = [
            ['name' => 'Pulpo a la Parrilla con Hummus de Pimentón', 'qty' => 2, 'notes' => null],
            ['name' => 'Mojito Cítrico de Hierbabuena', 'qty' => 2, 'notes' => 'Sin hielo.'],
            ['name' => 'Volcán de Chocolate Belga con Pistacho', 'qty' => 1, 'notes' => null],
        ];

        $total2 = 0;
        foreach ($items2 as $itemData) {
            $dish = $dishes[$itemData['name']] ?? null;
            if ($dish) {
                OrderItem::create([
                    'order_id' => $order2->id,
                    'dish_id' => $dish->id,
                    'quantity' => $itemData['qty'],
                    'price' => $dish->price,
                    'notes' => $itemData['notes'],
                ]);
                $total2 += $dish->price * $itemData['qty'];
            }
        }
        $order2->update(['total_amount' => $total2]);

        // Order 3: Pending / Guest Checkout (Home Delivery)
        $order3 = Order::create([
            'customer_name' => 'Juan Gómez',
            'customer_phone' => '+34 688 123 123',
            'customer_address' => 'Calle Gran Vía 12, Principal, Madrid',
            'customer_email' => null,
            'table_number' => null, // Delivery
            'status' => 'pending',
            'payment_status' => 'pending',
            'payment_method' => 'online',
            'total_amount' => 0.00,
            'notes' => 'Entregar en portería si no contesto.',
        ]);

        $items3 = [
            ['name' => 'Flores de Calabacín en Tempura', 'qty' => 1, 'notes' => null],
            ['name' => 'Lomo Fino de Cordero en Salsa de Vino Tinto', 'qty' => 1, 'notes' => null],
            ['name' => 'Tarta de Queso de Cabra y Trufa', 'qty' => 1, 'notes' => null],
        ];

        $total3 = 0;
        foreach ($items3 as $itemData) {
            $dish = $dishes[$itemData['name']] ?? null;
            if ($dish) {
                OrderItem::create([
                    'order_id' => $order3->id,
                    'dish_id' => $dish->id,
                    'quantity' => $itemData['qty'],
                    'price' => $dish->price,
                    'notes' => $itemData['notes'],
                ]);
                $total3 += $dish->price * $itemData['qty'];
            }
        }
        $order3->update(['total_amount' => $total3]);
    }
}
