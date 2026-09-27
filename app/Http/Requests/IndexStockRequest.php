<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * Utilizar strip_tags($this->search) para eliminar etiquetas HTML y
     * evitar inyecciones XSS si la búsqueda se refleja en el frontend o se guarda en logs.
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

            // ── Filtros opcionales de catálogo y estado ────────────────────
            'category'    => 'nullable|string|max:100',
            'supplier_id' => 'nullable',
            'fecha'       => 'nullable|date',
            'estado'      => 'nullable|string|max:50',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max' => 'El término de búsqueda no debe superar los 100 caracteres.',
            'fecha.date' => 'La fecha de filtro no tiene un formato válido.',
        ];
    }
}
