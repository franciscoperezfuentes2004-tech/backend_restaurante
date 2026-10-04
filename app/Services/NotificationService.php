<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\Order;
use App\Models\Reservation;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    /**
     * Enmascara un correo electrónico para proteger la privacidad (ej. fra***@gmail.com).
     *
     * @param string|null $email
     * @return string
     */
    public static function maskEmail(?string $email): string
    {
        if (empty($email) || !str_contains($email, '@')) {
            return (string) $email;
        }

        $parts = explode('@', $email);
        $userPart = substr($parts[0], 0, 3) . '***';
        $domainPart = $parts[1] ?? '';

        return $userPart . '@' . $domainPart;
    }

    /**
     * Create a new notification entry in the database.
     *
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array $data
     * @param int|null $userId
     * @return Notification
     */
    public static function create(string $type, string $title, string $message, array $data = [], ?int $userId = null): Notification
    {
        $targetUserId = $userId ?? ($data['user_id'] ?? null);

        return Notification::create([
            'user_id' => $targetUserId ? (int) $targetUserId : null,
            'type'    => $type,
            'title'   => $title,
            'message' => $message,
            'data'    => $data,
            'read_at' => null,
        ]);
    }
    /**
     * Envía notificación de nuevo pedido a la plataforma activa configurada.
     */
    public function sendOrderNotification(Order $order): bool
    {
        try {
            $settings = RestaurantSetting::first();
            if (!$settings) {
                return false;
            }

            $platform = $settings->active_notification_platform ?? 'none';
            if ($platform === 'none') {
                return false;
            }

            if ($platform === 'discord') {
                $discordSettings = is_array($settings->discord_settings)
                    ? $settings->discord_settings
                    : (json_decode($settings->discord_settings ?? '[]', true) ?: []);

                $webhookUrl = $discordSettings['orders_webhook_url'] ?? null;
                if (empty($webhookUrl)) {
                    Log::info("NotificationService: Webhook de Discord para pedidos no configurado.");
                    return false;
                }

                return $this->sendDiscordOrderMessage($webhookUrl, $order, $settings);
            }

            if ($platform === 'telegram') {
                $telegramSettings = is_array($settings->telegram_settings)
                    ? $settings->telegram_settings
                    : (json_decode($settings->telegram_settings ?? '[]', true) ?: []);

                $botToken = $telegramSettings['bot_token'] ?? null;
                $chatId = $telegramSettings['orders_chat_id'] ?? null;

                if (empty($botToken) || empty($chatId)) {
                    Log::info("NotificationService: Token o Chat ID de Telegram para pedidos no configurado.");
                    return false;
                }

                return $this->sendTelegramOrderMessage($botToken, $chatId, $order, $settings);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("NotificationService [sendOrderNotification] Error: " . $e->getMessage(), [
                'order_id' => $order->id ?? null,
                'trace'    => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Envía notificación de nueva reservación a la plataforma activa configurada.
     */
    public function sendReservationNotification(Reservation $reservation): bool
    {
        try {
            $settings = RestaurantSetting::first();
            if (!$settings) {
                return false;
            }

            $platform = $settings->active_notification_platform ?? 'none';
            if ($platform === 'none') {
                return false;
            }

            if ($platform === 'discord') {
                $discordSettings = is_array($settings->discord_settings)
                    ? $settings->discord_settings
                    : (json_decode($settings->discord_settings ?? '[]', true) ?: []);

                $webhookUrl = $discordSettings['reservations'] ?? $discordSettings['reservations_webhook_url'] ?? null;
                if (empty($webhookUrl)) {
                    Log::info("NotificationService: Webhook de Discord para reservaciones no configurado.");
                    return false;
                }

                return $this->sendDiscordReservationMessage($webhookUrl, $reservation, $settings);
            }

            if ($platform === 'telegram') {
                $telegramSettings = is_array($settings->telegram_settings)
                    ? $settings->telegram_settings
                    : (json_decode($settings->telegram_settings ?? '[]', true) ?: []);

                $botToken = $telegramSettings['bot_token'] ?? null;
                $chatId = $telegramSettings['reservations'] ?? $telegramSettings['reservations_chat_id'] ?? null;

                if (empty($botToken) || empty($chatId)) {
                    Log::info("NotificationService: Token o Chat ID de Telegram para reservaciones no configurado.");
                    return false;
                }

                return $this->sendTelegramReservationMessage($botToken, $chatId, $reservation, $settings);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("NotificationService [sendReservationNotification] Error: " . $e->getMessage(), [
                'reservation_id' => $reservation->id ?? null,
                'trace'          => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Envía el mensaje con Embed de nuevo pedido a Discord.
     */
    protected function sendDiscordOrderMessage(string $webhookUrl, Order $order, RestaurantSetting $settings): bool
    {
        $restaurantName = $settings->restaurant_name ?? 'Restaurante';
        $folio = $order->folio ?: ('#' . $order->id);
        $cliente = $order->customer_name ?: 'Cliente';
        $telefono = $order->customer_phone ?: 'Sin teléfono';
        $total = number_format((float) $order->total_amount, 2);
        $modalidad = ucfirst($order->modality ?? 'Local');
        $ubicacion = $order->table_number ? "Mesa {$order->table_number}" : ($order->customer_address ?: 'En sucursal');
        $notas = !empty($order->notes) ? $order->notes : 'Ninguna';

        // Detalle de platillos
        $order->loadMissing('items.dish');
        $itemsText = '';
        if ($order->items && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $dishName = $item->dish?->name ?? 'Platillo';
                $subtotal = number_format($item->quantity * $item->price, 2);
                $itemsText .= "• **{$item->quantity}x** {$dishName} — \${$subtotal}\n";
                if (!empty($item->notes)) {
                    $itemsText .= "  *Nota: {$item->notes}*\n";
                }
            }
        } else {
            $itemsText = "Sin platillos desglosados.";
        }

        $payload = [
            'username' => "{$restaurantName} • Cocina",
            'embeds' => [
                [
                    'title' => "🍳 ¡Nuevo Pedido Recibido! - {$folio}",
                    'description' => "Se ha recibido un nuevo pedido para cocina.",
                    'color' => 0xF59E0B, // Amber / Naranja
                    'fields' => [
                        ['name' => '👤 Cliente', 'value' => $cliente, 'inline' => true],
                        ['name' => '📞 Teléfono', 'value' => $telefono, 'inline' => true],
                        ['name' => '🛵 Modalidad', 'value' => "{$modalidad} ({$ubicacion})", 'inline' => true],
                        ['name' => '📋 Platillos', 'value' => mb_substr($itemsText, 0, 1024), 'inline' => false],
                        ['name' => '💰 Total a Cobrar', 'value' => "**\${$total}**", 'inline' => true],
                        ['name' => '📝 Notas', 'value' => $notas, 'inline' => true],
                    ],
                    'footer' => [
                        'text' => "Notificación Automática de Cocina • {$restaurantName}",
                    ],
                    'timestamp' => now()->toISOString(),
                ],
            ],
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        if ($response->failed()) {
            Log::warning("NotificationService Discord error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Envía el mensaje HTML de nuevo pedido a Telegram.
     */
    protected function sendTelegramOrderMessage(string $botToken, string $chatId, Order $order, RestaurantSetting $settings): bool
    {
        $restaurantName = htmlspecialchars($settings->restaurant_name ?? 'Restaurante', ENT_QUOTES);
        $folio = htmlspecialchars($order->folio ?: ('#' . $order->id), ENT_QUOTES);
        $cliente = htmlspecialchars($order->customer_name ?: 'Cliente', ENT_QUOTES);
        $telefono = htmlspecialchars($order->customer_phone ?: 'Sin teléfono', ENT_QUOTES);
        $total = number_format((float) $order->total_amount, 2);
        $modalidad = htmlspecialchars(ucfirst($order->modality ?? 'Local'), ENT_QUOTES);
        $ubicacion = htmlspecialchars($order->table_number ? "Mesa {$order->table_number}" : ($order->customer_address ?: 'En sucursal'), ENT_QUOTES);
        $notas = !empty($order->notes) ? htmlspecialchars($order->notes, ENT_QUOTES) : 'Ninguna';

        $order->loadMissing('items.dish');
        $itemsText = '';
        if ($order->items && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $dishName = htmlspecialchars($item->dish?->name ?? 'Platillo', ENT_QUOTES);
                $subtotal = number_format($item->quantity * $item->price, 2);
                $itemsText .= "• <b>{$item->quantity}x</b> {$dishName} — \${$subtotal}\n";
                if (!empty($item->notes)) {
                    $note = htmlspecialchars($item->notes, ENT_QUOTES);
                    $itemsText .= "   <i>(Nota: {$note})</i>\n";
                }
            }
        } else {
            $itemsText = "<i>Sin platillos desglosados</i>\n";
        }

        $text = "🍳 <b>¡NUEVO PEDIDO RECIBIDO!</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "<b>Folio:</b> <code>{$folio}</code>\n";
        $text .= "<b>Cliente:</b> {$cliente}\n";
        $text .= "<b>Teléfono:</b> {$telefono}\n";
        $text .= "<b>Modalidad:</b> {$modalidad} (<i>{$ubicacion}</i>)\n";
        if ($notas !== 'Ninguna') {
            $text .= "<b>Notas:</b> {$notas}\n";
        }
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "<b>Platillos:</b>\n{$itemsText}";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 <b>TOTAL: \${$total}</b>\n";
        $text .= "<i>{$restaurantName} • Notificación de Cocina</i>";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $response = Http::timeout(10)->post($url, [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            Log::warning("NotificationService Telegram error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Envía el mensaje con Embed de reservación confirmada a Discord.
     */
    protected function sendDiscordReservationMessage(string $webhookUrl, Reservation $reservation, RestaurantSetting $settings): bool
    {
        $restaurantName = $settings->restaurant_name ?? 'Restaurante';
        $folio = $reservation->folio ?: ('RES-' . str_pad($reservation->id, 4, '0', STR_PAD_LEFT));
        $cliente = $reservation->cliente_nombre ?? $reservation->nombre ?? $reservation->customer_name ?? 'Cliente';
        $telefono = $reservation->telefono ?? $reservation->phone ?? $reservation->customer_phone ?? 'Sin teléfono';
        $email = $reservation->cliente_email ?? $reservation->email ?? $reservation->customer_email ?? 'Sin correo';
        $personas = (int) ($reservation->personas ?? $reservation->cantidad_personas ?? 1);
        $mesa = $reservation->numero_mesa ?: ($reservation->table_number ?: 'Por asignar');

        $fechaFormateada = $reservation->fecha
            ? Carbon::parse($reservation->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : ($reservation->reservation_date ? Carbon::parse($reservation->reservation_date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : date('d/m/Y'));

        $horaFormateada = $reservation->hora
            ? Carbon::parse($reservation->hora)->format('h:i A')
            : ($reservation->reservation_time ? Carbon::parse($reservation->reservation_time)->format('h:i A') : '08:00 PM');

        $payload = [
            'username' => "{$restaurantName} • Recepción",
            'embeds' => [
                [
                    'title' => "📅 ¡Nueva Reservación Confirmada! - {$folio}",
                    'description' => "Se ha confirmado una nueva mesa para el restaurante.",
                    'color' => 0x3B82F6, // Blue
                    'fields' => [
                        ['name' => '👤 Cliente', 'value' => $cliente, 'inline' => true],
                        ['name' => '📞 Teléfono', 'value' => $telefono, 'inline' => true],
                        ['name' => '✉️ Correo', 'value' => $email, 'inline' => true],
                        ['name' => '📆 Fecha', 'value' => $fechaFormateada, 'inline' => true],
                        ['name' => '⏰ Hora', 'value' => $horaFormateada, 'inline' => true],
                        ['name' => '👥 Personas', 'value' => (string) $personas, 'inline' => true],
                        ['name' => '🪑 Mesa Asignada', 'value' => $mesa, 'inline' => true],
                        ['name' => '🏢 Sucursal', 'value' => $restaurantName, 'inline' => true],
                    ],
                    'footer' => [
                        'text' => "Notificación de Recepción • {$restaurantName}",
                    ],
                    'timestamp' => now()->toISOString(),
                ],
            ],
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        if ($response->failed()) {
            Log::warning("NotificationService Discord error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Envía el mensaje HTML de reservación confirmada a Telegram.
     */
    protected function sendTelegramReservationMessage(string $botToken, string $chatId, Reservation $reservation, RestaurantSetting $settings): bool
    {
        $restaurantName = htmlspecialchars($settings->restaurant_name ?? 'Restaurante', ENT_QUOTES);
        $folio = htmlspecialchars($reservation->folio ?: ('RES-' . str_pad($reservation->id, 4, '0', STR_PAD_LEFT)), ENT_QUOTES);
        $cliente = htmlspecialchars($reservation->cliente_nombre ?? $reservation->nombre ?? $reservation->customer_name ?? 'Cliente', ENT_QUOTES);
        $telefono = htmlspecialchars($reservation->telefono ?? $reservation->phone ?? $reservation->customer_phone ?? 'Sin teléfono', ENT_QUOTES);
        $email = htmlspecialchars($reservation->cliente_email ?? $reservation->email ?? $reservation->customer_email ?? 'Sin correo', ENT_QUOTES);
        $personas = (int) ($reservation->personas ?? $reservation->cantidad_personas ?? 1);
        $mesa = htmlspecialchars($reservation->numero_mesa ?: ($reservation->table_number ?: 'Por asignar'), ENT_QUOTES);

        $fechaFormateada = $reservation->fecha
            ? Carbon::parse($reservation->fecha)->locale('es')->isoFormat('D [de] MMMM [de] YYYY')
            : ($reservation->reservation_date ? Carbon::parse($reservation->reservation_date)->locale('es')->isoFormat('D [de] MMMM [de] YYYY') : date('d/m/Y'));

        $horaFormateada = $reservation->hora
            ? Carbon::parse($reservation->hora)->format('h:i A')
            : ($reservation->reservation_time ? Carbon::parse($reservation->reservation_time)->format('h:i A') : '08:00 PM');

        $text = "📅 <b>¡NUEVA RESERVACIÓN CONFIRMADA!</b>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "<b>Folio:</b> <code>{$folio}</code>\n";
        $text .= "<b>Cliente:</b> {$cliente}\n";
        $text .= "<b>Teléfono:</b> {$telefono}\n";
        $text .= "<b>Correo:</b> {$email}\n";
        $text .= "<b>Fecha:</b> {$fechaFormateada}\n";
        $text .= "<b>Hora:</b> {$horaFormateada}\n";
        $text .= "<b>Personas:</b> {$personas}\n";
        $text .= "<b>Mesa:</b> {$mesa}\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "<i>{$restaurantName} • Notificación de Recepción</i>";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $response = Http::timeout(10)->post($url, [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            Log::warning("NotificationService Telegram error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Envía reporte financiero diario a la plataforma activa configurada.
     */
    public function sendDailyFinancialReport(array $financialData): bool
    {
        try {
            $settings = RestaurantSetting::first();
            if (!$settings) {
                return false;
            }

            $platform = $settings->active_notification_platform ?? 'none';
            if ($platform === 'none') {
                return false;
            }

            if ($platform === 'discord') {
                $discordSettings = is_array($settings->discord_settings)
                    ? $settings->discord_settings
                    : (json_decode($settings->discord_settings ?? '[]', true) ?: []);

                $webhookUrl = $discordSettings['daily_financial_report'] ?? null;
                if (empty($webhookUrl)) {
                    Log::info("NotificationService: Webhook de Discord para reporte financiero diario no configurado.");
                    return false;
                }

                return $this->sendDiscordFinancialReportMessage($webhookUrl, $financialData, $settings);
            }

            if ($platform === 'telegram') {
                $telegramSettings = is_array($settings->telegram_settings)
                    ? $settings->telegram_settings
                    : (json_decode($settings->telegram_settings ?? '[]', true) ?: []);

                $botToken = $telegramSettings['bot_token'] ?? null;
                $chatId = $telegramSettings['daily_financial_report'] ?? null;

                if (empty($botToken) || empty($chatId)) {
                    Log::info("NotificationService: Token o Chat ID de Telegram para reporte financiero diario no configurado.");
                    return false;
                }

                return $this->sendTelegramFinancialReportMessage($botToken, $chatId, $financialData, $settings);
            }

            return false;
        } catch (\Throwable $e) {
            Log::error("NotificationService [sendDailyFinancialReport] Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Envía el mensaje de reporte financiero diario a Discord.
     */
    protected function sendDiscordFinancialReportMessage(string $webhookUrl, array $financialData, RestaurantSetting $settings): bool
    {
        $restaurantName = $settings->restaurant_name ?? 'Restaurante';
        $fecha = $financialData['fecha'] ?? Carbon::yesterday('America/Mexico_City')->format('d/m/Y');
        $ventas = number_format((float) ($financialData['total_ventas'] ?? 0), 2);
        $gastos = number_format((float) ($financialData['total_gastos'] ?? 0), 2);
        $balance = number_format((float) ($financialData['balance'] ?? (($financialData['total_ventas'] ?? 0) - ($financialData['total_gastos'] ?? 0))), 2);
        $ordenes = isset($financialData['total_ordenes']) ? (int) $financialData['total_ordenes'] : null;

        $fields = [
            ['name' => '💰 Total Ventas', 'value' => "\${$ventas}", 'inline' => true],
            ['name' => '📉 Total Gastos', 'value' => "\${$gastos}", 'inline' => true],
            ['name' => '💵 Balance', 'value' => "**\${$balance}**", 'inline' => true],
        ];

        if ($ordenes !== null) {
            $fields[] = ['name' => '🧾 Pedidos Concretados', 'value' => (string) $ordenes, 'inline' => true];
        }

        $payload = [
            'username' => "{$restaurantName} • Finanzas",
            'content'  => "📊 **Cierre Financiero del Día Anterior ({$fecha})**\n💰 Total Ventas: \${$ventas}\n📉 Total Gastos: \${$gastos}\n💵 Balance: \${$balance}",
            'embeds'   => [
                [
                    'title'       => "📊 Cierre Financiero del Día Anterior",
                    'description' => "Resumen financiero consolidado correspondiente al día **{$fecha}**.",
                    'color'       => 0x10B981, // Verde esmeralda
                    'fields'      => $fields,
                    'footer'      => [
                        'text' => "Notificación Automática de Cierre Diario • {$restaurantName}",
                    ],
                    'timestamp'   => now()->toISOString(),
                ],
            ],
        ];

        $response = Http::timeout(10)->post($webhookUrl, $payload);
        if ($response->failed()) {
            Log::warning("NotificationService Discord Financial Report error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }

    /**
     * Envía el mensaje de reporte financiero diario a Telegram.
     */
    protected function sendTelegramFinancialReportMessage(string $botToken, string $chatId, array $financialData, RestaurantSetting $settings): bool
    {
        $restaurantName = htmlspecialchars($settings->restaurant_name ?? 'Restaurante', ENT_QUOTES);
        $fecha = htmlspecialchars($financialData['fecha'] ?? Carbon::yesterday('America/Mexico_City')->format('d/m/Y'), ENT_QUOTES);
        $ventas = number_format((float) ($financialData['total_ventas'] ?? 0), 2);
        $gastos = number_format((float) ($financialData['total_gastos'] ?? 0), 2);
        $balance = number_format((float) ($financialData['balance'] ?? (($financialData['total_ventas'] ?? 0) - ($financialData['total_gastos'] ?? 0))), 2);
        $ordenes = isset($financialData['total_ordenes']) ? (int) $financialData['total_ordenes'] : null;

        $text = "📊 <b>Cierre Financiero del Día Anterior</b>\n";
        $text .= "<i>Fecha: {$fecha}</i>\n";
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "💰 <b>Total Ventas:</b> \${$ventas}\n";
        $text .= "📉 <b>Total Gastos:</b> \${$gastos}\n";
        $text .= "💵 <b>Balance:</b> \${$balance}\n";
        if ($ordenes !== null) {
            $text .= "🧾 <b>Pedidos Concretados:</b> {$ordenes}\n";
        }
        $text .= "━━━━━━━━━━━━━━━━━━━━\n";
        $text .= "<i>{$restaurantName} • Reporte Financiero Diario</i>";

        $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
        $response = Http::timeout(10)->post($url, [
            'chat_id'    => $chatId,
            'text'       => $text,
            'parse_mode' => 'HTML',
        ]);

        if ($response->failed()) {
            Log::warning("NotificationService Telegram Financial Report error ({$response->status()}): " . $response->body());
            return false;
        }

        return true;
    }
}
