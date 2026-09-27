<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFeaturedDishesRequest extends FormRequest
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
     * ACCIÓN: Aplicar strip_tags() exclusivamente a subtitle, title y button_text.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        $sanitizeText = function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value)));
        };

        // Soporte tanto para payload plano como anidado en platillos_seccion
        $rawPlatillos = $this->input('platillos_seccion') ?? $this->input('platillosSeccion') ?? null;

        // 1. Sanitizar subtitle
        if ($this->has('subtitle')) {
            $patches['subtitle'] = $sanitizeText($this->subtitle);
        } elseif ($this->has('menu_subtitle')) {
            $patches['subtitle'] = $sanitizeText($this->menu_subtitle);
        } elseif ($this->has('menuSubtitle')) {
            $patches['subtitle'] = $sanitizeText($this->menuSubtitle);
        } elseif (is_array($rawPlatillos) && (isset($rawPlatillos['labelSuperior']) || isset($rawPlatillos['subtitle']))) {
            $patches['subtitle'] = $sanitizeText($rawPlatillos['labelSuperior'] ?? $rawPlatillos['subtitle']);
        }

        // 2. Sanitizar title
        if ($this->has('title')) {
            $patches['title'] = $sanitizeText($this->title);
        } elseif ($this->has('menu_title')) {
            $patches['title'] = $sanitizeText($this->menu_title);
        } elseif ($this->has('menuTitle')) {
            $patches['title'] = $sanitizeText($this->menuTitle);
        } elseif (is_array($rawPlatillos) && (isset($rawPlatillos['tituloPrincipal']) || isset($rawPlatillos['title']))) {
            $patches['title'] = $sanitizeText($rawPlatillos['tituloPrincipal'] ?? $rawPlatillos['title']);
        }

        // 3. Sanitizar button_text
        if ($this->has('button_text')) {
            $patches['button_text'] = $sanitizeText($this->button_text);
        } elseif ($this->has('cta_menu_text')) {
            $patches['button_text'] = $sanitizeText($this->cta_menu_text);
        } elseif ($this->has('ctaMenuText')) {
            $patches['button_text'] = $sanitizeText($this->ctaMenuText);
        } elseif (is_array($rawPlatillos) && (isset($rawPlatillos['textoDebajoBoton']) || isset($rawPlatillos['button_text']))) {
            $patches['button_text'] = $sanitizeText($rawPlatillos['textoDebajoBoton'] ?? $rawPlatillos['button_text']);
        }

        // 4. Normalizar categorías destacadas
        if ($this->has('featured_categories')) {
            // Ya viene en campo principal
        } elseif ($this->has('selected_categories')) {
            $patches['featured_categories'] = $this->selected_categories;
        } elseif ($this->has('selectedCategories')) {
            $patches['featured_categories'] = $this->selectedCategories;
        } elseif (is_array($rawPlatillos) && (isset($rawPlatillos['selected_categories']) || isset($rawPlatillos['featured_categories']))) {
            $patches['featured_categories'] = $rawPlatillos['selected_categories'] ?? $rawPlatillos['featured_categories'];
        }

        // 5. Normalizar platillos destacados
        if ($this->has('featured_dishes')) {
            // Ya viene en campo principal
        } elseif ($this->has('selected_dishes')) {
            $patches['featured_dishes'] = $this->selected_dishes;
        } elseif ($this->has('selectedDishes')) {
            $patches['featured_dishes'] = $this->selectedDishes;
        } elseif (is_array($rawPlatillos) && (isset($rawPlatillos['selected_dishes']) || isset($rawPlatillos['featured_dishes']))) {
            $patches['featured_dishes'] = $rawPlatillos['selected_dishes'] ?? $rawPlatillos['featured_dishes'];
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
            // Reglas de Textos
            'subtitle'              => 'nullable|string|max:100',
            'title'                 => 'required|string|max:100',
            'button_text'           => 'nullable|string|max:150',

            // Optimización de Arreglos (Categorías)
            'featured_categories'   => 'nullable|array|max:4',
            'featured_categories.*' => 'integer|exists:categories,id',

            // Optimización de Arreglos (Platillos y Regla de Negocio Avanzada)
            'featured_dishes'       => 'nullable|array',
            'featured_dishes.*'     => [
                'integer',
                Rule::exists('dishes', 'id')->whereIn('category_id', $this->featured_categories ?? []),
            ],
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'title.required'                 => 'El título de la sección de platillos destacados es obligatorio.',
            'title.string'                   => 'El título debe ser una cadena de texto.',
            'title.max'                      => 'El título no puede exceder los 100 caracteres.',

            'subtitle.string'                => 'El subtítulo debe ser una cadena de texto.',
            'subtitle.max'                   => 'El subtítulo no puede exceder los 100 caracteres.',

            'button_text.string'             => 'El texto del botón debe ser una cadena de texto.',
            'button_text.max'                => 'El texto del botón no puede exceder los 150 caracteres.',

            'featured_categories.required'   => 'Debe seleccionar al menos una categoría destacada.',
            'featured_categories.array'      => 'Las categorías destacadas deben ser enviadas como una lista.',
            'featured_categories.max'        => 'No puede seleccionar más de 4 categorías destacadas.',
            'featured_categories.*.integer'  => 'Cada categoría seleccionada debe tener un identificador numérico válido.',
            'featured_categories.*.exists'   => 'Una o más de las categorías seleccionadas no existen en el sistema.',

            'featured_dishes.array'          => 'Los platillos destacados deben ser una lista.',
            'featured_dishes.*.integer'      => 'Cada platillo seleccionado debe tener un identificador numérico válido.',
            'featured_dishes.*.exists'       => 'Uno o más platillos no existen o no pertenecen a las categorías seleccionadas.',
        ];
    }
}
