<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * - Aplicar strip_tags a company_name y contact_name para eliminar HTML/XSS.
     * - Convertir is_active / active con filter_var a booleano estricto.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar company_name
        if ($this->has('company_name')) {
            $patches['company_name'] = trim(strip_tags((string) $this->company_name));
        }

        // 2. Sanitizar contact_name
        if ($this->has('contact_name') && $this->contact_name !== null) {
            $patches['contact_name'] = trim(strip_tags((string) $this->contact_name));
        }

        // 3. Normalizar y forzar casteo booleano estricto para is_active / active
        if ($this->has('is_active')) {
            $raw = $this->is_active;
            $validatedBool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($validatedBool !== null) {
                $patches['is_active'] = $validatedBool;
                $patches['active']    = $validatedBool;
            }
        } elseif ($this->has('active')) {
            $raw = $this->active;
            $validatedBool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($validatedBool !== null) {
                $patches['is_active'] = $validatedBool;
                $patches['active']    = $validatedBool;
            }
        }

        // 4. Compatibilidad: si se envía specialty como texto y no specialty_id
        if ($this->has('specialty') && !$this->has('specialty_id')) {
            $specialtyModel = \App\Models\SupplierSpecialty::where('name', trim($this->specialty))->first();
            if ($specialtyModel) {
                $patches['specialty_id'] = $specialtyModel->id;
            }
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        return [
            // ── Nombre de la Empresa ───────────────────────────────────────
            'company_name'  => 'required|string|min:2|max:100|unique:suppliers,company_name',

            // ── Nombre de Contacto ─────────────────────────────────────────
            'contact_name'  => 'nullable|string|max:100',

            // ── Especialidad / Ramo (FK) ───────────────────────────────────
            'specialty_id'  => 'required|integer|exists:supplier_specialties,id',

            // ── Teléfono (10 dígitos exactos, sin espacios ni guiones) ────
            'phone'         => 'required|string|regex:/^[0-9]{10}$/',

            // ── Correo Electrónico ─────────────────────────────────────────
            'email'         => 'nullable|email|max:150',

            // ── Días de Entrega ────────────────────────────────────────────
            'delivery_days'   => 'required|array|min:1|max:7',
            'delivery_days.*' => [
                'string',
                'distinct',
                Rule::in(['LUN', 'MAR', 'MIE', 'JUE', 'VIE', 'SAB', 'DOM', 'Lun', 'Mar', 'Mié', 'Mie', 'Jue', 'Vie', 'Sáb', 'Sab', 'Dom']),
            ],

            // ── Estado Activo/Inactivo ─────────────────────────────────────
            'is_active'     => 'required|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'company_name.required'    => 'El nombre de la empresa es obligatorio.',
            'company_name.min'         => 'El nombre de la empresa debe tener al menos 2 caracteres.',
            'company_name.max'         => 'El nombre de la empresa no debe superar los 100 caracteres.',
            'company_name.unique'      => 'Ya existe un proveedor registrado con esta empresa.',
            'contact_name.max'         => 'El nombre de contacto no debe superar los 100 caracteres.',
            'specialty_id.required'    => 'La especialidad del proveedor es obligatoria.',
            'specialty_id.integer'     => 'El identificador de la especialidad debe ser un número entero.',
            'specialty_id.exists'      => 'La especialidad seleccionada no existe en el catálogo.',
            'phone.required'           => 'El teléfono es obligatorio.',
            'phone.regex'              => 'El teléfono debe contener exactamente 10 dígitos numéricos sin espacios ni guiones.',
            'email.email'              => 'El correo electrónico debe tener un formato válido.',
            'email.max'                => 'El correo electrónico no debe superar los 150 caracteres.',
            'delivery_days.required'   => 'Debe seleccionar al menos un día de entrega.',
            'delivery_days.array'      => 'Los días de entrega deben ser una lista.',
            'delivery_days.min'        => 'Debe seleccionar al menos un día de entrega.',
            'delivery_days.max'        => 'No puede seleccionar más de 7 días de entrega.',
            'delivery_days.*.in'       => 'Uno o más días de entrega seleccionados no son válidos.',
            'delivery_days.*.distinct' => 'No puede duplicar días de entrega.',
            'is_active.required'       => 'El estado activo/inactivo es obligatorio.',
            'is_active.boolean'        => 'El estado activo/inactivo debe ser verdadero o falso.',
        ];
    }
}
