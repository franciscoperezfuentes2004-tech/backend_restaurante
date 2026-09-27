<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerOrderRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización previa: strip_tags() destruye cualquier intento de inyección
     * de código HTML o JavaScript (<script>, <iframe>, etc) y trim() elimina espacios.
     */
    protected function prepareForValidation(): void
    {
        $nombre = $this->input('nombre_completo') ?? $this->input('customer_name') ?? $this->input('nombre') ?? $this->input('name') ?? $this->input('cliente');
        $telefono = $this->input('telefono') ?? $this->input('customer_phone') ?? $this->input('phone') ?? $this->input('celular');
        $calle = $this->input('calle');
        $noExterior = $this->input('no_exterior') ?? $this->input('num_ext');
        $noInterior = $this->input('no_interior') ?? $this->input('num_int');
        $codigoPostal = $this->input('codigo_postal') ?? $this->input('cp');
        $colonia = $this->input('colonia');
        $referencias = $this->input('referencias');
        $notaSolo = $this->input('nota_especial') ?? $this->input('nota') ?? $this->input('notes') ?? $this->input('notaGeneral');

        $this->merge([
            'nombre_completo' => $nombre !== null ? strip_tags(trim((string) $nombre)) : null,
            'customer_name'   => $nombre !== null ? strip_tags(trim((string) $nombre)) : null,
            'telefono'        => $telefono !== null ? strip_tags(trim((string) $telefono)) : null,
            'customer_phone'  => $telefono !== null ? strip_tags(trim((string) $telefono)) : null,
            'calle'           => $calle !== null ? strip_tags(trim((string) $calle)) : null,
            'no_exterior'     => $noExterior !== null ? strip_tags(trim((string) $noExterior)) : null,
            'num_ext'         => $noExterior !== null ? strip_tags(trim((string) $noExterior)) : null,
            'no_interior'     => !empty($noInterior) ? strip_tags(trim((string) $noInterior)) : null,
            'num_int'         => !empty($noInterior) ? strip_tags(trim((string) $noInterior)) : null,
            'codigo_postal'   => $codigoPostal !== null ? strip_tags(trim((string) $codigoPostal)) : null,
            'cp'              => $codigoPostal !== null ? strip_tags(trim((string) $codigoPostal)) : null,
            'colonia'         => $colonia !== null ? strip_tags(trim((string) $colonia)) : null,
            'referencias'     => !empty($referencias) ? strip_tags(trim((string) $referencias)) : null,
            'nota_especial'   => !empty($notaSolo) ? strip_tags(trim((string) $notaSolo)) : null,
        ]);
    }

    /**
     * Reglas de validación estrictas contra inyecciones y datos corruptos.
     */
    public function rules(): array
    {
        $modality = strtolower((string) ($this->input('order_type') ?? $this->input('modality') ?? $this->input('modalidad') ?? 'delivery'));
        $isDelivery = in_array($modality, ['delivery', 'domicilio', 'a_domicilio']);

        return [
            // Reglas estrictas: Alpha con espacios para nombres
            'nombre_completo' => 'required|string|min:3|max:100|regex:/^[\pL\s\-]+$/u',
            
            // Exactamente 10 dígitos, nada de letras
            'telefono' => 'required|digits:10',
            
            // Letras, números, espacios y caracteres básicos seguros
            'calle' => $isDelivery ? 'required|string|max:150|regex:/^[a-zA-Z0-9\s.,#-]+$/u' : 'nullable|string|max:150',
            'no_exterior' => $isDelivery ? 'required|string|max:20|regex:/^[a-zA-Z0-9\s-]+$/u' : 'nullable|string|max:20',
            'no_interior' => 'nullable|string|max:20|regex:/^[a-zA-Z0-9\s-]+$/u',
            
            // Exactamente 5 dígitos para el CP mexicano
            'codigo_postal' => $isDelivery ? 'required|digits:5' : 'nullable|digits:5',
            
            'colonia' => $isDelivery ? 'required|string|max:100|regex:/^[a-zA-Z0-9\s.,#-]+$/u' : 'nullable|string|max:100',
            'referencias' => $isDelivery ? 'required|string|max:200' : 'nullable|string|max:200',
            'nota_especial' => 'nullable|string|max:250',

            // Carrito e items
            'items' => 'required|array|min:1',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'nombre_completo.required' => 'El nombre completo es obligatorio.',
            'nombre_completo.min'      => 'El nombre debe tener al menos 3 caracteres.',
            'nombre_completo.regex'    => 'Solo letras. Mínimo 3 caracteres.',
            'telefono.required'        => 'El teléfono es obligatorio.',
            'telefono.digits'          => 'El teléfono debe tener exactamente 10 números.',
            'calle.required'           => 'La calle es obligatoria para envíos a domicilio.',
            'calle.regex'              => 'No se permiten caracteres especiales raros.',
            'no_exterior.required'     => 'El número exterior es obligatorio.',
            'no_exterior.regex'        => 'No se permiten caracteres especiales raros.',
            'no_interior.regex'        => 'No se permiten caracteres especiales raros.',
            'codigo_postal.required'   => 'El código postal es obligatorio.',
            'codigo_postal.digits'     => 'El código postal debe ser de 5 dígitos.',
            'colonia.required'         => 'La colonia es obligatoria.',
            'colonia.regex'            => 'No se permiten caracteres especiales raros.',
            'referencias.required'     => 'Las referencias de entrega son obligatorias.',
            'items.required'           => 'El carrito de compras no puede estar vacío.',
        ];
    }
}
