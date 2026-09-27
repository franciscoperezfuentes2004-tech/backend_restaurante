<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReportScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * - Aplicar strip_tags($this->name) para mantener el registro limpio de XSS.
     * - Sincronizar alias de campos (report_type <-> type, send_time <-> time,
     *   export_format <-> format, recipients <-> emails).
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar nombre contra XSS
        if ($this->has('name')) {
            $patches['name'] = trim(strip_tags((string) $this->name));
        }

        // 2. Normalizar report_type <-> type
        if ($this->has('report_type') && !$this->has('type')) {
            $patches['type'] = $this->report_type;
        } elseif ($this->has('type') && !$this->has('report_type')) {
            $patches['report_type'] = $this->type;
        }

        // 3. Normalizar send_time <-> time
        if ($this->has('send_time') && !$this->has('time')) {
            $patches['time'] = $this->send_time;
        } elseif ($this->has('time') && !$this->has('send_time')) {
            $patches['send_time'] = $this->time;
        }

        // 4. Normalizar export_format <-> format
        if ($this->has('export_format') && !$this->has('format')) {
            $patches['format'] = $this->export_format;
        } elseif ($this->has('format') && !$this->has('export_format')) {
            $patches['export_format'] = $this->format;
        }

        // 5. Normalizar recipients <-> emails
        if ($this->has('recipients') && !$this->has('emails')) {
            $patches['emails'] = $this->recipients;
        } elseif ($this->has('emails') && !$this->has('recipients')) {
            $patches['recipients'] = $this->emails;
        }

        // 6. Normalizar is_active <-> active
        if ($this->has('is_active') && !$this->has('active')) {
            $patches['active'] = $this->is_active;
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Nombre del reporte ─────────────────────────────────────────
            'name'             => 'sometimes|required|string|min:3|max:100',

            // ── Tipo de reporte ────────────────────────────────────────────
            'report_type'      => [
                'sometimes',
                'required',
                'string',
                Rule::in([
                    'sales', 'inventory', 'cuts', 'reviews',
                    'ventas', 'inventario', 'cortes', 'ejecutivo', 'productos',
                    'delivery', 'reservaciones', 'costos', 'finanzas', 'impuestos'
                ]),
            ],
            'type'             => [
                'sometimes',
                'string',
                Rule::in([
                    'sales', 'inventory', 'cuts', 'reviews',
                    'ventas', 'inventario', 'cortes', 'ejecutivo', 'productos',
                    'delivery', 'reservaciones', 'costos', 'finanzas', 'impuestos'
                ]),
            ],

            // ── Frecuencia ─────────────────────────────────────────────────
            'frequency'        => [
                'sometimes',
                'required',
                'string',
                Rule::in(['daily', 'weekly', 'monthly', 'diario', 'semanal', 'mensual']),
            ],

            // ── Hora de envío (formato H:i) ────────────────────────────────
            'send_time'        => 'sometimes|required|date_format:H:i',
            'time'             => 'sometimes|date_format:H:i',

            // ── Formato de exportación ─────────────────────────────────────
            'export_format'    => [
                'sometimes',
                'required',
                'string',
                Rule::in(['pdf', 'excel', 'csv', 'PDF', 'Excel', 'CSV']),
            ],
            'format'           => [
                'sometimes',
                'string',
                Rule::in(['pdf', 'excel', 'csv', 'PDF', 'Excel', 'CSV']),
            ],

            // ── Destinatarios (mínimo 1, máximo 10 correos válidos) ────────
            'recipients'       => 'sometimes|required|array|min:1|max:10',
            'recipients.*'     => 'required|email:rfc,dns',
            'emails'           => 'sometimes|array|min:1|max:10',
            'emails.*'         => 'sometimes|email:rfc,dns',

            // ── Campos complementarios ─────────────────────────────────────
            'active'           => 'nullable|boolean',
            'day_of_week'      => 'nullable|string|max:20',
            'day_of_month'     => 'nullable|integer|between:1,31',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'             => 'El nombre de la programación es obligatorio.',
            'name.min'                  => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'                  => 'El nombre no debe superar los 100 caracteres.',
            'report_type.required'      => 'El tipo de reporte es obligatorio.',
            'report_type.in'            => 'El tipo de reporte debe ser: sales, inventory, cuts o reviews.',
            'type.required'             => 'El tipo de reporte es obligatorio.',
            'type.in'                   => 'El tipo de reporte debe ser: sales, inventory, cuts o reviews.',
            'frequency.required'        => 'La frecuencia de envío es obligatoria.',
            'frequency.in'              => 'La frecuencia debe ser: daily, weekly o monthly.',
            'send_time.required'        => 'La hora de envío es obligatoria.',
            'send_time.date_format'     => 'La hora de envío debe tener el formato HH:mm (ej. 23:30).',
            'time.required'             => 'La hora de envío es obligatoria.',
            'time.date_format'          => 'La hora de envío debe tener el formato HH:mm (ej. 23:30).',
            'export_format.required'    => 'El formato de exportación es obligatorio.',
            'export_format.in'          => 'El formato debe ser: pdf, excel o csv.',
            'format.required'           => 'El formato de exportación es obligatorio.',
            'format.in'                 => 'El formato debe ser: pdf, excel o csv.',
            'recipients.required'       => 'Debe especificar al menos un destinatario.',
            'recipients.array'          => 'Los destinatarios deben enviarse como una lista.',
            'recipients.min'            => 'Debe especificar al menos 1 correo destinatario.',
            'recipients.max'            => 'No puede configurar más de 10 correos destinatarios por programación.',
            'recipients.*.required'     => 'Cada destinatario es obligatorio.',
            'recipients.*.email'        => 'Uno o más correos destinatarios no tienen una estructura o dominio válido.',
            'emails.required'           => 'Debe especificar al menos un destinatario.',
            'emails.array'              => 'Los destinatarios deben enviarse como una lista.',
            'emails.min'                => 'Debe especificar al menos 1 correo destinatario.',
            'emails.max'                => 'No puede configurar más de 10 correos destinatarios por programación.',
            'emails.*.required'         => 'Cada destinatario es obligatorio.',
            'emails.*.email'            => 'Uno o más correos destinatarios no tienen una estructura o dominio válido.',
        ];
    }
}
