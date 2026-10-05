<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza los campos de texto antes de validar.
     * Previene XSS en name y notes.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        if ($this->has('name')) {
            $patches['name'] = trim(strip_tags((string) $this->name));
        }

        if ($this->has('notes')) {
            $patches['notes'] = trim(strip_tags((string) $this->notes));
        }

        if ($this->has('min_stock')) {
            $val = $this->min_stock;
            $patches['min_stock'] = ($val === '' || $val === null) ? 0.0 : $val;
        }

        if ($this->has('stock_minimo')) {
            $val = $this->stock_minimo;
            $patches['stock_minimo'] = ($val === '' || $val === null) ? 0.0 : $val;
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        // Obtiene el ID del ingrediente desde la ruta ({ingredient} o {id})
        $ingredientId = $this->route('ingredient') ?? $this->route('id');

        return [
            // ── Nombre (unique ignora el ID propio) ────────────────────────
            'name'            => [
                'sometimes',
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('ingredients', 'name')->ignore($ingredientId),
            ],

            // ── Proveedor (FK opcional) ────────────────────────────────────
            'supplier_id'     => 'sometimes|nullable|integer|exists:suppliers,id',

            // ── Categoría (FK obligatoria) ─────────────────────────────────
            'category_id'     => 'sometimes|required|integer|exists:ingredient_categories,id',

            // ── Unidad de medida (lista cerrada) ──────────────────────────
            'unit_of_measure' => [
                'sometimes',
                'required',
                'string',
                Rule::in(['kg', 'g', 'L', 'ml', 'piezas', 'cajas', 'bolsas', 'latas']),
            ],

            // ── Stock Mínimo (Umbral para alertas de stock bajo) ───────────
            'min_stock'       => 'sometimes|nullable|numeric|gte:0|max:999999.99',
            'stock_minimo'    => 'sometimes|nullable|numeric|gte:0|max:999999.99',

            // ── Notas ──────────────────────────────────────────────────────
            'notes'           => 'sometimes|nullable|string|max:250',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'            => 'El nombre del ingrediente es obligatorio.',
            'name.min'                 => 'El nombre debe tener al menos 2 caracteres.',
            'name.max'                 => 'El nombre no debe superar los 100 caracteres.',
            'name.unique'              => 'Ya existe un ingrediente con este nombre.',
            'supplier_id.integer'      => 'El proveedor debe ser un identificador numérico.',
            'supplier_id.exists'       => 'El proveedor seleccionado no existe o fue eliminado.',
            'category_id.required'     => 'La categoría es obligatoria.',
            'category_id.integer'      => 'La categoría debe ser un identificador numérico.',
            'category_id.exists'       => 'La categoría seleccionada no existe.',
            'unit_of_measure.required' => 'La unidad de medida es obligatoria.',
            'unit_of_measure.in'       => 'La unidad de medida debe ser: kg, g, L, ml, piezas, cajas, bolsas o latas.',
            'min_stock.numeric'        => 'El stock mínimo debe ser un número válido.',
            'min_stock.gte'            => 'El stock mínimo no puede ser negativo.',
            'min_stock.max'            => 'El stock mínimo no puede superar 999,999.99.',
            'stock_minimo.numeric'     => 'El stock mínimo debe ser un número válido.',
            'stock_minimo.gte'         => 'El stock mínimo no puede ser negativo.',
            'stock_minimo.max'         => 'El stock mínimo no puede superar 999,999.99.',
            'notes.max'                => 'Las notas no deben superar los 250 caracteres.',
        ];
    }
}
