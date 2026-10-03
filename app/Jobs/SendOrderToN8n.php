<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendOrderToN8n implements ShouldQueue
{
    // SerializesModels asegura que solo se guarde el ID en Redis/BD, ahorrando memoria
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;

    /**
     * Create a new job instance.
     */
    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            app(NotificationService::class)->sendOrderNotification($this->order);
        } catch (\Throwable $e) {
            Log::warning("Error al procesar notificación multi-canal para orden #{$this->order->id}: " . $e->getMessage());
        }
    }
}
