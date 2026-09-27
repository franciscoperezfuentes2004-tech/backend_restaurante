<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AssignDeliveryOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado para realizar esta solicitud.
     * Restricción Quirúrgica de Seguridad: Únicamente los usuarios con el rol exacto
     * de 'repartidor' pueden autoasignarse pedidos en calle.
     * Ni cajeros, meseros, cocineros ni administradores pueden autoasignarse pedidos.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        $role = strtolower($user->role ?? '');
        return in_array($role, ['repartidor', 'driver'], true);
    }

    /**
     * Sanitización previa a la validación:
     * - strip_tags() para eliminar cualquier etiqueta o inyección HTML/XSS.
     * - strtoupper(trim()) para normalizar códigos de folio o tokens alfanuméricos.
     */
    protected function prepareForValidation(): void
    {
        $rawToken = $this->token
            ?? $this->route('token')
            ?? $this->input('code')
            ?? $this->input('folio')
            ?? $this->input('order_id');

        if ($rawToken !== null) {
            $cleaned = strip_tags((string) $rawToken);
            $cleaned = strtoupper(trim($cleaned));
            $this->merge(['token' => $cleaned]);
        }
    }

    /**
     * Reglas de validación para el token del pedido.
     * Soporta folios cortos (ej: '29') o tokens largos (ej: 'X8A-9BQ').
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'regex:/^[A-Z0-9\-]+$/', 'max:50'],
        ];
    }

    /**
     * Mensajes de validación personalizados en español.
     */
    public function messages(): array
    {
        return [
            'token.required' => 'El token o código del pedido es obligatorio.',
            'token.string'   => 'El token debe ser una cadena de texto válida.',
            'token.regex'    => 'El token tiene un formato inválido. Solo se permiten letras mayúsculas, números y guiones.',
            'token.max'      => 'El token no debe exceder los 50 caracteres.',
        ];
    }
}
