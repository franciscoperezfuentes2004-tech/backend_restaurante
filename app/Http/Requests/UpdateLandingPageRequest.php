<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingPageRequest extends FormRequest
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
     * ACCIÓN OBLIGATORIA: Recorrer todos los campos de texto (hero_title, hero_slogan,
     * history_title, history_description y los campos de las features) y aplicarles strip_tags($value).
     * Convertir enable_carousel a booleano real con filter_var().
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        $sanitize = function ($val) {
            if (!is_string($val)) {
                return $val;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $val)));
        };

        // 1. Sanitizar y normalizar hero_title
        if ($this->has('hero_title')) {
            $patches['hero_title'] = $sanitize($this->hero_title);
            if (!$this->has('heroTitle')) $patches['heroTitle'] = $patches['hero_title'];
        } elseif ($this->has('heroTitle')) {
            $patches['hero_title'] = $sanitize($this->heroTitle);
            $patches['heroTitle'] = $patches['hero_title'];
        }

        // 2. Sanitizar y normalizar hero_slogan
        if ($this->has('hero_slogan')) {
            $patches['hero_slogan'] = $sanitize($this->hero_slogan);
        } elseif ($this->has('hero_description')) {
            $patches['hero_slogan'] = $sanitize($this->hero_description);
            $patches['hero_description'] = $patches['hero_slogan'];
        } elseif ($this->has('heroDescription')) {
            $patches['hero_slogan'] = $sanitize($this->heroDescription);
            $patches['heroDescription'] = $patches['hero_slogan'];
        } elseif ($this->has('heroSlogan')) {
            $patches['hero_slogan'] = $sanitize($this->heroSlogan);
            $patches['heroSlogan'] = $patches['hero_slogan'];
        }

        // 3. Normalizar enable_carousel con filter_var()
        if ($this->has('enable_carousel')) {
            $val = filter_var($this->enable_carousel, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['enable_carousel'] = $val !== null ? $val : $this->enable_carousel;
            if (!$this->has('use_carousel')) $patches['use_carousel'] = $patches['enable_carousel'];
        } elseif ($this->has('use_carousel')) {
            $val = filter_var($this->use_carousel, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['enable_carousel'] = $val !== null ? $val : $this->use_carousel;
            $patches['use_carousel'] = $patches['enable_carousel'];
        } elseif ($this->has('useCarousel')) {
            $val = filter_var($this->useCarousel, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['enable_carousel'] = $val !== null ? $val : $this->useCarousel;
            $patches['useCarousel'] = $patches['enable_carousel'];
        }

        // 4. Normalizar carousel_images
        if ($this->has('carousel_images')) {
            // Ya viene en el campo esperado
        } elseif ($this->has('banner_images')) {
            $patches['carousel_images'] = $this->banner_images;
        } elseif ($this->has('bannerImages')) {
            $patches['carousel_images'] = $this->bannerImages;
        }

        // 4b. Normalizar imágenes hero_background_image y history_background_image
        $rawHistoria = $this->input('historia_config') ?? $this->input('historiaConfig') ?? null;

        if (!$this->has('hero_background_image')) {
            if ($this->has('hero_image_url')) {
                $patches['hero_background_image'] = $this->hero_image_url;
            } elseif ($this->has('hero_image')) {
                $patches['hero_background_image'] = $this->hero_image;
            } elseif ($this->has('heroImageUrl')) {
                $patches['hero_background_image'] = $this->heroImageUrl;
            } elseif ($this->has('heroImage')) {
                $patches['hero_background_image'] = $this->heroImage;
            }
        }

        if (!$this->has('history_background_image')) {
            if ($this->has('history_image_url')) {
                $patches['history_background_image'] = $this->history_image_url;
            } elseif ($this->has('history_image')) {
                $patches['history_background_image'] = $this->history_image;
            } elseif ($this->has('historyImageUrl')) {
                $patches['history_background_image'] = $this->historyImageUrl;
            } elseif ($this->has('historyImage')) {
                $patches['history_background_image'] = $this->historyImage;
            } elseif ($this->has('imagen_fondo')) {
                $patches['history_background_image'] = $this->imagen_fondo;
            } elseif ($this->has('fondo')) {
                $patches['history_background_image'] = $this->fondo;
            } elseif (is_array($rawHistoria)) {
                if (isset($rawHistoria['fondo'])) {
                    $patches['history_background_image'] = $rawHistoria['fondo'];
                } elseif (isset($rawHistoria['imagenFondo'])) {
                    $patches['history_background_image'] = $rawHistoria['imagenFondo'];
                } elseif (isset($rawHistoria['image'])) {
                    $patches['history_background_image'] = $rawHistoria['image'];
                }
            }
        }

        // 5. Sanitizar y normalizar historia (planos o anidados en historia_config)
        if (is_array($rawHistoria)) {
            if (!$this->has('history_title') && isset($rawHistoria['titulo'])) {
                $patches['history_title'] = $sanitize($rawHistoria['titulo']);
            }
            if (!$this->has('history_description') && isset($rawHistoria['descripcion'])) {
                $patches['history_description'] = $sanitize($rawHistoria['descripcion']);
            }
            if (!$this->has('foundation_year') && (isset($rawHistoria['anio']) || isset($rawHistoria['anioFundacion']))) {
                $patches['foundation_year'] = (int) ($rawHistoria['anio'] ?? $rawHistoria['anioFundacion']);
            }
            if (!$this->has('features') && isset($rawHistoria['caracteristicas'])) {
                $patches['features'] = $rawHistoria['caracteristicas'];
            }
        }

        if ($this->has('history_title')) {
            $patches['history_title'] = $sanitize($this->history_title);
        } elseif ($this->has('historia_titulo')) {
            $patches['history_title'] = $sanitize($this->historia_titulo);
        }

        if ($this->has('history_description')) {
            $patches['history_description'] = $sanitize($this->history_description);
        } elseif ($this->has('historia_descripcion')) {
            $patches['history_description'] = $sanitize($this->historia_descripcion);
        }

        if ($this->has('foundation_year')) {
            $patches['foundation_year'] = is_numeric($this->foundation_year) ? (int)$this->foundation_year : $this->foundation_year;
        } elseif ($this->has('history_year')) {
            $patches['foundation_year'] = is_numeric($this->history_year) ? (int)$this->history_year : $this->history_year;
        } elseif ($this->has('historia_anio')) {
            $patches['foundation_year'] = is_numeric($this->historia_anio) ? (int)$this->historia_anio : $this->historia_anio;
        } elseif ($this->has('anio_fundacion')) {
            $patches['foundation_year'] = is_numeric($this->anio_fundacion) ? (int)$this->anio_fundacion : $this->anio_fundacion;
        }

        // 6. Sanitizar y normalizar Features
        $rawFeatures = $patches['features'] ?? $this->input('features') ?? $this->input('caracteristicas') ?? $this->input('history_features') ?? null;
        if (is_array($rawFeatures)) {
            $cleanedFeatures = [];
            foreach ($rawFeatures as $idx => $feat) {
                if (is_array($feat)) {
                    $cleanedFeatures[$idx] = [
                        'icon'        => strtolower(trim((string)($feat['icon'] ?? $feat['icono'] ?? ''))),
                        'title'       => $sanitize($feat['title'] ?? $feat['titulo'] ?? ''),
                        'description' => $sanitize($feat['description'] ?? $feat['descripcion'] ?? ''),
                    ];
                } else {
                    $cleanedFeatures[$idx] = $feat;
                }
            }
            $patches['features'] = $cleanedFeatures;
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
        $validIcons = implode(',', [
            'star', 'heart', 'gem', 'award', 'sparkles', 'clock', 'compass',
            'utensils', 'coffee', 'leaf', 'chef-hat', 'chef_hat', 'map-pin',
            'map_pin', 'shield-check', 'shield_check', 'crown', 'gift',
            'calendar', 'percent', 'check', 'phone', 'wine', 'beer', 'soup', 'smile'
        ]);

        $heroImageRule = ($this->has('hero_background_image') && is_string($this->hero_background_image))
            ? 'nullable|string'
            : 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240';

        $historyImageRule = ($this->has('history_background_image') && is_string($this->history_background_image))
            ? 'nullable|string'
            : 'nullable|image|mimes:jpg,jpeg,png,webp|max:10240';

        $carouselItemRule = 'image|mimes:jpg,jpeg,png,webp|max:10240';
        if (is_array($this->carousel_images) && !empty($this->carousel_images)) {
            $hasOnlyStrings = true;
            foreach ($this->carousel_images as $item) {
                if (!is_string($item)) {
                    $hasOnlyStrings = false;
                    break;
                }
            }
            if ($hasOnlyStrings) {
                $carouselItemRule = 'string';
            }
        }

        return [
            // ── Textos Principales ──────────────────────────────────────────
            'hero_title'                => 'required|string|min:3|max:100',
            'hero_slogan'               => 'nullable|string|max:150',

            // ── Archivos de Imagen ──────────────────────────────────────────
            'hero_background_image'     => $heroImageRule,

            // ── Carrusel ───────────────────────────────────────────────────
            'enable_carousel'           => 'required|boolean',
            'carousel_images'           => 'required_if:enable_carousel,true,1|array|min:2|max:6',
            'carousel_images.*'         => $carouselItemRule,

            // ── Sección Historia ───────────────────────────────────────────
            'history_title'             => 'required|string|max:100',
            'history_description'       => 'required|string|max:300',
            'foundation_year'           => 'required|integer|min:1900|max:2026',
            'history_background_image'  => $historyImageRule,

            // ── Características (Features) ─────────────────────────────────
            'features'                  => 'required|array|size:3',
            'features.*.icon'           => "required|string|in:{$validIcons}",
            'features.*.title'          => 'required|string|max:50',
            'features.*.description'    => 'required|string|max:150',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'hero_title.required'             => 'El título principal de la portada es obligatorio.',
            'hero_title.min'                  => 'El título principal debe tener al menos 3 caracteres.',
            'hero_title.max'                  => 'El título principal no puede exceder los 100 caracteres.',

            'hero_slogan.max'                 => 'El eslogan de la portada no puede exceder los 150 caracteres.',

            'hero_background_image.image'     => 'La imagen de fondo de la portada debe ser un archivo de imagen válido.',
            'hero_background_image.mimes'     => 'La imagen de fondo debe estar en formato jpg, jpeg, png o webp.',
            'hero_background_image.max'       => 'La imagen de fondo de la portada no puede superar los 10MB (10240 KB).',

            'enable_carousel.required'        => 'Debe especificar si el carrusel está activado.',
            'enable_carousel.boolean'         => 'El valor de activación del carrusel debe ser booleano.',

            'carousel_images.required_if'     => 'Las imágenes del carrusel son obligatorias cuando el carrusel está activado.',
            'carousel_images.array'           => 'Las imágenes del carrusel deben ser una lista de archivos.',
            'carousel_images.min'             => 'El carrusel debe incluir al menos 2 imágenes.',
            'carousel_images.max'             => 'El carrusel no puede contener más de 6 imágenes.',
            'carousel_images.*.image'         => 'Cada elemento del carrusel debe ser un archivo de imagen válido.',
            'carousel_images.*.mimes'         => 'Cada imagen del carrusel debe tener formato jpg, jpeg, png o webp.',
            'carousel_images.*.max'           => 'Cada imagen del carrusel no puede superar los 10MB (10240 KB).',

            'history_title.required'          => 'El título de la historia es obligatorio.',
            'history_title.max'               => 'El título de la historia no puede superar los 100 caracteres.',

            'history_description.required'    => 'La descripción de la historia es obligatoria.',
            'history_description.max'         => 'La descripción de la historia no puede superar los 300 caracteres.',

            'foundation_year.required'        => 'El año de fundación es obligatorio.',
            'foundation_year.integer'         => 'El año de fundación debe ser un número entero.',
            'foundation_year.min'             => 'El año de fundación no puede ser anterior a 1900.',
            'foundation_year.max'             => 'El año de fundación no puede ser superior a 2026.',

            'history_background_image.image'  => 'La imagen de fondo de historia debe ser una imagen válida.',
            'history_background_image.mimes'  => 'La imagen de fondo de historia debe estar en formato jpg, jpeg, png o webp.',
            'history_background_image.max'    => 'La imagen de fondo de historia no puede superar los 10MB (10240 KB).',

            'features.required'               => 'Las características de la historia son obligatorias.',
            'features.array'                  => 'Las características deben ser una lista.',
            'features.size'                   => 'Debe proporcionar exactamente 3 características.',

            'features.*.icon.required'        => 'El ícono de la característica es obligatorio.',
            'features.*.icon.in'              => 'El ícono seleccionado no es válido.',

            'features.*.title.required'       => 'El título de la característica es obligatorio.',
            'features.*.title.max'            => 'El título de la característica no puede superar los 50 caracteres.',

            'features.*.description.required' => 'La descripción de la característica es obligatoria.',
            'features.*.description.max'      => 'La descripción de la característica no puede superar los 150 caracteres.',
        ];
    }
}
