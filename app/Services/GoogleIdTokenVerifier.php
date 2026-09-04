<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class GoogleIdTokenVerifier
{
    public function verify(string $credential): array
    {
        try {
            $claims = (array) JWT::decode($credential, JWK::parseKeySet($this->keys(), 'RS256'));
        } catch (\UnexpectedValueException $exception) {
            // Google rotates signing keys. Refresh once before rejecting the token.
            Cache::forget('google.signing_keys');
            try {
                $claims = (array) JWT::decode($credential, JWK::parseKeySet($this->keys(), 'RS256'));
            } catch (\UnexpectedValueException $exception) {
                throw ValidationException::withMessages(['google' => '[google_token_invalid] Google could not verify this sign-in. Reload and try again.']);
            }
        }
        if (($claims['aud'] ?? null) !== config('services.google.client_id')
            || ! in_array($claims['iss'] ?? null, ['accounts.google.com', 'https://accounts.google.com'], true)
            || ! is_numeric($claims['exp'] ?? null) || $claims['exp'] <= time()
            || ($claims['email_verified'] ?? false) !== true
            || ! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw ValidationException::withMessages(['google' => '[google_token_invalid] Google could not verify this account. Choose a verified Google account and try again.']);
        }
        return $claims;
    }

    private function keys(): array
    {
        if ($keys = Cache::get('google.signing_keys')) {
            return $keys;
        }
        $response = Http::timeout(10)->get('https://www.googleapis.com/oauth2/v3/certs')->throw();
        $keys = $response->json();
        if (! is_array($keys) || empty($keys['keys'])) {
            throw new \RuntimeException('Google signing keys are unavailable.');
        }
        preg_match('/max-age=(\d+)/', $response->header('Cache-Control'), $match);
        Cache::put('google.signing_keys', $keys, min(3600, max(1, (int) ($match[1] ?? 300))));
        return $keys;
    }
}
