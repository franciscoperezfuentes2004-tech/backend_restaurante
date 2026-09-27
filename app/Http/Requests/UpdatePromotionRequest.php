<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePromotionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitiza y normaliza los datos antes de validar.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        if ($this->has('name')) {
            $patches['name'] = trim(strip_tags($this->name));
        }
        if ($this->has('mensaje_banner')) {
            $patches['mensaje_banner'] = trim(strip_tags($this->mensaje_banner));
        }
        if ($this->has('benefit')) {
            $patches['benefit'] = trim(strip_tags($this->benefit));
        }
        if ($this->has('has_date_range')) {
            $patches['has_date_range'] = filter_var($this->has_date_range, FILTER_VALIDATE_BOOLEAN);
        }
        if ($this->has('has_time_range')) {
            $patches['has_time_range'] = filter_var($this->has_time_range, FILTER_VALIDATE_BOOLEAN);
        }
        if ($this->has('is_active')) {
            $patches['is_active'] = filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN);
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        // Obtiene el ID de la promoción desde la ruta ({promotion} o {id})
        $promotionId = $this->route('promotion') ?? $this->route('id');

        return [
            // ── Información básica ──────────────────────────────────────────
            'name' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('promotions', 'name')->ignore($promotionId),
            ],
            'type'   => [
                'sometimes',
                'required',
                'string',
                Rule::in(['2x1', '3x2', '3x1', 'fixed', 'combo', 'personalizado']),
            ],
            'scheme'         => 'sometimes|nullable|string|max:50',
            'benefit'        => 'sometimes|nullable|string|max:255',
            'mensaje_banner' => 'sometimes|nullable|string|max:255',
            'aplica_en'      => ['sometimes', 'nullable', 'string', Rule::in(['pedidos', 'reservaciones', 'ambos'])],

            // ── Productos aplicables ────────────────────────────────────────
            'applicable_products'   => 'sometimes|required|array|min:1',
            'applicable_products.*' => 'integer|exists:dishes,id',

            // ── Días de vigencia ────────────────────────────────────────────
            'valid_days'   => 'sometimes|required|array|min:1|max:7',
            'valid_days.*' => ['string', 'distinct', Rule::in(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])],

            // ── Rango de fechas ─────────────────────────────────────────────
            'has_date_range' => 'sometimes|boolean',
            'start_date'     => 'sometimes|required_if:has_date_range,true|nullable|date',
            'end_date'       => 'sometimes|required_if:has_date_range,true|nullable|date|after_or_equal:start_date',

            // ── Rango de horario ────────────────────────────────────────────
            'has_time_range' => 'sometimes|boolean',
            'start_time'     => 'sometimes|required_if:has_time_range,true|nullable|date_format:H:i',
            'end_time'       => 'sometimes|required_if:has_time_range,true|nullable|date_format:H:i|after:start_time',

            // ── Estado ──────────────────────────────────────────────────────
            'is_active' => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                => 'El nombre de la promoción es obligatorio.',
            'name.min'                     => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'                     => 'El nombre no debe superar los 100 caracteres.',
            'name.unique'                  => 'Ya existe una promoción con este nombre.',
            'type.required'                => 'El tipo de promoción es obligatorio.',
            'type.in'                      => 'El tipo debe ser uno de: 2x1, 3x2, 3x1, fixed, combo, personalizado.',
            'applicable_products.required' => 'Debes seleccionar al menos un producto.',
            'applicable_products.min'      => 'Debes seleccionar al menos un producto.',
            'applicable_products.*.exists' => 'Uno o más platillos seleccionados no existen.',
            'valid_days.required'          => 'Debes seleccionar al menos un día de vigencia.',
            'valid_days.min'               => 'Debes seleccionar al menos un día de vigencia.',
            'valid_days.max'               => 'No puedes seleccionar más de 7 días.',
            'valid_days.*.in'              => 'Los días válidos son: Lun, Mar, Mié, Jue, Vie, Sáb, Dom.',
            'valid_days.*.distinct'        => 'No puedes repetir el mismo día.',
            'start_date.required_if'       => 'La fecha de inicio es obligatoria cuando hay rango de fechas.',
            'end_date.required_if'         => 'La fecha de fin es obligatoria cuando hay rango de fechas.',
            'end_date.after_or_equal'      => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'start_time.required_if'       => 'La hora de inicio es obligatoria cuando hay rango de horario.',
            'start_time.date_format'       => 'La hora de inicio debe tener el formato HH:MM.',
            'end_time.required_if'         => 'La hora de fin es obligatoria cuando hay rango de horario.',
            'end_time.date_format'         => 'La hora de fin debe tener el formato HH:MM.',
            'end_time.after'               => 'La hora de fin debe ser posterior a la hora de inicio.',
        ];
    }
}
