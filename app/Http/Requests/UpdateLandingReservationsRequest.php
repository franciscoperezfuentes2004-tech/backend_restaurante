<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingReservationsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * ACCIÓN OBLIGATORIA: Aplicar strip_tags() a title, subtitle, description,
     * y a cada elemento del arreglo de policies. Es crucial para evitar inyecciones
     * XSS en la vista pública de reservaciones.
     */
    protected function prepareForValidation(): void
    {
        $rawSection = $this->input('reservaciones_seccion')
            ?? $this->input('reservacionesSeccion')
            ?? null;

        $rawHorarios = (is_array($rawSection) && isset($rawSection['horarios']) && is_array($rawSection['horarios']))
            ? $rawSection['horarios']
            : ($this->input('horarios') ?? []);

        $sanitizeText = function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value)));
        };

        $normalizeTime = function ($time) {
            if (!is_string($time)) {
                return $time;
            }
            $trimmed = trim($time);
            if ($trimmed === '') {
                return $trimmed;
            }
            // Formato 12 horas con AM/PM (ej: 1:00 PM, 11:30 AM, 12:00 AM, 2:00 am)
            if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(AM|PM)$/i', $trimmed, $m)) {
                $hours = (int) $m[1];
                $minutes = isset($m[2]) && $m[2] !== '' ? $m[2] : '00';
                $period = strtoupper($m[3]);
                if ($hours >= 1 && $hours <= 12 && (int)$minutes >= 0 && (int)$minutes <= 59) {
                    if ($period === 'AM') {
                        $hours = ($hours === 12) ? 0 : $hours;
                    } else {
                        $hours = ($hours === 12) ? 12 : $hours + 12;
                    }
                    return sprintf('%02d:%02d', $hours, (int)$minutes);
                }
            }
            // Formato 24 horas (ej: 9:00, 09:00, 18:00, 18:00:00)
            if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $trimmed, $m)) {
                $hours = (int) $m[1];
                $minutes = (int) $m[2];
                if ($hours >= 0 && $hours <= 23 && $minutes >= 0 && $minutes <= 59) {
                    return sprintf('%02d:%02d', $hours, $minutes);
                }
            }
            return $trimmed;
        };

        $patches = [];

        // 1. title / tituloPrincipal / titulo
        $rawTitle = $this->input('title')
            ?? $this->input('tituloPrincipal')
            ?? $this->input('titulo_principal')
            ?? $this->input('titulo')
            ?? (is_array($rawSection) ? ($rawSection['title'] ?? $rawSection['tituloPrincipal'] ?? $rawSection['titulo_principal'] ?? $rawSection['titulo'] ?? null) : null);

        if ($rawTitle !== null) {
            $patches['title'] = $sanitizeText($rawTitle);
            $patches['tituloPrincipal'] = $patches['title'];
        }

        // 2. subtitle / subtituloDorado / subtitulo
        $rawSubtitle = $this->input('subtitle')
            ?? $this->input('subtituloDorado')
            ?? $this->input('subtitulo_dorado')
            ?? $this->input('subtitulo')
            ?? (is_array($rawSection) ? ($rawSection['subtitle'] ?? $rawSection['subtituloDorado'] ?? $rawSection['subtitulo_dorado'] ?? $rawSection['subtitulo'] ?? null) : null);

        if ($rawSubtitle !== null) {
            $patches['subtitle'] = $sanitizeText($rawSubtitle);
            $patches['subtituloDorado'] = $patches['subtitle'];
        }

        // 3. description / textoDescriptivo / descripcion
        $rawDescription = $this->input('description')
            ?? $this->input('textoDescriptivo')
            ?? $this->input('texto_descriptivo')
            ?? $this->input('descripcion')
            ?? (is_array($rawSection) ? ($rawSection['description'] ?? $rawSection['textoDescriptivo'] ?? $rawSection['texto_descriptivo'] ?? $rawSection['descripcion'] ?? null) : null);

        if ($rawDescription !== null) {
            $patches['description'] = $sanitizeText($rawDescription);
            $patches['textoDescriptivo'] = $patches['description'];
        }

        // 4. Horarios (weekday_start, weekday_end, weekend_start, weekend_end)
        $rawWdStart = $this->input('weekday_start')
            ?? $this->input('lunesViernesInicio')
            ?? $this->input('lunes_viernes_inicio')
            ?? ($rawHorarios['weekday_start'] ?? $rawHorarios['lunesViernesInicio'] ?? $rawHorarios['lunes_viernes_inicio'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['weekday_start'] ?? null) : null);

        if ($rawWdStart !== null) {
            $patches['weekday_start'] = $normalizeTime($rawWdStart);
        }

        $rawWdEnd = $this->input('weekday_end')
            ?? $this->input('lunesViernesFin')
            ?? $this->input('lunes_viernes_fin')
            ?? ($rawHorarios['weekday_end'] ?? $rawHorarios['lunesViernesFin'] ?? $rawHorarios['lunes_viernes_fin'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['weekday_end'] ?? null) : null);

        if ($rawWdEnd !== null) {
            $patches['weekday_end'] = $normalizeTime($rawWdEnd);
        }

        $rawWeStart = $this->input('weekend_start')
            ?? $this->input('sabadoDomingoInicio')
            ?? $this->input('sabado_domingo_inicio')
            ?? ($rawHorarios['weekend_start'] ?? $rawHorarios['sabadoDomingoInicio'] ?? $rawHorarios['sabado_domingo_inicio'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['weekend_start'] ?? null) : null);

        if ($rawWeStart !== null) {
            $patches['weekend_start'] = $normalizeTime($rawWeStart);
        }

        $rawWeEnd = $this->input('weekend_end')
            ?? $this->input('sabadoDomingoFin')
            ?? $this->input('sabado_domingo_fin')
            ?? ($rawHorarios['weekend_end'] ?? $rawHorarios['sabadoDomingoFin'] ?? $rawHorarios['sabado_domingo_fin'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['weekend_end'] ?? null) : null);

        if ($rawWeEnd !== null) {
            $patches['weekend_end'] = $normalizeTime($rawWeEnd);
        }

        // 5. policies / politicas
        $rawPolicies = $this->input('policies')
            ?? $this->input('politicas')
            ?? (is_array($rawSection) ? ($rawSection['policies'] ?? $rawSection['politicas'] ?? null) : null);

        if (is_array($rawPolicies)) {
            $cleanedPolicies = [];
            foreach ($rawPolicies as $key => $policy) {
                $cleanedPolicies[$key] = $sanitizeText($policy);
            }
            $patches['policies'] = $cleanedPolicies;
            $patches['politicas'] = $cleanedPolicies;
        } elseif ($rawPolicies !== null) {
            $patches['policies'] = $rawPolicies;
            $patches['politicas'] = $rawPolicies;
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Reglas de Textos
            'title'         => 'required|string|min:3|max:100',
            'subtitle'      => 'required|string|min:3|max:100',
            'description'   => 'required|string|min:10|max:300',

            // Reglas de Horarios (Formato Estricto H:i)
            // (Nota arquitectónica: No usar after:start_time para los cierres nocturnos)
            'weekday_start' => 'required|date_format:H:i',
            'weekday_end'   => 'required|date_format:H:i',
            'weekend_start' => 'required|date_format:H:i',
            'weekend_end'   => 'required|date_format:H:i',

            // Reglas de Políticas (Validación Masiva)
            'policies'      => 'required|array|size:4',
            'policies.*'    => 'required|string|min:5|max:100',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'title.required'             => 'El título de la sección de reservaciones es obligatorio.',
            'title.string'               => 'El título debe ser una cadena de texto.',
            'title.min'                  => 'El título debe tener al menos 3 caracteres.',
            'title.max'                  => 'El título no puede exceder los 100 caracteres.',

            'subtitle.required'          => 'El subtítulo de la sección de reservaciones es obligatorio.',
            'subtitle.string'            => 'El subtítulo debe ser una cadena de texto.',
            'subtitle.min'               => 'El subtítulo debe tener al menos 3 caracteres.',
            'subtitle.max'               => 'El subtítulo no puede exceder los 100 caracteres.',

            'description.required'       => 'La descripción de la sección de reservaciones es obligatoria.',
            'description.string'         => 'La descripción debe ser una cadena de texto.',
            'description.min'            => 'La descripción debe tener al menos 10 caracteres.',
            'description.max'            => 'La descripción no puede exceder los 300 caracteres.',

            'weekday_start.required'     => 'La hora de apertura de lunes a viernes es obligatoria.',
            'weekday_start.date_format'  => 'La hora de apertura de lunes a viernes debe tener el formato HH:mm (ej. 13:00).',

            'weekday_end.required'       => 'La hora de cierre de lunes a viernes es obligatoria.',
            'weekday_end.date_format'    => 'La hora de cierre de lunes a viernes debe tener el formato HH:mm (ej. 23:00).',

            'weekend_start.required'     => 'La hora de apertura de fin de semana es obligatoria.',
            'weekend_start.date_format'  => 'La hora de apertura de fin de semana debe tener el formato HH:mm (ej. 12:00).',

            'weekend_end.required'       => 'La hora de cierre de fin de semana es obligatoria.',
            'weekend_end.date_format'    => 'La hora de cierre de fin de semana debe tener el formato HH:mm (ej. 02:00).',

            'policies.required'          => 'Las políticas de reservación son obligatorias.',
            'policies.array'             => 'Las políticas de reservación deben enviarse como un arreglo.',
            'policies.size'              => 'Debe configurar exactamente 4 políticas de reservación.',

            'policies.*.required'        => 'Cada política de reservación es obligatoria.',
            'policies.*.string'          => 'Cada política de reservación debe ser una cadena de texto.',
            'policies.*.min'             => 'Cada política debe tener al menos 5 caracteres.',
            'policies.*.max'             => 'Cada política no puede exceder los 100 caracteres.',
        ];
    }

    /**
     * Nombres de atributos personalizados para mensajes de validación.
     */
    public function attributes(): array
    {
        return [
            'title'         => 'título',
            'subtitle'      => 'subtítulo',
            'description'   => 'descripción',
            'weekday_start' => 'hora de apertura entre semana',
            'weekday_end'   => 'hora de cierre entre semana',
            'weekend_start' => 'hora de apertura de fin de semana',
            'weekend_end'   => 'hora de cierre de fin de semana',
            'policies'      => 'políticas de reservación',
            'policies.*'    => 'política de reservación',
        ];
    }
}
