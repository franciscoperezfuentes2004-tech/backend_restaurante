<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentMethodsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización y normalización previa a la validación.
     */
    protected function prepareForValidation(): void
    {
        $patches = [];

        // 1. Sincronizar cash <-> acepta_efectivo
        if ($this->has('cash')) {
            $val = filter_var($this->cash, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['cash'] = $val !== null ? $val : $this->cash;
            $patches['acepta_efectivo'] = $patches['cash'];
        } elseif ($this->has('acepta_efectivo')) {
            $val = filter_var($this->acepta_efectivo, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['cash'] = $val !== null ? $val : $this->acepta_efectivo;
            $patches['acepta_efectivo'] = $patches['cash'];
        }

        // 2. Sincronizar card <-> acepta_tarjeta
        if ($this->has('card')) {
            $val = filter_var($this->card, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['card'] = $val !== null ? $val : $this->card;
            $patches['acepta_tarjeta'] = $patches['card'];
        } elseif ($this->has('acepta_tarjeta')) {
            $val = filter_var($this->acepta_tarjeta, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['card'] = $val !== null ? $val : $this->acepta_tarjeta;
            $patches['acepta_tarjeta'] = $patches['card'];
        }

        // 3. Sincronizar transfer <-> acepta_transferencia
        if ($this->has('transfer')) {
            $val = filter_var($this->transfer, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['transfer'] = $val !== null ? $val : $this->transfer;
            $patches['acepta_transferencia'] = $patches['transfer'];
        } elseif ($this->has('acepta_transferencia')) {
            $val = filter_var($this->acepta_transferencia, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $patches['transfer'] = $val !== null ? $val : $this->acepta_transferencia;
            $patches['acepta_transferencia'] = $patches['transfer'];
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
            // ── Métodos de Pago ─────────────────────────────────────────────
            'cash'                 => 'required|boolean',
            'card'                 => 'required|boolean',
            'transfer'             => 'required|boolean',
            'acepta_efectivo'      => 'sometimes|boolean',
            'acepta_tarjeta'       => 'sometimes|boolean',
            'acepta_transferencia' => 'sometimes|boolean',

            // ── Datos Bancarios Opcionales ──────────────────────────────────
            'banco_nombre'         => 'nullable|string|max:100',
            'banco_clabe'          => 'nullable|string|max:25',
            'banco_titular'        => 'nullable|string|max:150',
            'bank_name'            => 'nullable|string|max:100',
            'bank_clabe'           => 'nullable|string|max:25',
            'bank_account_holder'  => 'nullable|string|max:150',
        ];
    }

    /**
     * Validación Condicional: Aborta la petición si los tres valores llegan como false.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $cash = filter_var($this->input('cash'), FILTER_VALIDATE_BOOLEAN);
            $card = filter_var($this->input('card'), FILTER_VALIDATE_BOOLEAN);
            $transfer = filter_var($this->input('transfer'), FILTER_VALIDATE_BOOLEAN);

            if (!$cash && !$card && !$transfer) {
                $message = 'Debe mantener al menos un método de pago activo (efectivo, tarjeta o transferencia).';
                $validator->errors()->add('payment_methods', $message);
                $validator->errors()->add('cash', $message);
            }
        });
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'cash.required'     => 'El método de pago en efectivo es obligatorio.',
            'cash.boolean'      => 'El método de pago en efectivo debe ser un valor booleano.',
            'card.required'     => 'El método de pago con tarjeta es obligatorio.',
            'card.boolean'      => 'El método de pago con tarjeta debe ser un valor booleano.',
            'transfer.required' => 'El método de pago por transferencia es obligatorio.',
            'transfer.boolean'  => 'El método de pago por transferencia debe ser un valor booleano.',
        ];
    }
}
