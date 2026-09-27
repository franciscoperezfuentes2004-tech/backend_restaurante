<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateAdminCredentialsRequest extends FormRequest
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
     * ACCIÓN: strtolower($this->admin_email) y normalización de teléfono.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar email a minúsculas y sincronizar alias
        if ($this->has('admin_email')) {
            $cleaned = is_string($this->admin_email) ? strtolower(trim($this->admin_email)) : $this->admin_email;
            $patches['admin_email'] = $cleaned;
            if (!$this->has('email')) {
                $patches['email'] = $cleaned;
            }
        } elseif ($this->has('email')) {
            $cleaned = is_string($this->email) ? strtolower(trim($this->email)) : $this->email;
            $patches['admin_email'] = $cleaned;
            $patches['email'] = $cleaned;
        }

        // 2. Normalizar teléfono (quitar espacios o guiones) y sincronizar alias
        if ($this->has('admin_phone')) {
            $rawPhone = is_string($this->admin_phone) ? preg_replace('/[^0-9]/', '', $this->admin_phone) : $this->admin_phone;
            $patches['admin_phone'] = $rawPhone;
            if (!$this->has('phone')) {
                $patches['phone'] = $rawPhone;
            }
        } elseif ($this->has('phone')) {
            $rawPhone = is_string($this->phone) ? preg_replace('/[^0-9]/', '', $this->phone) : $this->phone;
            $patches['admin_phone'] = $rawPhone;
            $patches['phone'] = $rawPhone;
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
        $userId = $this->user()?->id ?? auth()->id() ?? $this->route('id') ?? $this->input('id');

        return [
            // ── Correo Electrónico ──────────────────────────────────────────
            'email' => [
                'required',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],
            'admin_email' => [
                'sometimes',
                'email',
                'max:150',
                Rule::unique('users', 'email')->ignore($userId),
            ],

            // ── Teléfono Móvil ─────────────────────────────────────────────
            'phone' => [
                'required',
                'string',
                'regex:/^[0-9]{10}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],
            'admin_phone' => [
                'sometimes',
                'string',
                'regex:/^[0-9]{10}$/',
                Rule::unique('users', 'phone')->ignore($userId),
            ],

            // ── Contraseña Blindada ─────────────────────────────────────────
            'password' => [
                'required',
                'string',
                'confirmed', // EXIGE que el frontend envíe un campo llamado 'password_confirmation' que coincida exactamente
                'not_regex:/\s/',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
            'password_confirmation' => 'required_with:password|string',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'email.required'          => 'El correo electrónico es obligatorio.',
            'email.email'             => 'El correo electrónico debe ser una dirección válida.',
            'email.max'               => 'El correo electrónico no debe exceder 150 caracteres.',
            'email.unique'            => 'El correo electrónico ya está en uso por otro usuario.',

            'admin_email.required'    => 'El correo electrónico es obligatorio.',
            'admin_email.email'       => 'El correo electrónico debe ser una dirección válida.',
            'admin_email.max'         => 'El correo electrónico no debe exceder 150 caracteres.',
            'admin_email.unique'      => 'El correo electrónico ya está en uso por otro usuario.',

            'phone.required'          => 'El número de teléfono es obligatorio.',
            'phone.regex'             => 'El teléfono debe contener exactamente 10 dígitos numéricos.',
            'phone.unique'            => 'El número de teléfono ya está registrado por otro usuario.',

            'admin_phone.required'    => 'El número de teléfono es obligatorio.',
            'admin_phone.regex'       => 'El teléfono debe contener exactamente 10 dígitos numéricos.',
            'admin_phone.unique'      => 'El número de teléfono ya está registrado por otro usuario.',

            'password.required'       => 'La contraseña es obligatoria.',
            'password.string'         => 'La contraseña debe ser una cadena de texto.',
            'password.confirmed'      => 'La confirmación de la contraseña no coincide.',
            'password.not_regex'      => 'La contraseña no debe contener espacios en blanco.',

            'password_confirmation.required_with' => 'Debe confirmar la contraseña.',
        ];
    }
}
