<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
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
        $categoryId = $this->route('id') ?? $this->route('category');
        if (is_object($categoryId)) {
            $categoryId = $categoryId->id;
        }

        $isUpdate = $this->isMethod('put') || $this->isMethod('patch');

        $uniqueRule = $isUpdate && $categoryId
            ? 'unique:categories,name,' . $categoryId
            : 'unique:categories,name';

        return [
            'name'       => ($isUpdate ? 'sometimes|' : '') . 'required|string|min:3|max:50|' . $uniqueRule,
            'image_url'  => 'nullable|string',
            'active'     => 'boolean',
            'time_start' => 'nullable|string',
            'time_end'   => 'nullable|string',
            'days'       => 'nullable|array',
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
        ];
    }
}
