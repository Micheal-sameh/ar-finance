<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Verifies a Cloudflare Turnstile token server-side against the siteverify
 * endpoint. The token itself (the "cf-turnstile-response" field) is opaque
 * client-side proof and must never be trusted without this round-trip.
 */
class Turnstile implements ValidationRule
{
    public function __construct(protected ?string $remoteIp = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.turnstile.secret_key');

        if (! $secret) {
            Log::warning('Turnstile secret key is not configured.');

            // Fail closed in production so a missing key can't silently
            // disable the bot check; local/testing environments skip it.
            if (app()->isProduction()) {
                $fail('Please complete the captcha verification.');
            }

            return;
        }

        if (! is_string($value) || $value === '') {
            $fail('Please complete the captcha verification.');

            return;
        }

        $response = Http::asForm()->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
            'secret' => $secret,
            'response' => $value,
            'remoteip' => $this->remoteIp,
        ]);

        if (! $response->successful() || $response->json('success') !== true) {
            $fail('Please complete the captcha verification.');
        }
    }
}
