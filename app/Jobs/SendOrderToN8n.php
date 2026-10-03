<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\RestaurantSetting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
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
        // Esta petición HTTP pesada ya no congelará el flujo del cliente
        $webhookUrl = config('services.n8n.webhook_url') ?: env('N8N_WEBHOOK_URL', 'http://tu-servidor-n8n:5678/webhook/nueva-orden');

        try {
            $settings = RestaurantSetting::first();
            $activePlatform = $settings?->active_notification_platform ?? 'none';
            $discordWebhook = $settings?->discord_webhook_url;
            $telegramBotToken = $settings?->telegram_bot_token;
            $telegramChatId = $settings?->telegram_chat_id;

            $payload = [
                'order_id'       => $this->order->id,
                'folio'          => $this->order->folio,
                'total'          => (float) $this->order->total_amount,
                'cliente'        => $this->order->customer_name,
                'cliente_nombre' => $this->order->customer_name,
                'telefono'       => $this->order->customer_phone,
                'direccion'      => $this->order->customer_address,
                'modalidad'      => $this->order->modality,
                'mesa'           => $this->order->table_number,
                'notas'          => $this->order->notes,
                'active_notification_platform' => $activePlatform,
                'discord_webhook_url'          => $discordWebhook,
                'telegram_bot_token'           => $telegramBotToken,
                'telegram_chat_id'             => $telegramChatId,
                'notification_settings'        => [
                    'platform'            => $activePlatform,
                    'discord_webhook_url' => $discordWebhook,
                    'telegram_bot_token'  => $telegramBotToken,
                    'telegram_chat_id'    => $telegramChatId,
                ],
                'items'          => $this->order->relationLoaded('items')
                    ? $this->order->items->map(function ($item) {
                        return [
                            'platillo' => $item->dish?->name ?? 'Platillo',
                            'cantidad' => $item->quantity,
                            'precio'   => (float) $item->price,
                            'notas'    => $item->notes,
                        ];
                    })->toArray()
                    : [],
            ];

            $response = Http::timeout(10)->post($webhookUrl, $payload);

            if ($response->failed()) {
                Log::warning("Webhook N8N retornó código {$response->status()} para orden #{$this->order->id}: " . $response->body());
            }
        } catch (\Throwable $e) {
            Log::warning("Error al procesar SendOrderToN8n para orden #{$this->order->id}: " . $e->getMessage());
        }
    }
}
