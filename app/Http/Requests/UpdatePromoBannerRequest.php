<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePromoBannerRequest extends FormRequest
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
     * ACCIÓN: Aplicar strip_tags() a los cinco campos de texto para evitar
     * inyecciones de código en el banner público.
     * Convertir el valor del toggle a booleano estricto:
     * $this->merge(['is_active' => filter_var($this->is_active, FILTER_VALIDATE_BOOLEAN)]);
     */
    protected function prepareForValidation(): void
    {
        $rawBanner = $this->input('banner_descuento') ?? $this->input('bannerDescuento') ?? null;

        $isActiveRaw = $this->has('is_active') 
            ? $this->is_active 
            : ($this->has('activo') 
                ? $this->activo 
                : (is_array($rawBanner) ? ($rawBanner['activo'] ?? $rawBanner['is_active'] ?? false) : false));

        $isActive = filter_var($isActiveRaw, FILTER_VALIDATE_BOOLEAN);

        $sanitizeText = function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value)));
        };

        $patches = [
            'is_active' => $isActive,
            'activo'    => $isActive,
        ];

        // 1. discount_percentage / porcentaje
        if ($this->has('discount_percentage')) {
            $patches['discount_percentage'] = is_numeric($this->discount_percentage) ? (int)$this->discount_percentage : $this->discount_percentage;
        } elseif ($this->has('porcentaje')) {
            $patches['discount_percentage'] = is_numeric($this->porcentaje) ? (int)$this->porcentaje : $this->porcentaje;
        } elseif (is_array($rawBanner) && (isset($rawBanner['porcentaje']) || isset($rawBanner['discount_percentage']))) {
            $val = $rawBanner['porcentaje'] ?? $rawBanner['discount_percentage'];
            $patches['discount_percentage'] = is_numeric($val) ? (int)$val : $val;
        }

        // 2. validity_badge / badgeVigencia
        if ($this->has('validity_badge')) {
            $patches['validity_badge'] = $sanitizeText($this->validity_badge);
        } elseif ($this->has('badgeVigencia')) {
            $patches['validity_badge'] = $sanitizeText($this->badgeVigencia);
        } elseif ($this->has('badge_vigencia')) {
            $patches['validity_badge'] = $sanitizeText($this->badge_vigencia);
        } elseif (is_array($rawBanner) && (isset($rawBanner['badgeVigencia']) || isset($rawBanner['validity_badge']))) {
            $patches['validity_badge'] = $sanitizeText($rawBanner['badgeVigencia'] ?? $rawBanner['validity_badge']);
        }

        // 3. title / tituloDescuento
        if ($this->has('title')) {
            $patches['title'] = $sanitizeText($this->title);
        } elseif ($this->has('tituloDescuento')) {
            $patches['title'] = $sanitizeText($this->tituloDescuento);
        } elseif ($this->has('titulo_descuento')) {
            $patches['title'] = $sanitizeText($this->titulo_descuento);
        } elseif (is_array($rawBanner) && (isset($rawBanner['tituloDescuento']) || isset($rawBanner['title']))) {
            $patches['title'] = $sanitizeText($rawBanner['tituloDescuento'] ?? $rawBanner['title']);
        }

        // 4. description / descripcion
        if ($this->has('description')) {
            $patches['description'] = $sanitizeText($this->description);
        } elseif ($this->has('descripcion')) {
            $patches['description'] = $sanitizeText($this->descripcion);
        } elseif (is_array($rawBanner) && (isset($rawBanner['descripcion']) || isset($rawBanner['description']))) {
            $patches['description'] = $sanitizeText($rawBanner['descripcion'] ?? $rawBanner['description']);
        }

        // 5. button_text / textoBoton
        if ($this->has('button_text')) {
            $patches['button_text'] = $sanitizeText($this->button_text);
        } elseif ($this->has('textoBoton')) {
            $patches['button_text'] = $sanitizeText($this->textoBoton);
        } elseif ($this->has('texto_boton')) {
            $patches['button_text'] = $sanitizeText($this->texto_boton);
        } elseif (is_array($rawBanner) && (isset($rawBanner['textoBoton']) || isset($rawBanner['button_text']))) {
            $patches['button_text'] = $sanitizeText($rawBanner['textoBoton'] ?? $rawBanner['button_text']);
        }

        // 6. button_subtext / textoBotonSub
        if ($this->has('button_subtext')) {
            $patches['button_subtext'] = $sanitizeText($this->button_subtext);
        } elseif ($this->has('textoBotonSub')) {
            $patches['button_subtext'] = $sanitizeText($this->textoBotonSub);
        } elseif ($this->has('texto_boton_sub')) {
            $patches['button_subtext'] = $sanitizeText($this->texto_boton_sub);
        } elseif (is_array($rawBanner) && (isset($rawBanner['textoBotonSub']) || isset($rawBanner['button_subtext']))) {
            $patches['button_subtext'] = $sanitizeText($rawBanner['textoBotonSub'] ?? $rawBanner['button_subtext']);
        }

        $this->merge($patches);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'is_active'           => 'required|boolean',
            'discount_percentage' => 'required_if:is_active,true|nullable|integer|min:1|max:100',
            'validity_badge'      => 'required_if:is_active,true|nullable|string|max:50',
            'title'               => 'required_if:is_active,true|nullable|string|max:100',
            'description'         => 'required_if:is_active,true|nullable|string|max:200',
            'button_text'         => 'required_if:is_active,true|nullable|string|max:50',
            'button_subtext'      => 'required_if:is_active,true|nullable|string|max:100',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'is_active.required'               => 'El estado del banner es obligatorio.',
            'is_active.boolean'                => 'El estado del banner debe ser verdadero o falso.',

            'discount_percentage.required_if'  => 'El porcentaje de descuento es obligatorio cuando el banner está activo.',
            'discount_percentage.integer'      => 'El porcentaje de descuento debe ser un número entero.',
            'discount_percentage.min'          => 'El porcentaje de descuento debe ser de al menos 1%.',
            'discount_percentage.max'          => 'El porcentaje de descuento no puede superar el 100%.',

            'validity_badge.required_if'       => 'La insignia de vigencia es obligatoria cuando el banner está activo.',
            'validity_badge.string'            => 'La insignia de vigencia debe ser una cadena de texto.',
            'validity_badge.max'               => 'La insignia de vigencia no puede superar los 50 caracteres.',

            'title.required_if'                => 'El título del descuento es obligatorio cuando el banner está activo.',
            'title.string'                     => 'El título del descuento debe ser una cadena de texto.',
            'title.max'                        => 'El título del descuento no puede superar los 100 caracteres.',

            'description.required_if'          => 'La descripción del descuento es obligatoria cuando el banner está activo.',
            'description.string'               => 'La descripción del descuento debe ser una cadena de texto.',
            'description.max'                  => 'La descripción del descuento no puede superar los 200 caracteres.',

            'button_text.required_if'          => 'El texto del botón es obligatorio cuando el banner está activo.',
            'button_text.string'               => 'El texto del botón debe ser una cadena de texto.',
            'button_text.max'                  => 'El texto del botón no puede superar los 50 caracteres.',

            'button_subtext.required_if'       => 'El texto secundario del botón es obligatorio cuando el banner está activo.',
            'button_subtext.string'            => 'El texto secundario del botón debe ser una cadena de texto.',
            'button_subtext.max'               => 'El texto secundario del botón no puede superar los 100 caracteres.',
        ];
    }
}