<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Intervenir los datos para purificarlos ANTES de validarlos.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            // strip_tags elimina cualquier cosa como <script> o <iframe>
            'mensaje' => is_string($this->mensaje) ? trim(strip_tags($this->mensaje)) : $this->mensaje,
            'asunto'  => is_string($this->asunto) ? trim(strip_tags($this->asunto)) : $this->asunto,
            'nombre'  => is_string($this->nombre) ? trim(strip_tags($this->nombre)) : $this->nombre,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre'   => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-zA-ZÀ-ÿ\s]+$/'],
            // Teléfono ahora es estricto y requerido
            'telefono' => ['required', 'digits:10'],
            // nullable permite que venga vacío, pero si trae datos, los valida como email estricto
            'email'    => ['nullable', 'email:rfc,dns', 'max:100'],
            'asunto'   => ['required', 'string', 'min:4', 'max:100'],
            'mensaje'  => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    /**
     * Mensajes personalizados de error en español.
     */
    public function messages(): array
    {
        return [
            'nombre.required'   => 'El nombre es obligatorio.',
            'nombre.min'        => 'El nombre debe tener al menos 3 caracteres.',
            'nombre.max'        => 'El nombre no puede exceder 50 caracteres.',
            'nombre.regex'      => 'El nombre solo puede contener letras y espacios.',
            'email.email'       => 'El formato del correo electrónico es inválido o su dominio no existe.',
            'email.max'         => 'El correo electrónico no puede exceder 100 caracteres.',
            'telefono.required' => 'El teléfono es obligatorio.',
            'telefono.digits'   => 'El teléfono debe contener exactamente 10 dígitos numéricos.',
            'asunto.required'   => 'El asunto es obligatorio.',
            'asunto.min'        => 'El asunto debe tener al menos 4 caracteres.',
            'asunto.max'        => 'El asunto no puede exceder 100 caracteres.',
            'mensaje.required'  => 'El mensaje es obligatorio.',
            'mensaje.min'       => 'El mensaje debe tener al menos 10 caracteres.',
            'mensaje.max'       => 'El mensaje no puede exceder 500 caracteres.',
        ];
    }
}
