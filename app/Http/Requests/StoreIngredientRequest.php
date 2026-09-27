<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreIngredientRequest extends FormRequest
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

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Nombre del ingrediente ─────────────────────────────────────
            // Único para evitar duplicados como "Tomate Bola" x2
            'name'            => [
                'required',
                'string',
                'min:2',
                'max:100',
                Rule::unique('ingredients', 'name'),
            ],

            // ── Proveedor (FK opcional) ────────────────────────────────────
            // Rechaza IDs de proveedores eliminados o inventados
            'supplier_id'     => 'nullable|integer|exists:suppliers,id',

            // ── Categoría (FK obligatoria) ─────────────────────────────────
            // Tabla real: ingredient_categories
            'category_id'     => 'required|integer|exists:ingredient_categories,id',

            // ── Unidad de medida (lista cerrada) ──────────────────────────
            // Si en el futuro se migra a una tabla dinámica, cambiar a:
            // Rule::exists('units_of_measure', 'code')
            'unit_of_measure' => [
                'required',
                'string',
                Rule::in(['kg', 'g', 'L', 'ml', 'piezas', 'cajas', 'bolsas', 'latas']),
            ],

            // ── Notas (texto libre opcional) ──────────────────────────────
            'notes'           => 'nullable|string|max:250',
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
            'notes.max'                => 'Las notas no deben superar los 250 caracteres.',
        ];
    }
}
