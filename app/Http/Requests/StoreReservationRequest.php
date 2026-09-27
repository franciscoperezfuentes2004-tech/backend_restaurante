<?php

namespace App\Http\Requests;

use App\Models\Mesa;
use App\Models\RestaurantSetting;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreReservationRequest extends FormRequest
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

        // Mapeo de token Cloudflare Turnstile
        if ($this->has('cf-turnstile-response') && !$this->has('turnstile_token')) {
            $patches['turnstile_token'] = $this->input('cf-turnstile-response');
        }

        // 1. Mapeo bidireccional de alias (Español <-> Inglés)
        if ($this->has('customer_name') && !$this->has('nombre')) {
            $patches['nombre'] = $this->customer_name;
        } elseif ($this->has('client_name') && !$this->has('nombre')) {
            $patches['nombre'] = $this->client_name;
        }

        if ($this->has('customer_phone') && !$this->has('telefono')) {
            $patches['telefono'] = $this->customer_phone;
        } elseif ($this->has('phone') && !$this->has('telefono')) {
            $patches['telefono'] = $this->phone;
        }

        if ($this->has('customer_email') && !$this->has('email')) {
            $patches['email'] = $this->customer_email;
        }

        if ($this->has('reservation_date') && !$this->has('fecha')) {
            $patches['fecha'] = $this->reservation_date;
        } elseif ($this->has('date') && !$this->has('fecha')) {
            $patches['fecha'] = $this->date;
        }

        if ($this->has('reservation_time') && !$this->has('hora')) {
            $patches['hora'] = $this->reservation_time;
        } elseif ($this->has('time') && !$this->has('hora')) {
            $patches['hora'] = $this->time;
        }

        if ($this->has('guests_count') && !$this->has('personas')) {
            $patches['personas'] = $this->guests_count;
        } elseif ($this->has('people_count') && !$this->has('personas')) {
            $patches['personas'] = $this->people_count;
        }

        if ($this->has('special_requests') && !$this->has('nota_especial')) {
            $patches['nota_especial'] = $this->special_requests;
        } elseif ($this->has('notes') && !$this->has('nota_especial')) {
            $patches['nota_especial'] = $this->notes;
        }

        if ($this->has('zona') && !$this->has('zona_preferida')) {
            $patches['zona_preferida'] = $this->zona;
        }

        if ($this->has('ocasion') && !$this->has('ocasion_especial')) {
            $patches['ocasion_especial'] = $this->ocasion;
        }

        // 2. Normalizar formato de hora si viene con segundos (ej: 20:30:00 -> 20:30)
        $rawHora = $patches['hora'] ?? $this->hora ?? null;
        if (is_string($rawHora) && strlen(trim($rawHora)) >= 5) {
            $patches['hora'] = substr(trim($rawHora), 0, 5);
        }

        // 3. Sanitización activa XSS en nota_especial
        $rawNota = $patches['nota_especial'] ?? $this->nota_especial ?? null;
        if (is_string($rawNota)) {
            $cleanedNota = trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $rawNota)));
            $patches['nota_especial'] = $cleanedNota;
            $patches['notes']         = $cleanedNota;
            $patches['special_requests'] = $cleanedNota;
        }

        // 4. Mapeo de compatibilidad inversa para controladores y modelos
        if (isset($patches['nombre']) || $this->has('nombre')) {
            $n = $patches['nombre'] ?? $this->nombre;
            $patches['client_name'] = $n;
            $patches['customer_name'] = $n;
        }
        if (isset($patches['telefono']) || $this->has('telefono')) {
            $t = $patches['telefono'] ?? $this->telefono;
            $patches['phone'] = $t;
            $patches['customer_phone'] = $t;
        }
        if (isset($patches['fecha']) || $this->has('fecha')) {
            $f = $patches['fecha'] ?? $this->fecha;
            $patches['date'] = $f;
            $patches['reservation_date'] = $f;
        }
        if (isset($patches['hora']) || $this->has('hora')) {
            $h = $patches['hora'] ?? $this->hora;
            $patches['time'] = $h;
            $patches['reservation_time'] = $h;
        }
        if (isset($patches['personas']) || $this->has('personas')) {
            $p = $patches['personas'] ?? $this->personas;
            $patches['people_count'] = $p;
            $patches['guests_count'] = $p;
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
        $rules = [
            'nombre'           => 'required|string|max:100|regex:/^[a-zA-ZÀ-ÿ\s]+$/',
            'telefono'         => 'required|digits:10',
            'email'            => 'nullable|email|max:150',
            'fecha'            => [
                'required',
                'date',
                'after_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail) {
                    try {
                        $dayOfWeek = Carbon::parse($value)->dayOfWeek;
                    } catch (\Throwable $e) {
                        $fail('La fecha de reservación no es válida.');
                        return;
                    }

                    $activeDays = RestaurantSetting::getActiveOperatingDays();

                    if (!in_array($dayOfWeek, $activeDays, true)) {
                        $fail('El restaurante se encuentra cerrado en el día seleccionado. Por favor, selecciona un día de servicio.');
                    }
                },
            ],
            'hora'             => [
                'required',
                'date_format:H:i',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $horaInput = substr(trim((string) $value), 0, 5);
                    $fechaInput = $this->input('fecha') ?? $this->input('date') ?? $this->input('reservation_date');

                    if (!$fechaInput) {
                        return;
                    }

                    try {
                        $parsedDate = Carbon::parse($fechaInput);
                    } catch (\Throwable $e) {
                        return;
                    }

                    // 1. Bloqueo de hora pasada: Si fecha es igual a today, la hora debe ser estrictamente mayor a now()->format('H:i')
                    if ($parsedDate->isToday()) {
                        $currentTime = now()->format('H:i');
                        if ($horaInput <= $currentTime) {
                            $fail('La hora seleccionada ya ha pasado. Por favor, selecciona un horario posterior a la hora actual.');
                            return;
                        }
                    }

                    // 2. Bloqueo de fuera de horario: Obtener rango de apertura y cierre para el día de la semana
                    $dayOfWeek = $parsedDate->dayOfWeek;
                    $operatingHours = RestaurantSetting::getOperatingHours();
                    $dayKey = (string) $dayOfWeek;

                    if (!isset($operatingHours[$dayKey])) {
                        $fail('El restaurante se encuentra cerrado en el día seleccionado. Por favor, selecciona un día de servicio.');
                        return;
                    }

                    $open = $operatingHours[$dayKey]['open'];
                    $close = $operatingHours[$dayKey]['close'];

                    $isWithin = ($close >= $open)
                        ? ($horaInput >= $open && $horaInput <= $close)
                        : ($horaInput >= $open || $horaInput <= $close);

                    if (!$isWithin) {
                        $fail("La hora de reservación ({$horaInput}) está fuera del horario de atención para este día (Horario permitido: {$open} a {$close}).");
                        return;
                    }
                },
            ],
            'personas'         => 'required|integer|min:1|max:20',
            'nota_especial'    => 'nullable|string|max:500|strip_tags',
            'zona_preferida'   => 'nullable|string|max:50',
            'ocasion_especial' => 'nullable|string|max:50',
            'estado'           => 'nullable|string|max:20',

            // Campos opcionales para asignación y compatibilidad administrativa
            'area_id'          => 'sometimes|nullable|integer|exists:areas,id',
            'table_id'         => 'sometimes|nullable|integer|exists:tables,id',
            'status'           => 'sometimes|nullable|string|max:20',
        ];

        // Protección Cloudflare Turnstile para solicitudes públicas (se omite en entorno local)
        $isPublic = !$this->user() && !$this->is('api/admin/*');
        if ($isPublic && !app()->environment('local') && config('services.turnstile.required', true)) {
            $rules['turnstile_token'] = ['required', 'string', new \App\Rules\TurnstileRule($this->ip())];
        }

        return $rules;
    }

    /**
     * Custom messages for validation errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nombre.required'           => 'El nombre es obligatorio.',
            'nombre.string'             => 'El nombre debe ser una cadena de texto.',
            'nombre.max'                => 'El nombre no puede superar los 100 caracteres.',
            'nombre.regex'              => 'El nombre solo puede contener letras y espacios.',

            'telefono.required'         => 'El teléfono es obligatorio.',
            'telefono.digits'           => 'El teléfono debe contener exactamente 10 dígitos numéricos.',

            'email.email'               => 'El correo electrónico no es válido.',
            'email.max'                 => 'El correo electrónico no debe superar los 150 caracteres.',

            'fecha.required'            => 'La fecha de reservación es obligatoria.',
            'fecha.date'                => 'La fecha de reservación no es válida.',
            'fecha.after_or_equal'      => 'La fecha de reservación no puede ser en el pasado.',

            'hora.required'             => 'La hora de reservación es obligatoria.',
            'hora.date_format'          => 'La hora de reservación debe tener el formato H:i.',

            'personas.required'         => 'El número de personas es obligatorio.',
            'personas.integer'          => 'El número de personas debe ser un número entero.',
            'personas.min'              => 'La reservación debe ser para al menos 1 persona.',
            'personas.max'              => 'La reservación no puede exceder las 20 personas.',

            'nota_especial.string'      => 'La nota especial debe ser una cadena de texto.',
            'nota_especial.max'         => 'La nota especial no puede superar los 500 caracteres.',

            'zona_preferida.string'     => 'La zona preferida debe ser texto.',
            'zona_preferida.max'        => 'La zona preferida no puede superar los 50 caracteres.',

            'ocasion_especial.string'   => 'La ocasión especial debe ser texto.',
            'ocasion_especial.max'      => 'La ocasión especial no puede superar los 50 caracteres.',

            'turnstile_token.required'  => 'La verificación de seguridad de Cloudflare Turnstile es obligatoria.',
            'turnstile_token.string'    => 'El token de seguridad no es válido.',
        ];
    }
}
