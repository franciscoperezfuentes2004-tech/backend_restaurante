<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexIngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * Aplicar strip_tags para destruir cualquier etiqueta HTML o script
     * malicioso antes de que toque la base de datos o los logs.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('search')) {
            $this->merge([
                'search' => trim(strip_tags((string) $this->search)),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            // ── Búsqueda de texto libre ────────────────────────────────────
            'search'      => 'nullable|string|max:100',

            // ── Filtro por proveedor ───────────────────────────────────────
            'supplier_id' => 'nullable|integer|exists:suppliers,id',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max'          => 'El término de búsqueda no debe superar los 100 caracteres.',
            'supplier_id.integer' => 'El identificador del proveedor debe ser un número entero.',
            'supplier_id.exists'  => 'El proveedor seleccionado no existe o fue eliminado.',
        ];
    }
}
