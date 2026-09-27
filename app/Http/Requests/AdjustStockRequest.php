<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * Aplicar strip_tags a notes para prevenir cualquier inyección XSS.
     * Sincronizar alias de campos entre frontend y backend:
     * - movement_type <-> type
     * - expiration_date <-> expiry_date
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar observaciones (XSS)
        if ($this->has('notes')) {
            $patches['notes'] = trim(strip_tags((string) $this->notes));
        }

        // 2. Normalizar movement_type / type
        if ($this->has('movement_type') && !$this->has('type')) {
            $patches['type'] = $this->movement_type;
        } elseif ($this->has('type') && !$this->has('movement_type')) {
            $patches['movement_type'] = $this->type;
        }

        // 3. Normalizar expiration_date / expiry_date
        if ($this->has('expiration_date') && !$this->has('expiry_date')) {
            $patches['expiry_date'] = $this->expiration_date;
        } elseif ($this->has('expiry_date') && !$this->has('expiration_date')) {
            $patches['expiration_date'] = $this->expiry_date;
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Ingrediente ────────────────────────────────────────────────
            'ingredient_id'   => 'required|integer|exists:ingredients,id',

            // ── Tipo de Movimiento ─────────────────────────────────────────
            // Acepta tanto las claves en inglés (manual_adjustment, waste)
            // como las nativas del esquema (ajuste, merma, salida, entrada)
            'movement_type'   => [
                'required',
                'string',
                Rule::in(['manual_adjustment', 'waste', 'ajuste', 'merma', 'salida', 'entrada']),
            ],
            'type'            => [
                'sometimes',
                'string',
                Rule::in(['manual_adjustment', 'waste', 'ajuste', 'merma', 'salida', 'entrada']),
            ],

            // ── Cantidad ───────────────────────────────────────────────────
            'quantity'        => 'required|numeric|min:0.01|max:99999.99',

            // ── Fecha de Vencimiento ───────────────────────────────────────
            // Evita registrar insumos que matemáticamente ya caducaron
            'expiration_date' => 'nullable|date|after_or_equal:today',
            'expiry_date'     => 'nullable|date|after_or_equal:today',

            // ── Observaciones ──────────────────────────────────────────────
            'notes'           => 'nullable|string|max:250',

            // ── Parámetro opcional para ajuste absoluto ────────────────────
            'is_absolute'     => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'ingredient_id.required'          => 'Debe seleccionar un ingrediente válido.',
            'ingredient_id.integer'           => 'El identificador del ingrediente debe ser un número entero.',
            'ingredient_id.exists'            => 'El ingrediente seleccionado no existe en el inventario.',
            'movement_type.required'          => 'El tipo de movimiento es obligatorio.',
            'movement_type.in'                => 'El tipo de movimiento debe ser ajuste manual o merma.',
            'type.required'                   => 'El tipo de movimiento es obligatorio.',
            'type.in'                         => 'El tipo de movimiento debe ser ajuste manual o merma.',
            'quantity.required'               => 'La cantidad es obligatoria.',
            'quantity.numeric'                => 'La cantidad debe ser un valor numérico.',
            'quantity.min'                    => 'La cantidad mínima es 0.01.',
            'quantity.max'                    => 'La cantidad no debe superar 99999.99.',
            'expiration_date.date'            => 'La fecha de vencimiento no tiene un formato válido.',
            'expiration_date.after_or_equal'  => 'La fecha de vencimiento debe ser igual o posterior a hoy.',
            'expiry_date.date'                => 'La fecha de vencimiento no tiene un formato válido.',
            'expiry_date.after_or_equal'      => 'La fecha de vencimiento debe ser igual o posterior a hoy.',
            'notes.max'                       => 'Las notas u observaciones no deben superar los 250 caracteres.',
        ];
    }
}
