<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Services\CloudflareTurnstileService;

class TurnstileRule implements ValidationRule
{
    protected ?string $ip;
    protected CloudflareTurnstileService $turnstileService;

    public function __construct(?string $ip = null)
    {
        $this->ip = $ip;
        $this->turnstileService = app(CloudflareTurnstileService::class);
    }

    /**
     * Valida el token contra Cloudflare Turnstile.
     *
     * @param  string  $attribute
     * @param  mixed   $value
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (!is_string($value) || empty(trim($value))) {
            $fail('La verificación de seguridad es obligatoria.');
            return;
        }

        if (!$this->turnstileService->verify(trim($value), $this->ip)) {
            $fail('La verificación de seguridad de Cloudflare Turnstile ha fallado. Por favor, inténtalo de nuevo.');
        }
    }
}