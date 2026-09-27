<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddDishToOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        // El acceso por rol se gestiona a nivel de middleware en las rutas
        return true;
    }

    /**
     * Sanitización previa: Normalización de campos, eliminación de totales del cliente
     * y sanitización estricta de notas con strip_tags() para proteger la cocina contra XSS.
     */
    protected function prepareForValidation(): void
    {
        $rawOrderId = $this->input('order_id')
            ?? $this->route('order')
            ?? $this->route('id')
            ?? $this->route('order_id');

        $orderId = $rawOrderId instanceof \App\Models\Order ? $rawOrderId->id : (is_numeric($rawOrderId) ? (int) $rawOrderId : $rawOrderId);

        $rawDishId = $this->input('dish_id')
            ?? $this->input('id')
            ?? $this->input('platillo_id');

        $dishId = is_numeric($rawDishId) ? (int) $rawDishId : $rawDishId;

        $rawQuantity = $this->input('quantity') ?? $this->input('cantidad') ?? 1;
        $quantity = is_numeric($rawQuantity) ? max(1, (int) $rawQuantity) : $rawQuantity;

        // Sanitización estricta de notas contra inyecciones XSS y scripts maliciosos
        $rawNotes = $this->input('notes') ?? $this->input('notas') ?? $this->input('observaciones');
        $cleanNotes = null;
        if ($rawNotes !== null) {
            $noScripts = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', (string) $rawNotes);
            $cleanNotes = trim(strip_tags($noScripts));
        }

        // Normalizar extras a un arreglo limpio de enteros
        $rawExtras = $this->input('extras') ?? $this->input('extra_ids') ?? [];
        $cleanExtras = [];

        if (is_array($rawExtras)) {
            foreach ($rawExtras as $extra) {
                if (is_numeric($extra)) {
                    $cleanExtras[] = (int) $extra;
                } elseif (is_array($extra) && isset($extra['id']) && is_numeric($extra['id'])) {
                    $cleanExtras[] = (int) $extra['id'];
                } elseif (is_array($extra) && isset($extra['extra_id']) && is_numeric($extra['extra_id'])) {
                    $cleanExtras[] = (int) $extra['extra_id'];
                }
            }
        }

        // Cero Confianza Financiera: remover precios y totales enviados por el cliente
        $this->request->remove('price');
        $this->request->remove('precio');
        $this->request->remove('total');
        $this->request->remove('subtotal');
        $this->request->remove('total_amount');

        $this->merge([
            'order_id' => $orderId,
            'dish_id'  => $dishId,
            'quantity' => $quantity,
            'notes'    => $cleanNotes,
            'extras'   => $cleanExtras,
        ]);
    }

    /**
     * Reglas de validación:
     * - Cero Confianza: Validación de pertenencia estricta de extras mediante Rule::exists('dish_extra', 'extra_id')->where('dish_id', $this->dish_id)
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'dish_id'  => [
                'required',
                'integer',
                Rule::exists('dishes', 'id')->where(function ($q) {
                    $q->whereNull('deleted_at');
                    if (\Illuminate\Support\Facades\Schema::hasColumn('dishes', 'is_available')) {
                        $q->where('is_available', true);
                    }
                }),
            ],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'notes'    => ['nullable', 'string', 'max:500'],
            'extras'   => ['nullable', 'array'],
            'extras.*' => [
                'integer',
                Rule::exists('dish_extra', 'extra_id')
                    ->where('dish_id', $this->dish_id),
            ],
        ];
    }

    /**
     * Mensajes de validación en español.
     */
    public function messages(): array
    {
        return [
            'order_id.required' => 'La orden es obligatoria.',
            'order_id.exists'   => 'La orden seleccionada no existe.',
            'dish_id.required'  => 'El platillo es obligatorio.',
            'dish_id.exists'    => 'El platillo seleccionado no existe o no se encuentra disponible.',
            'quantity.required' => 'La cantidad es obligatoria.',
            'quantity.min'      => 'La cantidad debe ser al menos 1.',
            'quantity.max'      => 'La cantidad máxima por partida es 100.',
            'extras.*.exists'   => 'El extra seleccionado no pertenece a este platillo o no es válido.',
        ];
    }
}
