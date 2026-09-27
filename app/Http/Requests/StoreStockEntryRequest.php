<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStockEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * Aplicar strip_tags al campo notes.
     * Sincronizar alias de campos:
     * - total_cost <-> cost_total
     * - expiration_date <-> expiry_date
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar observaciones (XSS)
        if ($this->has('notes')) {
            $patches['notes'] = trim(strip_tags((string) $this->notes));
        }

        // 2. Normalizar total_cost / cost_total
        if ($this->has('total_cost') && !$this->has('cost_total')) {
            $patches['cost_total'] = $this->total_cost;
        } elseif ($this->has('cost_total') && !$this->has('total_cost')) {
            $patches['total_cost'] = $this->cost_total;
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
            // ── Proveedor ──────────────────────────────────────────────────
            'supplier_id'     => 'required|integer|exists:suppliers,id',

            // ── Ingrediente (Regla Avanzada) ───────────────────────────────
            // Confirma que el ingredient_id realmente pertenece al supplier_id
            // enviado, bloqueando intentos de asociar insumos a proveedores incorrectos.
            'ingredient_id'   => [
                'required',
                'integer',
                Rule::exists('ingredients', 'id')->where(function ($query) {
                    $query->where('supplier_id', $this->supplier_id);
                }),
            ],

            // ── Cantidad ───────────────────────────────────────────────────
            'quantity'        => 'required|numeric|min:0.01|max:99999.99',

            // ── Costo Total (se permite 0 para bonificaciones / muestras) ──
            'total_cost'      => 'required|numeric|min:0|max:999999.99',
            'cost_total'      => 'sometimes|numeric|min:0|max:999999.99',

            // ── Costo por unidad opcional (se recalcula en el servidor) ────
            'cost_per_unit'   => 'nullable|numeric|min:0',

            // ── Fecha de Vencimiento ───────────────────────────────────────
            'expiration_date' => 'nullable|date|after_or_equal:today',
            'expiry_date'     => 'nullable|date|after_or_equal:today',

            // ── Observaciones ──────────────────────────────────────────────
            'notes'           => 'nullable|string|max:250',
        ];
    }

    public function messages(): array
    {
        return [
            'supplier_id.required'           => 'El proveedor es obligatorio.',
            'supplier_id.integer'            => 'El identificador del proveedor debe ser un número entero.',
            'supplier_id.exists'             => 'El proveedor seleccionado no existe o fue eliminado.',
            'ingredient_id.required'         => 'Debe seleccionar un ingrediente válido.',
            'ingredient_id.integer'          => 'El identificador del ingrediente debe ser un número entero.',
            'ingredient_id.exists'           => 'El ingrediente seleccionado no pertenece al proveedor especificado o no existe.',
            'quantity.required'              => 'La cantidad ingresada es obligatoria.',
            'quantity.numeric'               => 'La cantidad debe ser un valor numérico.',
            'quantity.min'                   => 'La cantidad mínima es 0.01.',
            'quantity.max'                   => 'La cantidad no debe superar 99999.99.',
            'total_cost.required'            => 'El costo total es obligatorio.',
            'total_cost.numeric'             => 'El costo total debe ser un valor numérico.',
            'total_cost.min'                 => 'El costo total no puede ser negativo.',
            'total_cost.max'                 => 'El costo total no debe superar 999999.99.',
            'cost_total.required'            => 'El costo total es obligatorio.',
            'cost_total.numeric'             => 'El costo total debe ser un valor numérico.',
            'cost_total.min'                 => 'El costo total no puede ser negativo.',
            'cost_total.max'                 => 'El costo total no debe superar 999999.99.',
            'expiration_date.date'           => 'La fecha de vencimiento no tiene un formato válido.',
            'expiration_date.after_or_equal' => 'La fecha de vencimiento debe ser igual o posterior a hoy.',
            'expiry_date.date'               => 'La fecha de vencimiento no tiene un formato válido.',
            'expiry_date.after_or_equal'     => 'La fecha de vencimiento debe ser igual o posterior a hoy.',
            'notes.max'                      => 'Las notas u observaciones no deben superar los 250 caracteres.',
        ];
    }
}
