<?php

namespace App\Jobs;

use App\Events\OrderCreated;
use App\Events\OrderStatusUpdated;
use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendKitchenNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;
    public bool $isOnline;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order, bool $isOnline = false)
    {
        $this->order = $order;
        $this->isOnline = $isOnline;
    }

    /**
     * Execute the job in background.
     */
    public function handle(): void
    {
        // 1. Guardar notificación interna en base de datos
        try {
            $notificationTitle = $this->isOnline ? 'Nuevo Pedido en Línea' : 'Nuevo Pedido Recibido';
            NotificationService::create(
                'pedido_nuevo',
                $notificationTitle,
                "Pedido #{$this->order->folio} de {$this->order->customer_name} por \${$this->order->total_amount} MXN",
                [
                    'order_id' => $this->order->id,
                    'folio'    => $this->order->folio,
                    'modality' => $this->order->modality,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('SendKitchenNotification error (NotificationService): ' . $e->getMessage());
        }

        // 2. Transmisión WebSocket en tiempo real hacia cocina y KDS
        try {
            $loadedOrder = $this->order->fresh(['items.dish', 'items.extras.extra', 'user', 'delivery']);
            if ($loadedOrder) {
                broadcast(new OrderCreated($loadedOrder));
                broadcast(new OrderStatusUpdated($loadedOrder));
            }
        } catch (\Throwable $e) {
            Log::warning('SendKitchenNotification error (Broadcast): ' . $e->getMessage());
        }
    }
}
