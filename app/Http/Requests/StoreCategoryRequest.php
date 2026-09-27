<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                => 'required|string|min:3|max:50|unique:categories,name',
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
            'active'              => 'boolean',
            'limitar_dias'        => 'nullable|boolean',
            'dias_disponibilidad' => 'nullable|array',
            'time_start'          => 'nullable|string',
            'time_end'            => 'nullable|string',
            'days'                => 'nullable|array',
        ];
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la categoría es obligatorio.',
            'name.string'   => 'El nombre de la categoría debe ser texto.',
            'name.min'      => 'El nombre de la categoría debe tener al menos 3 caracteres.',
            'name.max'      => 'El nombre de la categoría no debe exceder los 50 caracteres.',
            'name.unique'   => 'Esta categoría ya existe',

            'imagen.image'  => 'El archivo debe ser una imagen válida.',
            'imagen.mimes'  => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'imagen.max'    => 'La imagen no debe pesar más de 10MB.',

            'image.image'   => 'El archivo debe ser una imagen válida.',
            'image.mimes'   => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'image.max'     => 'La imagen no debe pesar más de 10MB.',

            'foto.image'    => 'El archivo debe ser una imagen válida.',
            'foto.mimes'    => 'Solo se permiten imágenes en formato JPG, PNG o WEBP.',
            'foto.max'      => 'La imagen no debe pesar más de 10MB.',
        ];
    }
}
