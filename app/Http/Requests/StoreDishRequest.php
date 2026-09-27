<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDishRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('description') && is_string($this->description)) {
            $this->merge([
                'description' => trim(strip_tags($this->description)),
            ]);
        }

        $catInput = $this->input('category_id') ?? $this->input('category') ?? $this->input('category_name');
        if (!empty($catInput) && is_numeric($catInput)) {
            $this->merge(['category_id' => (int) $catInput]);
        }

        if ($this->has('extras') && is_array($this->input('extras'))) {
            $rawExtras = $this->input('extras');
            $cleanExtras = [];

            foreach ($rawExtras as $item) {
                if (is_numeric($item)) {
                    $cleanExtras[] = (int) $item;
                } elseif (is_string($item) && !empty(trim($item)) && trim($item) !== '[object Object]') {
                    $ex = \App\Models\Extra::where('name', 'like', trim($item))->first();
                    if ($ex) {
                        $cleanExtras[] = $ex->id;
                    }
                } elseif (is_array($item) && isset($item['id']) && is_numeric($item['id'])) {
                    $cleanExtras[] = (int) $item['id'];
                }
            }

            $this->merge(['extras' => array_values(array_unique($cleanExtras))]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'               => 'required|string|min:3|max:100|unique:dishes,name',
            'description'        => 'nullable|string|max:250',
            'price'              => 'required|numeric|min:0.01|max:99999.99',
            'category_id'         => 'required|integer|exists:categories,id',
            'imagen'              => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'image'               => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'foto'                => [
                'nullable',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'image_url'           => 'nullable|string',
            'allergens'           => 'nullable|array',
            'ingredients'         => 'nullable|array',
            'extras'              => 'nullable|array',
            'extras.*'            => 'exists:extras,id',
            'allow_observations'  => 'boolean',
            'allow_spice_level'   => 'boolean',
            'is_available'        => 'boolean',
            'limitar_dias'        => 'nullable|boolean',
            'dias_disponibilidad' => 'nullable|array',
        ];
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required'        => 'El nombre del platillo es obligatorio.',
            'name.string'          => 'El nombre del platillo debe ser texto.',
            'name.min'             => 'El nombre del platillo debe tener al menos 3 caracteres.',
            'name.max'             => 'El nombre del platillo no debe exceder los 100 caracteres.',
            'name.unique'          => 'Ya existe un platillo con este nombre.',

            'description.string'   => 'La descripción debe ser una cadena de texto.',
            'description.max'      => 'La descripción no debe exceder los 250 caracteres.',

            'price.required'       => 'El precio es obligatorio.',
            'price.numeric'        => 'El precio debe ser un número válido.',
            'price.min'            => 'El precio debe ser de al menos 0.01.',
            'price.max'            => 'El precio no debe exceder 99999.99.',

            'category_id.required' => 'La categoría es obligatoria.',
            'category_id.integer'  => 'El ID de la categoría debe ser un número entero.',
            'category_id.exists'   => 'La categoría seleccionada no existe.',

            'imagen.image'         => 'El archivo debe ser una imagen válida.',
            'imagen.mimes'         => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'imagen.max'           => 'La imagen no debe pesar más de 10MB.',

            'image.image'          => 'El archivo debe ser una imagen válida.',
            'image.mimes'          => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'image.max'            => 'La imagen no debe pesar más de 10MB.',

            'foto.image'           => 'El archivo debe ser una imagen válida.',
            'foto.mimes'           => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'foto.max'             => 'La imagen no debe pesar más de 10MB.',
        ];
    }
}
