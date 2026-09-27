<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateExtraRequest extends FormRequest
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
        if ($this->has('is_free')) {
            $this->merge([
                'is_free' => filter_var($this->is_free, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $this->is_free,
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $extra = $this->route('extra') ?? $this->route('id');
        $extraId = is_object($extra) ? $extra->id : $extra;

        return [
            'name'    => ($this->isMethod('patch') ? 'sometimes|' : '') . 'required|string|min:2|max:50|unique:extras,name,' . $extraId,
            'is_free' => ($this->isMethod('patch') ? 'sometimes|' : '') . 'required|boolean',
            'price'   => 'nullable|numeric|min:0|max:999.99',
            'active'  => 'boolean',
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
            'name.required' => 'El nombre del extra es obligatorio.',
            'name.string'   => 'El nombre del extra debe ser texto.',
            'name.min'      => 'El nombre del extra debe tener al menos 2 caracteres.',
            'name.max'      => 'El nombre del extra no debe superar los 50 caracteres.',
            'name.unique'   => 'Ya existe un extra con este nombre.',

            'is_free.required' => 'El campo is_free es obligatorio.',
            'is_free.boolean'  => 'El campo is_free debe ser booleano.',

            'price.numeric' => 'El precio debe ser un valor numérico.',
            'price.min'     => 'El precio no puede ser menor a 0.',
            'price.max'     => 'El precio no debe superar 999.99.',
        ];
    }
}
