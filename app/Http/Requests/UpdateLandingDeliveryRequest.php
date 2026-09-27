<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingDeliveryRequest extends FormRequest
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
     * ACCIÓN OBLIGATORIA: Aplicar strip_tags() a los campos principales y crear un
     * mapeo para sanitizar los arreglos anidados (benefits, steps, guarantees).
     * Limpieza Estricta del Número:
     * $this->merge([
     *     'whatsapp_number' => preg_replace('/[^0-9]/', '', $this->whatsapp_number),
     * ]);
     */
    protected function prepareForValidation(): void
    {
        $rawSection = $this->input('delivery_seccion')
            ?? $this->input('deliverySeccion')
            ?? null;

        $sanitizeText = function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value)));
        };

        $patches = [];

        // 1. subtitle / labelSuperior / subtitulo
        $rawSubtitle = $this->input('subtitle')
            ?? $this->input('labelSuperior')
            ?? $this->input('label_superior')
            ?? $this->input('subtitulo')
            ?? (is_array($rawSection) ? ($rawSection['subtitle'] ?? $rawSection['labelSuperior'] ?? $rawSection['subtitulo'] ?? null) : null);

        if ($rawSubtitle !== null) {
            $patches['subtitle'] = $sanitizeText($rawSubtitle);
            $patches['labelSuperior'] = $patches['subtitle'];
        }

        // 2. title / tituloPrincipal / titulo
        $rawTitle = $this->input('title')
            ?? $this->input('tituloPrincipal')
            ?? $this->input('titulo_principal')
            ?? $this->input('titulo')
            ?? (is_array($rawSection) ? ($rawSection['title'] ?? $rawSection['tituloPrincipal'] ?? $rawSection['titulo'] ?? null) : null);

        if ($rawTitle !== null) {
            $patches['title'] = $sanitizeText($rawTitle);
            $patches['tituloPrincipal'] = $patches['title'];
        }

        // 3. description / descripcion
        $rawDescription = $this->input('description')
            ?? $this->input('descripcion')
            ?? (is_array($rawSection) ? ($rawSection['description'] ?? $rawSection['descripcion'] ?? null) : null);

        if ($rawDescription !== null) {
            $patches['description'] = $sanitizeText($rawDescription);
            $patches['descripcion'] = $patches['description'];
        }

        // 4. image_title / imagenTitulo / imagen_titulo / delivery_image_title
        $rawImageTitle = $this->input('image_title')
            ?? $this->input('imagenTitulo')
            ?? $this->input('imagen_titulo')
            ?? $this->input('delivery_image_title')
            ?? (is_array($rawSection) ? ($rawSection['image_title'] ?? $rawSection['imagenTitulo'] ?? $rawSection['imagen_titulo'] ?? $rawSection['delivery_image_title'] ?? null) : null);

        if ($rawImageTitle !== null) {
            $patches['image_title'] = $sanitizeText($rawImageTitle);
            $patches['imagenTitulo'] = $patches['image_title'];
            $patches['delivery_image_title'] = $patches['image_title'];
        }

        // 5. image_alt / imagenDescripcion / imagen_descripcion / delivery_image_description
        $rawImageAlt = $this->input('image_alt')
            ?? $this->input('imagenDescripcion')
            ?? $this->input('imagen_descripcion')
            ?? $this->input('delivery_image_description')
            ?? (is_array($rawSection) ? ($rawSection['image_alt'] ?? $rawSection['imagenDescripcion'] ?? $rawSection['imagen_descripcion'] ?? $rawSection['delivery_image_description'] ?? null) : null);

        if ($rawImageAlt !== null) {
            $patches['image_alt'] = $sanitizeText($rawImageAlt);
            $patches['imagenDescripcion'] = $patches['image_alt'];
            $patches['delivery_image_description'] = $patches['image_alt'];
        }

        // 6. image / imagen / imagen_url / delivery_image_url
        if (!$this->hasFile('image')) {
            $rawImage = $this->input('image')
                ?? $this->input('imagen')
                ?? $this->input('imagen_url')
                ?? $this->input('delivery_image_url')
                ?? (is_array($rawSection) ? ($rawSection['image'] ?? $rawSection['imagen'] ?? $rawSection['imagen_url'] ?? $rawSection['delivery_image_url'] ?? null) : null);

            if ($rawImage !== null) {
                $patches['image'] = is_string($rawImage) ? $sanitizeText($rawImage) : $rawImage;
                $patches['imagen'] = $patches['image'];
                $patches['delivery_image_url'] = $patches['image'];
            }
        }

        // 7. button_subtext / textoBoton / texto_boton
        $rawBtnSubtext = $this->input('button_subtext')
            ?? $this->input('textoBoton')
            ?? $this->input('texto_boton')
            ?? (is_array($rawSection) ? ($rawSection['button_subtext'] ?? $rawSection['textoBoton'] ?? $rawSection['texto_boton'] ?? null) : null);

        if ($rawBtnSubtext !== null) {
            $patches['button_subtext'] = $sanitizeText($rawBtnSubtext);
            $patches['textoBoton'] = $patches['button_subtext'];
        }

        // 8. benefits / beneficios
        $rawBenefits = $this->input('benefits')
            ?? $this->input('beneficios')
            ?? (is_array($rawSection) ? ($rawSection['benefits'] ?? $rawSection['beneficios'] ?? null) : null);

        if (is_array($rawBenefits)) {
            $cleanedBenefits = [];
            foreach ($rawBenefits as $idx => $item) {
                if (is_array($item)) {
                    $bTitle = $item['title'] ?? $item['titulo'] ?? '';
                    $bDesc = $item['description'] ?? $item['descripcion'] ?? '';
                    $cleanedBenefits[$idx] = [
                        'title'       => $sanitizeText($bTitle),
                        'titulo'      => $sanitizeText($bTitle),
                        'description' => $sanitizeText($bDesc),
                        'descripcion' => $sanitizeText($bDesc),
                    ];
                } else {
                    $cleanedBenefits[$idx] = $item;
                }
            }
            $patches['benefits'] = $cleanedBenefits;
            $patches['beneficios'] = $cleanedBenefits;
        } elseif ($rawBenefits !== null) {
            $patches['benefits'] = $rawBenefits;
            $patches['beneficios'] = $rawBenefits;
        }

        // 9. steps / pasos
        $rawSteps = $this->input('steps')
            ?? $this->input('pasos')
            ?? (is_array($rawSection) ? ($rawSection['steps'] ?? $rawSection['pasos'] ?? null) : null);

        if (is_array($rawSteps)) {
            $cleanedSteps = [];
            foreach ($rawSteps as $idx => $item) {
                if (is_array($item)) {
                    $sTitle = $item['title'] ?? $item['titulo'] ?? '';
                    $sSubtitle = $item['subtitle'] ?? $item['subtitulo'] ?? '';
                    $cleanedSteps[$idx] = [
                        'title'     => $sanitizeText($sTitle),
                        'titulo'    => $sanitizeText($sTitle),
                        'subtitle'  => $sanitizeText($sSubtitle),
                        'subtitulo' => $sanitizeText($sSubtitle),
                    ];
                } else {
                    $cleanedSteps[$idx] = $item;
                }
            }
            $patches['steps'] = $cleanedSteps;
            $patches['pasos'] = $cleanedSteps;
        } elseif ($rawSteps !== null) {
            $patches['steps'] = $rawSteps;
            $patches['pasos'] = $rawSteps;
        }

        // 10. guarantees / garantias
        $rawGuarantees = $this->input('guarantees')
            ?? $this->input('garantias')
            ?? (is_array($rawSection) ? ($rawSection['guarantees'] ?? $rawSection['garantias'] ?? null) : null);

        if (is_array($rawGuarantees)) {
            $cleanedGuarantees = [];
            foreach ($rawGuarantees as $idx => $item) {
                if (is_string($item)) {
                    $cleanedGuarantees[$idx] = $sanitizeText($item);
                } elseif (is_array($item) && (isset($item['texto']) || isset($item['text']))) {
                    $cleanedGuarantees[$idx] = $sanitizeText($item['texto'] ?? $item['text']);
                } else {
                    $cleanedGuarantees[$idx] = $item;
                }
            }
            $patches['guarantees'] = $cleanedGuarantees;
            $patches['garantias'] = $cleanedGuarantees;
        } elseif ($rawGuarantees !== null) {
            $patches['guarantees'] = $rawGuarantees;
            $patches['garantias'] = $rawGuarantees;
        }

        // 11. Limpieza Estricta del Número
        $rawWhatsapp = $this->input('whatsapp_number')
            ?? $this->input('numeroWhatsapp')
            ?? $this->input('numero_whatsapp')
            ?? $this->input('whatsapp')
            ?? (is_array($rawSection) ? ($rawSection['whatsapp_number'] ?? $rawSection['numeroWhatsapp'] ?? $rawSection['whatsapp'] ?? null) : null);

        $cleanWhatsapp = preg_replace('/[^0-9]/', '', (string)($rawWhatsapp ?? $this->whatsapp_number ?? ''));
        $patches['whatsapp_number'] = $cleanWhatsapp;
        $patches['numeroWhatsapp']  = $cleanWhatsapp;
        $patches['whatsapp']        = $cleanWhatsapp;

        $this->merge($patches);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $hasFile = $this->hasFile('image');
        $isString = ($this->has('image') && is_string($this->image))
            || ($this->filled('image') && is_string($this->input('image')));

        $imageRule = ($isString && !$hasFile)
            ? 'nullable|string|max:500'
            : 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240';

        return [
            'subtitle'               => 'required|string|max:100',
            'title'                  => 'required|string|max:100',
            'description'            => 'required|string|max:250',
            'image'                  => $imageRule,
            'image_title'            => 'nullable|string|max:100',
            'image_alt'              => 'nullable|string|max:100',
            'whatsapp_number'        => 'required|string|regex:/^[0-9]{10,15}$/',
            'button_subtext'         => 'required|string|max:150',

            // Reglas Estructurales (Evitan manipulación del DOM público)
            'benefits'               => 'required|array|size:4',
            'benefits.*.title'       => 'required|string|max:50',
            'benefits.*.description' => 'required|string|max:100',

            'steps'                  => 'required|array|size:3',
            'steps.*.title'          => 'required|string|max:50',
            'steps.*.subtitle'       => 'required|string|max:100',

            'guarantees'             => 'required|array|size:3',
            'guarantees.*'           => 'required|string|max:100',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'subtitle.required'               => 'El subtítulo de la sección de delivery es obligatorio.',
            'subtitle.string'                 => 'El subtítulo debe ser una cadena de texto.',
            'subtitle.max'                    => 'El subtítulo no puede exceder los 100 caracteres.',

            'title.required'                  => 'El título principal de delivery es obligatorio.',
            'title.string'                    => 'El título principal debe ser una cadena de texto.',
            'title.max'                       => 'El título principal no puede exceder los 100 caracteres.',

            'description.required'            => 'La descripción del servicio de delivery es obligatoria.',
            'description.string'              => 'La descripción debe ser una cadena de texto.',
            'description.max'                 => 'La descripción no puede exceder los 250 caracteres.',

            'image.image'                     => 'El archivo seleccionado debe ser una imagen válida.',
            'image.mimes'                     => 'La imagen debe ser de formato jpg, jpeg, png o webp.',
            'image.max'                       => 'La imagen no puede exceder los 10MB (10240 KB).',

            'image_title.max'                 => 'El título sobre la imagen no puede exceder los 100 caracteres.',
            'image_alt.max'                   => 'El texto alternativo (ALT) no puede exceder los 100 caracteres.',

            'whatsapp_number.required'        => 'El número de WhatsApp para pedidos es obligatorio.',
            'whatsapp_number.regex'           => 'El número de WhatsApp debe contener entre 10 y 15 dígitos numéricos.',

            'button_subtext.required'         => 'El texto bajo el botón de delivery es obligatorio.',
            'button_subtext.string'           => 'El texto bajo el botón debe ser una cadena de texto.',
            'button_subtext.max'              => 'El texto bajo el botón no puede exceder los 150 caracteres.',

            'benefits.required'               => 'Las tarjetas de beneficios son obligatorias.',
            'benefits.array'                  => 'Los beneficios deben ser un arreglo.',
            'benefits.size'                   => 'Debe configurar exactamente 4 tarjetas de beneficios.',

            'benefits.*.title.required'       => 'El título de cada beneficio es obligatorio.',
            'benefits.*.title.string'         => 'El título del beneficio debe ser texto.',
            'benefits.*.title.max'            => 'El título del beneficio no puede exceder los 50 caracteres.',

            'benefits.*.description.required' => 'La descripción de cada beneficio es obligatoria.',
            'benefits.*.description.string'   => 'La descripción del beneficio debe ser texto.',
            'benefits.*.description.max'      => 'La descripción del beneficio no puede exceder los 100 caracteres.',

            'steps.required'                  => 'Los pasos del proceso de delivery son obligatorios.',
            'steps.array'                     => 'Los pasos deben ser un arreglo.',
            'steps.size'                      => 'Debe configurar exactamente 3 pasos.',

            'steps.*.title.required'          => 'El título de cada paso es obligatorio.',
            'steps.*.title.string'            => 'El título del paso debe ser texto.',
            'steps.*.title.max'               => 'El título del paso no puede exceder los 50 caracteres.',

            'steps.*.subtitle.required'       => 'El subtítulo de cada paso es obligatorio.',
            'steps.*.subtitle.string'         => 'El subtítulo del paso debe ser texto.',
            'steps.*.subtitle.max'            => 'El subtítulo del paso no puede exceder los 100 caracteres.',

            'guarantees.required'             => 'Las garantías del servicio son obligatorias.',
            'guarantees.array'                => 'Las garantías deben ser un arreglo.',
            'guarantees.size'                 => 'Debe configurar exactamente 3 garantías.',

            'guarantees.*.required'           => 'El texto de cada garantía es obligatorio.',
            'guarantees.*.string'             => 'El texto de la garantía debe ser una cadena de texto.',
            'guarantees.*.max'                => 'El texto de la garantía no puede exceder los 100 caracteres.',
        ];
    }

    /**
     * Nombres de atributos personalizados para mensajes de validación.
     */
    public function attributes(): array
    {
        return [
            'subtitle'               => 'subtítulo',
            'title'                  => 'título principal',
            'description'            => 'descripción',
            'image'                  => 'imagen de delivery',
            'image_title'            => 'título sobre la imagen',
            'image_alt'              => 'texto alternativo',
            'whatsapp_number'        => 'número de WhatsApp',
            'button_subtext'         => 'texto bajo el botón',
            'benefits'               => 'beneficios',
            'benefits.*.title'       => 'título de beneficio',
            'benefits.*.description' => 'descripción de beneficio',
            'steps'                  => 'pasos',
            'steps.*.title'          => 'título del paso',
            'steps.*.subtitle'       => 'subtítulo del paso',
            'guarantees'             => 'garantías',
            'guarantees.*'           => 'garantía',
        ];
    }
}
