<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGeneralSettingsRequest extends FormRequest
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
     * ACCIÓN: strip_tags($this->business_name).
     * Convertir el booleano del interruptor con filter_var($this->is_delivery_active, FILTER_VALIDATE_BOOLEAN).
     * Normalizar y sincronizar alias entre inglés y español.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sanitizar business_name / nombre_comercial contra XSS
        if ($this->has('business_name')) {
            $raw = $this->business_name;
            $sanitized = is_string($raw) ? trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw))) : $raw;
            $patches['business_name'] = $sanitized;
            if (!$this->has('nombre_comercial')) {
                $patches['nombre_comercial'] = $sanitized;
            }
        } elseif ($this->has('nombre_comercial')) {
            $raw = $this->nombre_comercial;
            $sanitized = is_string($raw) ? trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $raw))) : $raw;
            $patches['business_name'] = $sanitized;
            $patches['nombre_comercial'] = $sanitized;
        }

        // 2. Normalizar is_delivery_active / delivery_activo con casteo booleano estricto
        if ($this->has('is_delivery_active')) {
            $raw = $this->is_delivery_active;
            $boolVal = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolVal !== null) {
                $patches['is_delivery_active'] = $boolVal;
                $patches['delivery_activo']    = $boolVal;
            }
        } elseif ($this->has('delivery_activo')) {
            $raw = $this->delivery_activo;
            $boolVal = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($boolVal !== null) {
                $patches['is_delivery_active'] = $boolVal;
                $patches['delivery_activo']    = $boolVal;
            } else {
                $patches['is_delivery_active'] = $this->delivery_activo;
            }
        }

        // 3. Normalizar theme / modo_fondo
        if ($this->has('theme')) {
            $themeRaw = is_string($this->theme) ? strtolower(trim($this->theme)) : $this->theme;
            $patches['theme'] = $themeRaw;
            if (!$this->has('modo_fondo')) {
                $patches['modo_fondo'] = ($themeRaw === 'dark' ? 'oscuro' : ($themeRaw === 'light' ? 'claro' : $themeRaw));
            }
        } elseif ($this->has('modo_fondo')) {
            $modoRaw = is_string($this->modo_fondo) ? strtolower(trim($this->modo_fondo)) : $this->modo_fondo;
            $patches['modo_fondo'] = $modoRaw;
            $patches['theme'] = ($modoRaw === 'oscuro' ? 'dark' : ($modoRaw === 'claro' ? 'light' : $modoRaw));
        }

        // 4. Normalizar primary_color / color_primario
        if ($this->has('primary_color') && !$this->has('color_primario')) {
            $patches['color_primario'] = $this->primary_color;
        } elseif ($this->has('color_primario') && !$this->has('primary_color')) {
            $patches['primary_color'] = $this->color_primario;
        }

        // 5. Normalizar fixed_delivery_fee / costo_envio_fijo
        if ($this->has('fixed_delivery_fee') && !$this->has('costo_envio_fijo')) {
            $patches['costo_envio_fijo'] = $this->fixed_delivery_fee;
        } elseif ($this->has('costo_envio_fijo') && !$this->has('fixed_delivery_fee')) {
            $patches['fixed_delivery_fee'] = $this->costo_envio_fijo;
        }

        // 6. Normalizar free_delivery_threshold / envio_gratis_desde
        if ($this->has('free_delivery_threshold') && !$this->has('envio_gratis_desde')) {
            $patches['envio_gratis_desde'] = $this->free_delivery_threshold;
        } elseif ($this->has('envio_gratis_desde') && !$this->has('free_delivery_threshold')) {
            $patches['free_delivery_threshold'] = $this->envio_gratis_desde;
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
            // ── Nombre Comercial ───────────────────────────────────────────
            'business_name'           => 'required|string|min:2|max:100',
            'nombre_comercial'        => 'sometimes|string|min:2|max:100',

            // ── Logotipo ───────────────────────────────────────────────────
            // Valida la firma MIME real del archivo para evitar .exe renombrados a .png
            'logo'                    => 'nullable|file|image|mimes:jpeg,png,webp,jpg|max:10240',
            'logotipo'                => 'nullable',

            // ── Tema (Fondo del sistema) ───────────────────────────────────
            'theme'                   => 'required|string|in:light,dark',
            'modo_fondo'              => 'sometimes|string|in:claro,oscuro,light,dark',

            // ── Color Primario ─────────────────────────────────────────────
            // Bloqueo estricto para evitar inyecciones en los estilos CSS dinámicos
            'primary_color'           => ['required', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],
            'color_primario'          => ['sometimes', 'string', 'regex:/^#[a-fA-F0-9]{6}$/'],

            // ── Servicio de Delivery ───────────────────────────────────────
            'is_delivery_active'      => 'required|boolean',
            'delivery_activo'         => 'sometimes|boolean',

            // ── Costos de Envío ────────────────────────────────────────────
            'fixed_delivery_fee'      => [
                'required_if:is_delivery_active,true,1',
                'nullable',
                'numeric',
                'min:0',
                'max:99999',
            ],
            'costo_envio_fijo'        => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999',
            ],
            'free_delivery_threshold' => [
                'required_if:is_delivery_active,true,1',
                'nullable',
                'numeric',
                'min:0',
                'max:99999',
            ],
            'envio_gratis_desde'      => [
                'sometimes',
                'nullable',
                'numeric',
                'min:0',
                'max:99999',
            ],

            // ── Campos Adicionales Compatibles ────────────────────────────
            'fondo_sistema'           => 'nullable|string|max:50',
            'color_apoyo'             => 'nullable|string|max:50',
            'color_apoyo_activo'      => 'nullable|boolean',
            'schedule'                => 'nullable|array',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'business_name.required'              => 'El nombre comercial es obligatorio.',
            'business_name.string'                => 'El nombre comercial debe ser una cadena de texto.',
            'business_name.min'                   => 'El nombre comercial debe tener al menos 2 caracteres.',
            'business_name.max'                   => 'El nombre comercial no puede superar los 100 caracteres.',

            'nombre_comercial.required'           => 'El nombre comercial es obligatorio.',
            'nombre_comercial.string'             => 'El nombre comercial debe ser una cadena de texto.',
            'nombre_comercial.min'                => 'El nombre comercial debe tener al menos 2 caracteres.',
            'nombre_comercial.max'                => 'El nombre comercial no puede superar los 100 caracteres.',

            'logo.image'                          => 'El logotipo debe ser una imagen válida.',
            'logo.mimes'                          => 'El formato del logotipo debe ser jpeg, png o webp.',
            'logo.max'                            => 'El archivo del logotipo no puede ser mayor a 10MB (10240 KB).',

            'theme.required'                      => 'El tema del sistema es obligatorio.',
            'theme.in'                            => 'El tema seleccionado debe ser light o dark.',

            'modo_fondo.required'                 => 'El modo de fondo es obligatorio.',
            'modo_fondo.in'                       => 'El modo de fondo debe ser claro u oscuro.',

            'primary_color.required'              => 'El color primario es obligatorio.',
            'primary_color.regex'                 => 'El color primario debe ser un código hexadecimal de 6 dígitos válido (ej. #7c3aed).',

            'color_primario.required'             => 'El color primario es obligatorio.',
            'color_primario.regex'                => 'El color primario debe ser un código hexadecimal de 6 dígitos válido (ej. #7c3aed).',

            'is_delivery_active.required'         => 'El estado del delivery es obligatorio.',
            'is_delivery_active.boolean'          => 'El estado del delivery debe ser un valor booleano.',

            'delivery_activo.required'            => 'El estado del delivery es obligatorio.',
            'delivery_activo.boolean'             => 'El estado del delivery debe ser un valor booleano.',

            'fixed_delivery_fee.required_if'      => 'La tarifa de envío es requerida cuando el delivery está activo.',
            'fixed_delivery_fee.numeric'          => 'La tarifa de envío debe ser un valor numérico.',
            'fixed_delivery_fee.min'              => 'La tarifa de envío no puede ser negativa.',
            'fixed_delivery_fee.max'              => 'La tarifa de envío no debe exceder 99999.',

            'costo_envio_fijo.numeric'            => 'El costo de envío fijo debe ser un valor numérico.',
            'costo_envio_fijo.min'                => 'El costo de envío fijo no puede ser negativo.',
            'costo_envio_fijo.max'                => 'El costo de envío fijo no debe exceder 99999.',

            'free_delivery_threshold.required_if' => 'El monto para envío gratis es requerido cuando el delivery está activo.',
            'free_delivery_threshold.numeric'     => 'El monto para envío gratis debe ser un valor numérico.',
            'free_delivery_threshold.min'         => 'El monto para envío gratis no puede ser negativo.',
            'free_delivery_threshold.max'         => 'El monto para envío gratis no debe exceder 99999.',

            'envio_gratis_desde.numeric'          => 'El umbral de envío gratis debe ser un valor numérico.',
            'envio_gratis_desde.min'              => 'El umbral de envío gratis no puede ser negativo.',
            'envio_gratis_desde.max'              => 'El umbral de envío gratis no debe exceder 99999.',
        ];
    }
}
