<?php

namespace App\Services;

use App\Models\Notification;

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
}
