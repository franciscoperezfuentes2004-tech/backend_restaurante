<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactoRequest;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class ContactoController extends Controller
{
    /**
     * POST /api/contacto
     *
     * Procesa y purifica mensajes de contacto con protección anti-spam y rate limiting.
     */
    public function enviar(ContactoRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $email = $validated['email'] ?? null;
        $emailDisplay = $email ? " ({$email})" : '';

        Log::info('Nuevo mensaje de contacto recibido:', [
            'nombre'   => $validated['nombre'],
            'email'    => $email,
            'telefono' => $validated['telefono'],
            'asunto'   => $validated['asunto'],
        ]);

        if (class_exists(NotificationService::class)) {
            NotificationService::create(
                'contacto_nuevo',
                'Nuevo Mensaje de Contacto',
                "Mensaje de {$validated['nombre']}{$emailDisplay}: {$validated['asunto']}",
                $validated
            );
        }

        return response()->json([
            'message' => '¡Mensaje enviado con éxito! Nos pondremos en contacto contigo pronto.',
            'data'    => $validated,
        ], 200);
    }
}
