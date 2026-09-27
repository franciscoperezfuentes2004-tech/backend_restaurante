<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryZoneRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa:
     * ACCIÓN: strip_tags en todos los campos de texto para evitar que inyecten scripts en las direcciones.
     * Sincronizar alias de campos entre inglés y español.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar campos de texto contra XSS
        $textFields = [
            'city', 'municipality', 'state', 'street', 'zip_code',
            'ciudad', 'municipio', 'estado', 'calle', 'street_name',
            'postal_code', 'codigo_postal'
        ];

        foreach ($textFields as $field) {
            if ($this->has($field)) {
                $raw = $this->input($field);
                if (is_string($raw)) {
                    $cleaned = trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw)));
                    $patches[$field] = $cleaned;
                }
            }
        }

        // 2. Sincronizar city <-> ciudad
        if (isset($patches['city']) && !isset($patches['ciudad'])) {
            $patches['ciudad'] = $patches['city'];
        } elseif (isset($patches['ciudad']) && !isset($patches['city'])) {
            $patches['city'] = $patches['ciudad'];
        }

        // 3. Sincronizar municipality <-> municipio
        if (isset($patches['municipality']) && !isset($patches['municipio'])) {
            $patches['municipio'] = $patches['municipality'];
        } elseif (isset($patches['municipio']) && !isset($patches['municipality'])) {
            $patches['municipality'] = $patches['municipio'];
        }

        // 4. Sincronizar state <-> estado
        if (isset($patches['state']) && !isset($patches['estado'])) {
            $patches['estado'] = $patches['state'];
        } elseif (isset($patches['estado']) && !isset($patches['state'])) {
            $patches['state'] = $patches['estado'];
        }

        // 5. Sincronizar street <-> street_name / calle
        if (isset($patches['street'])) {
            if (!isset($patches['street_name'])) $patches['street_name'] = $patches['street'];
            if (!isset($patches['calle']))       $patches['calle'] = $patches['street'];
        } elseif (isset($patches['street_name'])) {
            $patches['street'] = $patches['street_name'];
            if (!isset($patches['calle'])) $patches['calle'] = $patches['street_name'];
        } elseif (isset($patches['calle'])) {
            $patches['street'] = $patches['calle'];
            if (!isset($patches['street_name'])) $patches['street_name'] = $patches['calle'];
        }

        // 6. Sincronizar zip_code <-> postal_code / codigo_postal
        if (isset($patches['zip_code'])) {
            if (!isset($patches['postal_code']))   $patches['postal_code'] = $patches['zip_code'];
            if (!isset($patches['codigo_postal'])) $patches['codigo_postal'] = $patches['zip_code'];
        } elseif (isset($patches['postal_code'])) {
            $patches['zip_code'] = $patches['postal_code'];
            if (!isset($patches['codigo_postal'])) $patches['codigo_postal'] = $patches['postal_code'];
        } elseif (isset($patches['codigo_postal'])) {
            $patches['zip_code'] = $patches['codigo_postal'];
            if (!isset($patches['postal_code'])) $patches['postal_code'] = $patches['codigo_postal'];
        }

        // 7. Sincronizar delivery_radius_km <-> delivery_radius_meters
        if ($this->has('delivery_radius_km')) {
            $val = $this->delivery_radius_km;
            if (is_numeric($val)) {
                $patches['delivery_radius_km'] = (float) $val;
                $patches['delivery_radius_meters'] = (int) round((float) $val * 1000);
            }
        } elseif ($this->has('delivery_radius_meters')) {
            $val = $this->delivery_radius_meters;
            if (is_numeric($val)) {
                $patches['delivery_radius_km'] = round((float) $val / 1000, 2);
                $patches['delivery_radius_meters'] = (int) $val;
            }
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
            // ── Reglas de Ubicación ─────────────────────────────────────────
            'city'                    => 'required|string|min:2|max:150',
            'ciudad'                  => 'sometimes|string|min:2|max:150',

            'municipality'            => 'required|string|min:2|max:150',
            'municipio'               => 'sometimes|string|min:2|max:150',

            'state'                   => 'required|string|min:2|max:150',
            'estado'                  => 'sometimes|string|min:2|max:150',

            'street'                  => 'required|string|min:2|max:150',
            'street_name'             => 'sometimes|string|min:2|max:150',
            'calle'                   => 'sometimes|string|min:2|max:150',

            // ── Regla Código Postal (5 números exactos) ────────────────────
            'zip_code'                => ['required', 'string', 'regex:/^[0-9]{5}$/'],
            'postal_code'             => ['sometimes', 'string', 'regex:/^[0-9]{5}$/'],
            'codigo_postal'           => ['sometimes', 'string', 'regex:/^[0-9]{5}$/'],

            // ── Regla Radio de Cobertura ───────────────────────────────────
            'delivery_radius_km'      => 'required|numeric|min:0.1|max:100',
            'delivery_radius_meters'  => 'sometimes|numeric',

            // ── Campos Opcionales Compatibles ──────────────────────────────
            'latitude'                => 'nullable|numeric|between:-90,90',
            'longitude'               => 'nullable|numeric|between:-180,180',
            'coverage_polygon'        => 'nullable|array',
            'coverage_neighborhoods'  => 'nullable|array',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'city.required'              => 'La ciudad es obligatoria.',
            'city.string'                => 'La ciudad debe ser una cadena de texto.',
            'city.min'                   => 'La ciudad debe tener al menos 2 caracteres.',
            'city.max'                   => 'La ciudad no puede superar 150 caracteres.',

            'municipality.required'      => 'El municipio o delegación es obligatorio.',
            'municipality.string'        => 'El municipio debe ser una cadena de texto.',
            'municipality.min'           => 'El municipio debe tener al menos 2 caracteres.',
            'municipality.max'           => 'El municipio no puede superar 150 caracteres.',

            'state.required'             => 'El estado es obligatorio.',
            'state.string'               => 'El estado debe ser una cadena de texto.',
            'state.min'                  => 'El estado debe tener al menos 2 caracteres.',
            'state.max'                  => 'El estado no puede superar 150 caracteres.',

            'street.required'            => 'La calle y número son obligatorios.',
            'street.string'              => 'La calle debe ser una cadena de texto.',
            'street.min'                 => 'La calle debe tener al menos 2 caracteres.',
            'street.max'                 => 'La calle no puede superar 150 caracteres.',

            'zip_code.required'          => 'El código postal es obligatorio.',
            'zip_code.string'            => 'El código postal debe ser una cadena de texto.',
            'zip_code.regex'             => 'El código postal debe tener exactamente 5 dígitos numéricos.',

            'delivery_radius_km.required'=> 'El radio de cobertura es obligatorio.',
            'delivery_radius_km.numeric' => 'El radio de cobertura debe ser un valor numérico.',
            'delivery_radius_km.min'     => 'El radio de cobertura mínimo es de 0.1 km.',
            'delivery_radius_km.max'     => 'El radio de cobertura no puede exceder 100 km.',
        ];
    }
}
