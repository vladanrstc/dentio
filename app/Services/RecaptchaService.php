<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class RecaptchaService
{
    public function verify(?string $token): void
    {
        if (! (bool) config('services.recaptcha.enabled')) {
            return;
        }

        if (! is_string($token) || trim($token) === '') {
            $this->fail();
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret' => config('services.recaptcha.secret_key'),
            'response' => $token,
        ]);

        $payload = (array) $response->json();
        $score = isset($payload['score']) ? (float) $payload['score'] : null;

        if (! $response->ok()
            || ($payload['success'] ?? false) !== true
            || ($score !== null && $score < (float) config('services.recaptcha.min_score'))) {
            $this->fail();
        }
    }

    private function fail(): never
    {
        throw ValidationException::withMessages([
            'recaptcha_token' => [__('errors.recaptcha_failed')],
        ]);
    }
}
