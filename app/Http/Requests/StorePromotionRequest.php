<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePromotionRequest extends FormRequest
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
        $this->merge([
            'name'           => isset($this->name)
                ? trim(strip_tags($this->name))
                : $this->name,
            'mensaje_banner' => isset($this->mensaje_banner)
                ? trim(strip_tags($this->mensaje_banner))
                : $this->mensaje_banner,
            'benefit'        => isset($this->benefit)
                ? trim(strip_tags($this->benefit))
                : $this->benefit,
            // Normalizar booleanos enviados como string '1'/'0'/'true'/'false'
            'has_date_range' => filter_var($this->has_date_range ?? false, FILTER_VALIDATE_BOOLEAN),
            'has_time_range' => filter_var($this->has_time_range ?? false, FILTER_VALIDATE_BOOLEAN),
            'is_active'      => filter_var($this->is_active ?? true,  FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function rules(): array
    {
        return [
            // ── Información básica ──────────────────────────────────────────
            'name'    => [
                'required',
                'string',
                'min:3',
                'max:100',
                Rule::unique('promotions', 'name'),
            ],
            'type'   => [
                'required',
                'string',
                Rule::in(['2x1', '3x2', '3x1', 'fixed', 'combo', 'personalizado']),
            ],
            'scheme'         => 'nullable|string|max:50',
            'benefit'        => 'nullable|string|max:255',
            'mensaje_banner' => 'nullable|string|max:255',
            'aplica_en'      => ['nullable', 'string', Rule::in(['pedidos', 'reservaciones', 'ambos'])],

            // ── Productos aplicables ────────────────────────────────────────
            'applicable_products'   => 'required|array|min:1',
            'applicable_products.*' => 'integer|exists:dishes,id',

            // ── Días de vigencia ────────────────────────────────────────────
            'valid_days'   => 'required|array|min:1|max:7',
            'valid_days.*' => ['string', 'distinct', Rule::in(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'])],

            // ── Rango de fechas (controlado por has_date_range) ─────────────
            'has_date_range' => 'required|boolean',
            'start_date'     => 'required_if:has_date_range,true|nullable|date',
            'end_date'       => 'required_if:has_date_range,true|nullable|date|after_or_equal:start_date',

            // ── Rango de horario (controlado por has_time_range) ────────────
            'has_time_range' => 'required|boolean',
            'start_time'     => 'required_if:has_time_range,true|nullable|date_format:H:i',
            'end_time'       => 'required_if:has_time_range,true|nullable|date_format:H:i|after:start_time',

            // ── Estado ──────────────────────────────────────────────────────
            'is_active' => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'El nombre de la promoción es obligatorio.',
            'name.min'                    => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'                    => 'El nombre no debe superar los 100 caracteres.',
            'name.unique'                 => 'Ya existe una promoción con este nombre.',
            'type.required'               => 'El tipo de promoción es obligatorio.',
            'type.in'                     => 'El tipo debe ser uno de: 2x1, 3x2, 3x1, fixed, combo, personalizado.',
            'applicable_products.required' => 'Debes seleccionar al menos un producto.',
            'applicable_products.min'     => 'Debes seleccionar al menos un producto.',
            'applicable_products.*.exists' => 'Uno o más platillos seleccionados no existen.',
            'valid_days.required'         => 'Debes seleccionar al menos un día de vigencia.',
            'valid_days.min'              => 'Debes seleccionar al menos un día de vigencia.',
            'valid_days.max'              => 'No puedes seleccionar más de 7 días.',
            'valid_days.*.in'             => 'Los días válidos son: Lun, Mar, Mié, Jue, Vie, Sáb, Dom.',
            'valid_days.*.distinct'       => 'No puedes repetir el mismo día.',
            'has_date_range.required'     => 'Debes indicar si la promoción tiene rango de fechas.',
            'start_date.required_if'      => 'La fecha de inicio es obligatoria cuando hay rango de fechas.',
            'end_date.required_if'        => 'La fecha de fin es obligatoria cuando hay rango de fechas.',
            'end_date.after_or_equal'     => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            'has_time_range.required'     => 'Debes indicar si la promoción tiene rango de horario.',
            'start_time.required_if'      => 'La hora de inicio es obligatoria cuando hay rango de horario.',
            'start_time.date_format'      => 'La hora de inicio debe tener el formato HH:MM.',
            'end_time.required_if'        => 'La hora de fin es obligatoria cuando hay rango de horario.',
            'end_time.date_format'        => 'La hora de fin debe tener el formato HH:MM.',
            'end_time.after'              => 'La hora de fin debe ser posterior a la hora de inicio.',
            'is_active.required'          => 'El campo de estado activo/inactivo es obligatorio.',
        ];
    }
}
