<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExclusiveServicesRequest extends FormRequest
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
     * ACCIÓN: Utilizar un bucle para aplicar strip_tags() a todos los campos
     * title y description dentro del arreglo enviado, antes de que toquen las reglas de validación.
     */
    protected function prepareForValidation(): void
    {
        $rawServices = $this->input('services')
            ?? $this->input('servicios')
            ?? $this->input('servicios_config')
            ?? $this->input('serviciosConfig')
            ?? null;

        if (is_array($rawServices)) {
            $iconMap = [
                'mappin'      => 'MapPin',
                'map-pin'     => 'MapPin',
                'map_pin'     => 'MapPin',
                'chefhat'     => 'ChefHat',
                'chef-hat'    => 'ChefHat',
                'chef_hat'    => 'ChefHat',
                'shieldcheck' => 'ShieldCheck',
                'shield-check'=> 'ShieldCheck',
                'shield_check'=> 'ShieldCheck',
                'star'        => 'Star',
                'gem'         => 'Gem',
                'heart'       => 'Heart',
            ];

            $cleaned = [];
            foreach ($rawServices as $idx => $item) {
                if (!is_array($item)) {
                    $cleaned[$idx] = $item;
                    continue;
                }

                $rawTitle = $item['title'] ?? $item['titulo'] ?? '';
                $rawDesc = $item['description'] ?? $item['descripcion'] ?? '';
                $rawIcon = trim((string)($item['icon'] ?? $item['icono'] ?? ''));

                // Normalizar icono si coincide en minúsculas con la lista blanca
                $iconLower = strtolower(str_replace(['-', '_', ' '], '', $rawIcon));
                $canonicalIcon = $iconMap[$iconLower] ?? $rawIcon;

                $cleanTitle = is_string($rawTitle)
                    ? trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawTitle)))
                    : $rawTitle;

                $cleanDesc = is_string($rawDesc)
                    ? trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawDesc)))
                    : $rawDesc;

                $cleaned[$idx] = [
                    'icon'        => $canonicalIcon,
                    'icono'       => $canonicalIcon,
                    'title'       => $cleanTitle,
                    'titulo'      => $cleanTitle,
                    'description' => $cleanDesc,
                    'descripcion' => $cleanDesc,
                ];
            }

            $this->merge([
                'services'         => $cleaned,
                'servicios_config' => $cleaned,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'services'               => 'required|array|size:3',
            'services.*.icon'        => 'required|string|in:MapPin,ChefHat,ShieldCheck,Star,Gem,Heart',
            'services.*.title'       => 'required|string|min:3|max:50',
            'services.*.description' => 'required|string|max:150',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'services.required'               => 'Los servicios exclusivos son obligatorios.',
            'services.array'                  => 'Los servicios exclusivos deben enviarse como una lista.',
            'services.size'                   => 'Debe configurar exactamente 3 servicios exclusivos para mantener el diseño.',

            'services.*.icon.required'        => 'El ícono del servicio es obligatorio.',
            'services.*.icon.string'          => 'El ícono del servicio debe ser una cadena de texto.',
            'services.*.icon.in'              => 'El ícono seleccionado no es válido. Solo se permiten: MapPin, ChefHat, ShieldCheck, Star, Gem, Heart.',

            'services.*.title.required'       => 'El título del servicio es obligatorio.',
            'services.*.title.string'         => 'El título del servicio debe ser una cadena de texto.',
            'services.*.title.min'            => 'El título del servicio debe tener al menos 3 caracteres.',
            'services.*.title.max'            => 'El título del servicio no puede superar los 50 caracteres.',

            'services.*.description.required' => 'La descripción del servicio es obligatoria.',
            'services.*.description.string'   => 'La descripción del servicio debe ser una cadena de texto.',
            'services.*.description.max'      => 'La descripción del servicio no puede superar los 150 caracteres.',
        ];
    }
}