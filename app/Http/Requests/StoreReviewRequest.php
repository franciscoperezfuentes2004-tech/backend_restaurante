<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreReviewRequest extends FormRequest
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
        // 0. Detección anti-bots por señuelo Honeypot
        if ($this->filled('_hp_website') || $this->filled('website_url_hp') || $this->filled('hp_email')) {
            abort(response()->json([
                'message' => 'Acceso denegado. Solicitud automatizada detectada por Honeypot.',
                'errors' => [
                    'bot' => ['Detección de bot por Honeypot.']
                ]
            ], 422));
        }

        $patches = [];

        // Mapeo bidireccional de alias (Español <-> Inglés)
        if ($this->has('customer_name') && !$this->has('nombre')) {
            $patches['nombre'] = $this->customer_name;
        }

        if ($this->has('comment') && !$this->has('comentario')) {
            $patches['comentario'] = $this->comment;
        }

        // Sanitización estricta de XSS y etiquetas HTML en comentario
        $rawComentario = $patches['comentario'] ?? $this->comentario ?? null;
        if (is_string($rawComentario)) {
            $cleaned = trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawComentario)));
            $patches['comentario'] = $cleaned;
        }

        // Sanitización de espacios en nombre
        $rawNombre = $patches['nombre'] ?? $this->nombre ?? null;
        if (is_string($rawNombre)) {
            $patches['nombre'] = trim($rawNombre);
        }

        // Mapeo de token Cloudflare Turnstile
        if ($this->has('cf-turnstile-response') && !$this->has('turnstile_token')) {
            $patches['turnstile_token'] = $this->input('cf-turnstile-response');
        }

        // Mapeo de alias para teléfono y correo
        if ($this->has('phone') && !$this->has('telefono')) {
            $patches['telefono'] = $this->phone;
        } elseif ($this->has('customer_phone') && !$this->has('telefono')) {
            $patches['telefono'] = $this->customer_phone;
        }

        if ($this->has('email') && !$this->has('correo')) {
            $patches['correo'] = $this->email;
        } elseif ($this->has('customer_email') && !$this->has('correo')) {
            $patches['correo'] = $this->customer_email;
        }

        if (isset($patches['telefono']) || $this->has('telefono')) {
            $rawTelefono = $patches['telefono'] ?? $this->telefono;
            if (is_string($rawTelefono)) {
                $patches['telefono'] = trim($rawTelefono);
            }
        }

        if (isset($patches['correo']) || $this->has('correo')) {
            $rawCorreo = $patches['correo'] ?? $this->correo;
            if (is_string($rawCorreo)) {
                $patches['correo'] = trim(strtolower($rawCorreo));
            }
        }

        // Mapeo de archivos de fotos (fotos <-> images)
        if ($this->hasFile('images') && !$this->hasFile('fotos')) {
            $this->files->set('fotos', $this->file('images'));
        }
        if ($this->hasFile('fotos') && !is_array($this->file('fotos'))) {
            $this->files->set('fotos', [$this->file('fotos')]);
        }

        if (!empty($patches)) {
            $this->merge($patches);
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
            'nombre'     => 'required|string|max:100|regex:/^[a-zA-Z\s]+$/',
            'correo'     => 'required|email|max:255',
            'telefono'   => [
                'required',
                'string',
                'min:10',
                'max:15',
                'regex:/^[0-9+]+$/', // Rechaza la petición si detecta cualquier letra ("eee")
            ],
            'rating'     => 'required|integer|min:1|max:5',
            // Ampliación a 1000 caracteres con protección XSS (strip_tags)
            'comentario' => 'required|string|max:1000|strip_tags',
            // max:10240 significa 10MB de tolerancia máxima de entrada
            'fotos'      => 'nullable|array|max:3',
            'fotos.*'    => 'image|mimes:jpeg,png,webp|max:10240',
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
            'nombre.required'     => 'El nombre es obligatorio.',
            'nombre.string'       => 'El nombre debe ser una cadena de texto.',
            'nombre.max'          => 'El nombre no puede superar los 100 caracteres.',
            'nombre.regex'        => 'El nombre solo puede contener letras y espacios, sin símbolos ni URLs.',
            'telefono.required'   => 'El teléfono es obligatorio.',
            'telefono.string'     => 'El teléfono debe ser una cadena de texto.',
            'telefono.min'        => 'El teléfono debe tener al menos 10 caracteres.',
            'telefono.max'        => 'El teléfono no puede superar los 15 caracteres.',
            'telefono.regex'      => 'El teléfono solo puede contener números y el signo +.',
            'correo.required'     => 'El correo electrónico es obligatorio.',
            'correo.email'        => 'El correo electrónico debe tener un formato válido.',
            'correo.max'          => 'El correo electrónico no puede superar los 255 caracteres.',
            'rating.required'     => 'La calificación es obligatoria.',
            'rating.integer'      => 'La calificación debe ser un número entero.',
            'rating.min'          => 'La calificación mínima permitida es de 1 estrella.',
            'rating.max'          => 'La calificación máxima permitida es de 5 estrellas.',
            'comentario.required' => 'El comentario es obligatorio.',
            'comentario.string'   => 'El comentario debe ser una cadena de texto.',
            'comentario.max'      => 'El comentario no puede superar los 1000 caracteres.',
            'fotos.array'         => 'Las fotos deben ser enviadas como un arreglo.',
            'fotos.max'           => 'No puedes subir más de 3 fotos.',
            'fotos.*.required'    => 'Cada fotografía es obligatoria.',
            'fotos.*.image'       => 'El archivo debe ser una imagen real.',
            'fotos.*.mimes'       => 'Solo se permiten imágenes en formato JPEG, PNG o WebP.',
            'fotos.*.max'         => 'Cada fotografía no debe superar los 5MB de tamaño.',
        ];
    }
}
