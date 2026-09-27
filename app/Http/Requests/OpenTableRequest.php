<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OpenTableRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización previa y asignación estricta de mesero.
     */
    protected function prepareForValidation(): void
    {
        $tableId = $this->input('table_id') ?? $this->input('mesa_id') ?? $this->route('table_id') ?? $this->route('id');

        $mergeData = [
            'table_id'  => $tableId !== null ? (int) $tableId : null,
            // Asignar automáticamente el waiter_id usando auth()->id() para que el sistema
            // sepa quién abrió la cuenta, impidiendo que el frontend envíe un ID de mesero falso.
            'waiter_id' => auth()->id() ?? $this->user()?->id,
        ];

        if ($this->has('customer_name')) {
            $mergeData['customer_name'] = strip_tags(trim((string) $this->input('customer_name')));
        }

        if ($this->has('notes')) {
            $mergeData['notes'] = strip_tags(trim((string) $this->input('notes')));
        }

        $this->merge($mergeData);
    }

    /**
     * Reglas de validación.
     */
    public function rules(): array
    {
        return [
            'table_id'         => ['required', 'integer', 'exists:tables,id'],
            'customer_name'    => ['nullable', 'string', 'max:100'],
            'notes'            => ['nullable', 'string', 'max:255'],
            'items'            => ['nullable', 'array'],
            'items.*.dish_id'  => ['required_with:items', 'integer', 'exists:dishes,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1'],
            'items.*.notes'    => ['nullable', 'string', 'max:255'],
            'items.*.extras'   => ['nullable', 'array'],
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'table_id.required' => 'La mesa es obligatoria.',
            'table_id.integer'  => 'El identificador de la mesa debe ser un número entero.',
            'table_id.exists'   => 'La mesa seleccionada no existe.',
        ];
    }
}
