<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLandingContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Sanitización Previa (prepareForValidation):
     * ACCIÓN OBLIGATORIA: Aplicar strip_tags() a los textos y a los enlaces de redes sociales.
     * Limpieza de WhatsApp/Teléfono: Si el frontend falló en limpiar los números, el backend DEBE hacerlo.
     */
    protected function prepareForValidation(): void
    {
        $rawSection = $this->input('contacto_seccion')
            ?? $this->input('contactoSeccion')
            ?? null;

        $rawRedes = is_array($rawSection) && isset($rawSection['redesSociales']) && is_array($rawSection['redesSociales'])
            ? $rawSection['redesSociales']
            : ($this->input('redesSociales') ?? $this->input('redes_sociales') ?? []);

        $sanitizeText = function ($value) {
            if (!is_string($value)) {
                return $value;
            }
            return trim(strip_tags(preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $value)));
        };

        // 1. Extraer o normalizar title
        $rawTitle = $this->input('title')
            ?? $this->input('titulo')
            ?? (is_array($rawSection) ? ($rawSection['title'] ?? $rawSection['titulo'] ?? null) : null);

        // 2. Extraer o normalizar subtitle
        $rawSubtitle = $this->input('subtitle')
            ?? $this->input('labelSuperior')
            ?? $this->input('label_superior')
            ?? $this->input('subtitulo')
            ?? (is_array($rawSection) ? ($rawSection['subtitle'] ?? $rawSection['labelSuperior'] ?? $rawSection['subtitulo'] ?? null) : null);

        // 3. Extraer o normalizar events_text
        $rawEventsText = $this->input('events_text')
            ?? $this->input('textoEventos')
            ?? $this->input('texto_eventos')
            ?? (is_array($rawSection) ? ($rawSection['events_text'] ?? $rawSection['textoEventos'] ?? null) : null);

        // 4. Extraer o normalizar google_maps_url
        $rawMapsUrl = $this->input('google_maps_url')
            ?? $this->input('mapsLink')
            ?? $this->input('maps_link')
            ?? (is_array($rawSection) ? ($rawSection['google_maps_url'] ?? $rawSection['mapsLink'] ?? null) : null);

        // 5. Extraer o normalizar public_phone
        $rawPhone = $this->input('public_phone')
            ?? $this->input('telefono')
            ?? $this->input('phone')
            ?? $this->input('contact_phone')
            ?? $this->input('telefono_publico')
            ?? (is_array($rawSection) ? ($rawSection['public_phone'] ?? $rawSection['telefono'] ?? $rawSection['phone'] ?? null) : null);

        // 6. Extraer o normalizar whatsapp_number
        $rawWhatsapp = $this->input('whatsapp_number')
            ?? $this->input('whatsapp')
            ?? $this->input('numero_whatsapp')
            ?? $this->input('numeroWhatsapp')
            ?? $this->input('contact_whatsapp')
            ?? (is_array($rawSection) ? ($rawSection['whatsapp_number'] ?? $rawSection['whatsapp'] ?? $rawSection['numeroWhatsapp'] ?? null) : null);

        // 7. Extraer o normalizar contact_email
        $rawEmail = $this->input('contact_email')
            ?? $this->input('email')
            ?? $this->input('correo')
            ?? $this->input('correo_contacto')
            ?? (is_array($rawSection) ? ($rawSection['contact_email'] ?? $rawSection['email'] ?? $rawSection['correo'] ?? null) : null);

        // 8. Redes Sociales
        $rawInstagram = $this->input('instagram')
            ?? $this->input('social_instagram')
            ?? ($rawRedes['instagram'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['instagram'] ?? null) : null);

        $rawFacebook = $this->input('facebook')
            ?? $this->input('social_facebook')
            ?? ($rawRedes['facebook'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['facebook'] ?? null) : null);

        $rawTiktok = $this->input('tiktok')
            ?? $this->input('social_tiktok')
            ?? ($rawRedes['tiktok'] ?? null)
            ?? (is_array($rawSection) ? ($rawSection['tiktok'] ?? null) : null);

        $patches = [];

        if ($rawTitle !== null) {
            $patches['title'] = $sanitizeText($rawTitle);
            $patches['titulo'] = $patches['title'];
        }

        if ($rawSubtitle !== null) {
            $patches['subtitle'] = $sanitizeText($rawSubtitle);
            $patches['labelSuperior'] = $patches['subtitle'];
        }

        if ($rawEventsText !== null) {
            $patches['events_text'] = $sanitizeText($rawEventsText);
            $patches['textoEventos'] = $patches['events_text'];
        }

        if ($rawMapsUrl !== null) {
            $patches['google_maps_url'] = $sanitizeText($rawMapsUrl);
            $patches['mapsLink'] = $patches['google_maps_url'];
        }

        if ($rawInstagram !== null) {
            $patches['instagram'] = $sanitizeText($rawInstagram);
        }

        if ($rawFacebook !== null) {
            $patches['facebook'] = $sanitizeText($rawFacebook);
        }

        if ($rawTiktok !== null) {
            $patches['tiktok'] = $sanitizeText($rawTiktok);
        }

        // Limpieza de WhatsApp / Teléfono / Email según instrucción quirúrgica:
        $patches['whatsapp_number'] = preg_replace('/[^0-9]/', '', (string)($rawWhatsapp ?? $this->whatsapp_number ?? ''));
        $patches['public_phone']    = preg_replace('/[^0-9]/', '', (string)($rawPhone ?? $this->public_phone ?? ''));
        $patches['contact_email']   = strtolower(trim((string)($rawEmail ?? $this->contact_email ?? '')));
        $patches['telefono']        = $patches['public_phone'];
        $patches['whatsapp']        = $patches['whatsapp_number'];
        $patches['email']           = $patches['contact_email'];

        $this->merge($patches);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title'           => 'required|string|max:100',
            'subtitle'        => 'required|string|max:100',
            'events_text'     => 'nullable|string|max:300',
            'google_maps_url' => 'required|url|max:500',
            'public_phone'    => 'required|string|regex:/^[0-9]{10,15}$/',
            'whatsapp_number' => 'required|string|regex:/^[0-9]{10,15}$/',
            'contact_email'   => 'required|email:rfc,dns|max:150',
            'instagram'       => 'nullable|string|max:255',
            'facebook'        => 'nullable|string|max:255',
            'tiktok'          => 'nullable|string|max:255',
        ];
    }

    /**
     * Mensajes de error personalizados en español.
     */
    public function messages(): array
    {
        return [
            'title.required'             => 'El título de la sección de contacto es obligatorio.',
            'title.string'               => 'El título debe ser una cadena de texto.',
            'title.max'                  => 'El título no puede exceder los 100 caracteres.',

            'subtitle.required'          => 'El subtítulo de la sección de contacto es obligatorio.',
            'subtitle.string'            => 'El subtítulo debe ser una cadena de texto.',
            'subtitle.max'               => 'El subtítulo no puede exceder los 100 caracteres.',

            'events_text.string'         => 'El texto de eventos debe ser una cadena de texto.',
            'events_text.max'            => 'El texto de eventos no puede exceder los 300 caracteres.',

            'google_maps_url.required'   => 'La URL de Google Maps es obligatoria.',
            'google_maps_url.url'        => 'Debe ingresar una URL válida para Google Maps.',
            'google_maps_url.max'        => 'La URL de Google Maps no puede superar los 500 caracteres.',

            'public_phone.required'      => 'El teléfono público es obligatorio.',
            'public_phone.string'        => 'El teléfono debe ser una cadena de texto.',
            'public_phone.regex'         => 'El teléfono debe contener entre 10 y 15 dígitos numéricos.',

            'whatsapp_number.required'   => 'El número de WhatsApp es obligatorio.',
            'whatsapp_number.string'     => 'El número de WhatsApp debe ser una cadena de texto.',
            'whatsapp_number.regex'      => 'El número de WhatsApp debe contener entre 10 y 15 dígitos numéricos.',

            'contact_email.required'     => 'El correo electrónico de contacto es obligatorio.',
            'contact_email.email'        => 'El correo electrónico no es válido o su dominio no puede recibir mensajes.',
            'contact_email.max'          => 'El correo electrónico no puede exceder los 150 caracteres.',

            'instagram.string'           => 'El identificador o enlace de Instagram debe ser texto.',
            'instagram.max'              => 'El enlace o identificador de Instagram no puede exceder los 255 caracteres.',

            'facebook.string'            => 'El identificador o enlace de Facebook debe ser texto.',
            'facebook.max'               => 'El enlace o identificador de Facebook no puede exceder los 255 caracteres.',

            'tiktok.string'              => 'El identificador o enlace de TikTok debe ser texto.',
            'tiktok.max'                 => 'El enlace o identificador de TikTok no puede exceder los 255 caracteres.',
        ];
    }

    /**
     * Nombres de atributos personalizados para mensajes de validación.
     */
    public function attributes(): array
    {
        return [
            'title'           => 'título',
            'subtitle'        => 'subtítulo',
            'events_text'     => 'texto de eventos',
            'google_maps_url' => 'URL de Google Maps',
            'public_phone'    => 'teléfono público',
            'whatsapp_number' => 'número de WhatsApp',
            'contact_email'   => 'correo de contacto',
            'instagram'       => 'Instagram',
            'facebook'        => 'Facebook',
            'tiktok'          => 'TikTok',
        ];
    }
}
