<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * - Aplicar strip_tags a 'name' para sanear el nombre contra inyección XSS.
     * - Convertir el correo a minúsculas (strtolower) y eliminar espacios en blanco.
     * - Sanitizar teléfono eliminando espacios circundantes.
     * - Normalizar alias de roles (ej. manager -> gerente, waiter -> mesero).
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar nombre contra XSS
        if ($this->has('name')) {
            $patches['name'] = trim(strip_tags((string) $this->name));
        }

        // 2. Normalizar email a minúsculas y sin espacios
        if ($this->has('email')) {
            $patches['email'] = strtolower(trim((string) $this->email));
        }

        // 3. Sanitizar teléfono
        if ($this->has('phone') && is_string($this->phone)) {
            $patches['phone'] = trim($this->phone);
        }

        // 4. Normalizar rol si es un alias
        if ($this->has('role')) {
            $role = strtolower(trim((string) $this->role));
            $roleAliases = [
                'manager'       => 'gerente',
                'waiter'        => 'mesero',
                'kitchen'       => 'cocina',
                'driver'        => 'repartidor',
                'superadmin'    => 'super_admin',
                'administrador' => 'admin',
            ];
            $patches['role'] = $roleAliases[$role] ?? $role;
        }

        if (!empty($patches)) {
            $this->merge($patches);
        }
    }

    public function rules(): array
    {
        $targetUserId = $this->route('id') ?? $this->route('user') ?? $this->route('usuario') ?? $this->id;
        if (is_object($targetUserId)) {
            $targetUserId = $targetUserId->id;
        }

        return [
            // ── Nombre completo ────────────────────────────────────────────
            'name'        => 'sometimes|required|string|min:3|max:100',

            // ── Teléfono (10 dígitos exactos, único ignorando usuario actual) ────
            'phone'       => [
                'sometimes',
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
                Rule::unique('users', 'phone')->ignore($targetUserId),
            ],

            // ── Correo electrónico (máx 150 caracteres, único ignorando usuario actual) ────
            'email'       => [
                'sometimes',
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($targetUserId),
            ],

            // ── Rol del usuario ────────────────────────────────────────────
            'role'        => 'sometimes|required|string|exists:roles,name',


            'is_active'   => 'nullable|boolean',
            'branch_id'   => 'nullable|integer',
            'branch_name' => 'nullable|string|max:100',
            'avatar'      => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:10240',
            'imagen'      => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:10240',
            'foto'        => 'nullable|file|image|mimes:jpg,jpeg,png,webp|max:10240',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'           => 'El nombre completo es obligatorio.',
            'name.string'             => 'El nombre debe ser una cadena de texto.',
            'name.min'                => 'El nombre debe tener al menos 3 caracteres.',
            'name.max'                => 'El nombre no debe superar los 100 caracteres.',
            'phone.required'          => 'El número de teléfono es obligatorio.',
            'phone.string'            => 'El número de teléfono debe ser una cadena de texto.',
            'phone.regex'             => 'El teléfono debe contener exactamente 10 dígitos numéricos.',
            'phone.unique'            => 'Este número de teléfono ya pertenece a otro usuario.',
            'email.required'          => 'El correo electrónico es obligatorio.',
            'email.email'             => 'Debe ingresar un correo electrónico válido.',
            'email.max'               => 'El correo electrónico no debe superar los 150 caracteres.',
            'email.unique'            => 'Este correo electrónico ya pertenece a otro usuario.',
            'role.required'           => 'El rol de usuario es obligatorio.',
            'role.string'             => 'El rol de usuario debe ser una cadena de texto.',
            'role.exists'             => 'El rol seleccionado no es válido.',

            'avatar.image'            => 'El archivo debe ser una imagen válida.',
            'avatar.mimes'            => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'avatar.max'              => 'La imagen no debe pesar más de 10MB.',
            'imagen.image'            => 'El archivo debe ser una imagen válida.',
            'imagen.mimes'            => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'imagen.max'              => 'La imagen no debe pesar más de 10MB.',
            'foto.image'              => 'El archivo debe ser una imagen válida.',
            'foto.mimes'              => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'foto.max'                => 'La imagen no debe pesar más de 10MB.',
        ];
    }
}
