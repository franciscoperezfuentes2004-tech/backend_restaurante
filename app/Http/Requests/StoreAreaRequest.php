<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * ACCIÓN: Aplicar strip_tags($this->name) para prevenir XSS.
     * Sincronizar alias de campos (name <-> nombre, capacity <-> capacidad_personas,
     * tables_count <-> numero_mesas, is_active <-> active).
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar name / nombre contra XSS
        if ($this->has('name')) {
            $patches['name'] = trim(strip_tags((string) $this->name));
            if (!$this->has('nombre')) {
                $patches['nombre'] = $patches['name'];
            }
        } elseif ($this->has('nombre')) {
            $patches['name'] = trim(strip_tags((string) $this->nombre));
            $patches['nombre'] = $patches['name'];
        }

        // 2. Normalizar capacity <-> capacidad_personas
        if ($this->has('capacity') && !$this->has('capacidad_personas')) {
            $patches['capacidad_personas'] = $this->capacity;
        } elseif ($this->has('capacidad_personas') && !$this->has('capacity')) {
            $patches['capacity'] = $this->capacidad_personas;
        }

        // 3. Normalizar tables_count <-> numero_mesas
        if ($this->has('tables_count') && !$this->has('numero_mesas')) {
            $patches['numero_mesas'] = $this->tables_count;
        } elseif ($this->has('numero_mesas') && !$this->has('tables_count')) {
            $patches['tables_count'] = $this->numero_mesas;
        }

        // 4. Normalizar is_active <-> active con casteo booleano estricto si viene texto
        if ($this->has('is_active')) {
            $raw = $this->is_active;
            $boolVal = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolVal !== null) {
                $patches['is_active'] = $boolVal;
                $patches['active']    = $boolVal;
            }
        } elseif ($this->has('active')) {
            $raw = $this->active;
            $boolVal = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolVal !== null) {
                $patches['is_active'] = $boolVal;
                $patches['active']    = $boolVal;
            }
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Nombre del área ────────────────────────────────────────────
            'name'               => [
                'required',
                'string',
                'min:3',
                'max:50',
                Rule::unique('areas', 'name')->withoutTrashed(),
            ],
            'nombre'             => 'sometimes|string|min:3|max:50',

            // ── Capacidad de personas (entero sin decimales) ───────────────
            'capacity'           => 'required|integer|min:1|max:1000',
            'capacidad_personas' => 'sometimes|integer|min:1|max:1000',

            // ── Número de mesas (entero sin decimales) ─────────────────────
            'tables_count'       => 'required|integer|min:0|max:500',
            'numero_mesas'       => 'sometimes|integer|min:0|max:500',

            // ── Estado activo/inactivo ─────────────────────────────────────
            'is_active'          => 'required|boolean',
            'active'             => 'sometimes|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'               => 'El nombre del área es obligatorio.',
            'name.string'                 => 'El nombre del área debe ser una cadena de texto.',
            'name.min'                    => 'El nombre del área debe tener al menos 3 caracteres.',
            'name.max'                    => 'El nombre del área no debe superar los 50 caracteres.',
            'name.unique'                 => 'Ya existe un área con este nombre.',
            'nombre.required'             => 'El nombre del área es obligatorio.',
            'nombre.string'               => 'El nombre del área debe ser una cadena de texto.',
            'nombre.min'                  => 'El nombre del área debe tener al menos 3 caracteres.',
            'nombre.max'                  => 'El nombre del área no debe superar los 50 caracteres.',
            'nombre.unique'               => 'Ya existe un área con este nombre.',
            'capacity.required'           => 'La capacidad de personas es obligatoria.',
            'capacity.integer'            => 'La capacidad de personas debe ser un número entero sin decimales.',
            'capacity.min'                => 'La capacidad debe ser de al menos 1 persona.',
            'capacity.max'                => 'La capacidad máxima permitida es de 1000 personas.',
            'capacidad_personas.required' => 'La capacidad de personas es obligatoria.',
            'capacidad_personas.integer'  => 'La capacidad de personas debe ser un número entero sin decimales.',
            'capacidad_personas.min'      => 'La capacidad debe ser de al menos 1 persona.',
            'capacidad_personas.max'      => 'La capacidad máxima permitida es de 1000 personas.',
            'tables_count.required'       => 'El número de mesas es obligatorio.',
            'tables_count.integer'        => 'El número de mesas debe ser un número entero sin decimales.',
            'tables_count.min'            => 'El número de mesas no puede ser menor a 0.',
            'tables_count.max'            => 'El número máximo de mesas permitido es 500.',
            'numero_mesas.required'       => 'El número de mesas es obligatorio.',
            'numero_mesas.integer'        => 'El número de mesas debe ser un número entero sin decimales.',
            'numero_mesas.min'            => 'El número de mesas no puede ser menor a 0.',
            'numero_mesas.max'            => 'El número máximo de mesas permitido es 500.',
            'is_active.required'          => 'El estado activo/inactivo es obligatorio.',
            'is_active.boolean'           => 'El estado debe ser verdadero o falso.',
            'active.required'             => 'El estado activo/inactivo es obligatorio.',
            'active.boolean'              => 'El estado debe ser verdadero o falso.',
        ];
    }
}
