<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza los parámetros de búsqueda antes de validar.
     * Previene que tags HTML queden registrados en logs o reflejados en la vista.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // Limpiar XSS del campo de búsqueda
        if ($this->has('search')) {
            $patches['search'] = trim(strip_tags((string) $this->search));
        }

        // Normalizar boolean solo cuando el valor enviado es reconocible.
        // Si el valor no es convertible (ej. 'maybe'), lo dejamos intacto
        // para que la regla `boolean` del validador lo rechace con 422.
        if ($this->has('has_images')) {
            $raw = $this->has_images;
            $recognizedTrue  = ['true',  '1', 'yes', 'on',  true,  1];
            $recognizedFalse = ['false', '0', 'no',  'off', false, 0];

            if (in_array($raw, $recognizedTrue, true) || in_array(strtolower((string) $raw), ['true', '1', 'yes', 'on'], true)) {
                $patches['has_images'] = true;
            } elseif (in_array($raw, $recognizedFalse, true) || in_array(strtolower((string) $raw), ['false', '0', 'no', 'off'], true)) {
                $patches['has_images'] = false;
            }
            // Si no es reconocible, no tocamos el valor → el validador devolverá 422
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Búsqueda por nombre o texto libre ──────────────────────────
            'nombre'     => 'nullable|string|max:100',
            'search'     => 'nullable|string|max:100',
            'per_page'   => 'nullable|integer|min:1|max:100',

            // ── Filtro de calificación (1–5 estrellas) ─────────────────────
            // Bloquea enteros fuera del rango y valores no numéricos
            'rating'     => 'nullable|integer|between:1,5',

            // ── Filtro de estado (columna: status) ─────────────────────────
            // Valores exactos de BD: pendiente, aprobada, oculta, respondida, reportada
            'state'      => [
                'nullable',
                'string',
                Rule::in(['pendiente', 'aprobada', 'oculta', 'respondida', 'reportada']),
            ],

            // ── Filtro de origen (columna: origen) ─────────────────────────
            // Valores exactos de BD: consumo, delivery, reservacion
            'origin'     => [
                'nullable',
                'string',
                Rule::in(['consumo', 'delivery', 'reservacion']),
            ],

            // ── Interruptor: con imágenes (columna: relación images) ───────
            'has_images' => 'nullable|boolean',

            // ── Rango de fechas ────────────────────────────────────────────
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date|after_or_equal:start_date',
        ];
    }

    public function messages(): array
    {
        return [
            'search.max'              => 'El término de búsqueda no debe superar los 100 caracteres.',
            'rating.integer'          => 'La calificación debe ser un número entero.',
            'rating.between'          => 'La calificación debe estar entre 1 y 5 estrellas.',
            'state.in'                => 'El estado debe ser: pendiente, aprobada, oculta, respondida o reportada.',
            'origin.in'               => 'El origen debe ser: consumo, delivery o reservacion.',
            'has_images.boolean'      => 'El filtro de imágenes debe ser verdadero o falso.',
            'start_date.date'         => 'La fecha de inicio no tiene un formato válido.',
            'end_date.date'           => 'La fecha de fin no tiene un formato válido.',
            'end_date.after_or_equal' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
        ];
    }
}
