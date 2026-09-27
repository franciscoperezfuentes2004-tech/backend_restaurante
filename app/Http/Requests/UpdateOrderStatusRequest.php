<?php

namespace App\Http\Requests;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateOrderStatusRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     * Roles autorizados: kitchen, cocina, admin, super_admin, manager, gerente, mesero, cajero, waiter.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return true;
        }

        if (method_exists($user, 'can') && $user->can('updateStatus', Order::class)) {
            return true;
        }

        return $user->hasAnyRole(['kitchen', 'cocina', 'admin', 'super_admin', 'manager', 'gerente', 'mesero', 'cajero', 'waiter']);
    }

    /**
     * Respuesta personalizada en caso de fallo de autorización (403 Forbidden).
     */
    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'No tienes autorización para cambiar el estado de este pedido. Se requiere un rol operativo autorizado (cocina, administrador, gerente o mesero).'
            ], 403)
        );
    }

    /**
     * Preparación y sanitización previa a la validación.
     */
    protected function prepareForValidation(): void
    {
        // 1. Sanitización y extracción de order_id (desde cuerpo o parámetro de ruta)
        $orderId = $this->input('order_id');
        if ($orderId === null) {
            $routeParam = $this->route('order') ?? $this->route('order_id') ?? $this->route('id');
            $orderId = $routeParam instanceof Order ? $routeParam->id : $routeParam;
        }

        // 2. Sanitización y normalización de status
        $rawStatus = $this->input('status') ?? $this->input('estado');
        $normalizedStatus = is_string($rawStatus) 
            ? strtolower(trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawStatus))))
            : $rawStatus;

        // Mapeo seguro de alias en español e inglés hacia los estados requeridos
        $statusAliases = [
            'pending'          => 'pendiente',
            'pendiente'        => 'pendiente',
            'preparing'        => 'en_preparacion',
            'en_preparacion'   => 'en_preparacion',
            'en_cocina'        => 'en_preparacion',
            'preparando'       => 'en_preparacion',
            'en preparacion'   => 'en_preparacion',
            'en preparación'   => 'en_preparacion',
            'ready'            => 'listo',
            'listo'            => 'listo',
            'terminado'        => 'listo',
            'terminada'        => 'listo',
            'completado'       => 'listo',
            'completada'       => 'listo',
            'completed'        => 'listo',
            'finalizado'       => 'listo',
            'finalizada'       => 'listo',
            'servido'          => 'listo',
            'servida'          => 'listo',
            'entregado'        => 'entregado',
            'delivered'        => 'entregado',
            'cancelado'        => 'cancelado',
            'cancelled'        => 'cancelado',
        ];

        if (is_string($normalizedStatus) && isset($statusAliases[$normalizedStatus])) {
            $normalizedStatus = $statusAliases[$normalizedStatus];
        }

        $mergeData = [];
        if ($orderId !== null) {
            $mergeData['order_id'] = is_numeric($orderId) ? (int) $orderId : $orderId;
        }
        if ($normalizedStatus !== null) {
            $mergeData['status'] = $normalizedStatus;
        }

        $this->merge($mergeData);
    }

    /**
     * Reglas de validación base y sanitización de IDs.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'order_id' => 'required|integer|exists:orders,id',
            'status'   => 'required|string|in:pendiente,en_preparacion,listo,entregado,cancelado',
        ];
    }

    /**
     * Mensajes descriptivos de error.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'El ID del pedido es obligatorio.',
            'order_id.integer'  => 'El ID del pedido debe ser un número entero.',
            'order_id.exists'   => 'El pedido especificado no existe en el sistema.',
            'status.required'   => 'El estado del pedido es obligatorio.',
            'status.string'     => 'El estado debe ser una cadena de texto.',
            'status.in'         => 'El estado indicado no es válido. Valores permitidos: pendiente, en_preparacion, listo, entregado, cancelado.',
        ];
    }
}
