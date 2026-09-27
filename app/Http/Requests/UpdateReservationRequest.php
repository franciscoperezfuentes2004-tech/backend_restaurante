<?php

namespace App\Http\Requests;

use App\Models\Mesa;
use Illuminate\Foundation\Http\FormRequest;

class UpdateReservationRequest extends FormRequest
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
        if ($this->has('customer_name') && !$this->has('client_name')) {
            $this->merge(['client_name' => $this->customer_name]);
        }
        if ($this->has('customer_phone') && !$this->has('phone')) {
            $this->merge(['phone' => $this->customer_phone]);
        }
        if ($this->has('customer_email') && !$this->has('email')) {
            $this->merge(['email' => $this->customer_email]);
        }
        if ($this->has('guests_count') && !$this->has('people_count')) {
            $this->merge(['people_count' => $this->guests_count]);
        }
        if ($this->has('reservation_date') && !$this->has('date')) {
            $this->merge(['date' => $this->reservation_date]);
        }
        if ($this->has('reservation_time') && !$this->has('time')) {
            $this->merge(['time' => $this->reservation_time]);
        }
        if ($this->has('special_requests') && !$this->has('notes')) {
            $this->merge(['notes' => $this->special_requests]);
        }
        if ($this->has('table_number') && !$this->has('table_id')) {
            if (is_numeric($this->table_number)) {
                $this->merge(['table_id' => (int) $this->table_number]);
            } else {
                $digits = preg_replace('/[^0-9]/', '', (string) $this->table_number);
                if (!empty($digits)) {
                    $m = Mesa::where('numero_mesa', (int) $digits)->first();
                    if ($m) {
                        $this->merge(['table_id' => $m->id]);
                    }
                }
            }
        }

        if ($this->has('time') && is_string($this->time) && strlen(trim($this->time)) >= 5) {
            $this->merge(['time' => substr(trim($this->time), 0, 5)]);
        }

        if ($this->has('phone') && is_string($this->phone)) {
            $cleanPhone = preg_replace('/[-\s]/', '', trim($this->phone));
            if (strlen($cleanPhone) === 10 && ctype_digit($cleanPhone)) {
                $this->merge(['phone' => $cleanPhone]);
            }
        }

        if ($this->has('notes') && is_string($this->notes)) {
            $this->merge(['notes' => trim(strip_tags($this->notes))]);
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
            'client_name'  => 'sometimes|required|string|min:3|max:100',
            'phone'        => ['sometimes', 'required', 'string', 'regex:/^[0-9]{10}$/'],
            'email'        => 'nullable|email|max:150',
            'people_count' => 'sometimes|required|integer|min:1|max:50',
            'date'         => 'sometimes|required|date',
            'time'         => 'sometimes|required|date_format:H:i',
            'area_id'      => 'sometimes|required|integer|exists:areas,id',
            'table_id'     => 'sometimes|required|integer|exists:tables,id',
            'status'       => 'sometimes|required|string|in:pending,confirmed,cancelled',
            'notes'        => 'nullable|string|max:250',
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
            'client_name.required'      => 'El nombre del cliente es obligatorio.',
            'client_name.string'        => 'El nombre del cliente debe ser texto.',
            'client_name.min'           => 'El nombre del cliente debe tener al menos 3 caracteres.',
            'client_name.max'           => 'El nombre del cliente no debe superar los 100 caracteres.',

            'phone.required'            => 'El teléfono es obligatorio.',
            'phone.regex'               => 'El teléfono debe contener exactamente 10 dígitos numéricos.',

            'email.email'               => 'El correo electrónico no es válido.',
            'email.max'                 => 'El correo electrónico no debe superar los 150 caracteres.',

            'people_count.required'     => 'El número de personas es obligatorio.',
            'people_count.integer'      => 'El número de personas debe ser un número entero.',
            'people_count.min'          => 'La reservación debe ser para al menos 1 persona.',
            'people_count.max'          => 'La reservación no puede exceder las 50 personas.',

            'date.required'             => 'La fecha de reservación es obligatoria.',
            'date.date'                 => 'La fecha de reservación no es válida.',

            'time.required'             => 'La hora de reservación es obligatoria.',
            'time.date_format'          => 'La hora de reservación debe tener el formato H:i.',

            'area_id.required'          => 'El área es obligatoria.',
            'area_id.integer'           => 'El identificador del área debe ser un entero.',
            'area_id.exists'            => 'El área seleccionada no existe.',

            'table_id.required'         => 'La mesa es obligatoria.',
            'table_id.integer'          => 'El identificador de la mesa debe ser un entero.',
            'table_id.exists'           => 'La mesa seleccionada no existe.',

            'status.required'           => 'El estado de la reservación es obligatorio.',
            'status.in'                 => 'El estado debe ser pending, confirmed o cancelled.',

            'notes.string'              => 'Las notas deben ser una cadena de texto.',
            'notes.max'                 => 'Las notas no deben superar los 250 caracteres.',
        ];
    }
}
