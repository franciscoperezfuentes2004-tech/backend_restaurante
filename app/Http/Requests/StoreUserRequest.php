<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
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
        return [
            // ── Nombre completo ────────────────────────────────────────────
            'name'        => 'required|string|min:3|max:100',

            // ── Teléfono (10 dígitos exactos, único) ───────────────────────
            'phone'       => 'required|string|regex:/^[0-9]{10}$/|unique:users,phone',

            // ── Correo electrónico (máx 150 caracteres, único) ─────────────
            'email'       => 'required|email|max:150|unique:users,email',

            // ── Rol del usuario ────────────────────────────────────────────
            'role'        => 'required|string|exists:roles,name',

            // ── Contraseña de alta seguridad (Password rule) ───────────────
            'password'    => [
                'required',
                'string',
                'not_regex:/\s/', // Sin espacios
                Password::min(8)
                    ->letters()       // Requiere al menos una letra
                    ->mixedCase()     // Requiere mayúsculas y minúsculas
                    ->numbers()       // Requiere números
                    ->symbols()       // Requiere símbolos especiales
                    ->uncompromised(), // Revisa si ha sido filtrada (HaveIBeenPwned API)
            ],
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
            'phone.regex'             => 'El número de teléfono debe contener exactamente 10 dígitos numéricos.',
            'phone.unique'            => 'Este número de teléfono ya está registrado en el sistema.',
            'email.required'          => 'El correo electrónico es obligatorio.',
            'email.email'             => 'Debe ingresar un correo electrónico válido.',
            'email.max'               => 'El correo electrónico no debe superar los 150 caracteres.',
            'email.unique'            => 'Este correo electrónico ya está registrado en el sistema.',
            'role.required'           => 'El rol de usuario es obligatorio.',
            'role.string'             => 'El rol de usuario debe ser una cadena de texto.',
            'role.exists'             => 'El rol seleccionado no es válido.',
            'password.required'       => 'La contraseña es obligatoria.',
            'password.string'         => 'La contraseña debe ser una cadena de texto.',
            'password.not_regex'      => 'La contraseña no debe contener espacios en blanco.',
            'password.min'            => 'La contraseña debe tener al menos 8 caracteres.',
            'password.letters'        => 'La contraseña debe contener al menos una letra.',
            'password.mixed'          => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            'password.numbers'        => 'La contraseña debe contener al menos un número.',
            'password.symbols'        => 'La contraseña debe contener al menos un carácter especial o símbolo.',
            'password.uncompromised'  => 'La contraseña proporcionada ha aparecido en una filtración de datos en internet. Por seguridad, elija una contraseña diferente.',
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
