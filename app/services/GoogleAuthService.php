<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\HttpException;
use RuntimeException;

/**
 * Flujo OAuth 2.0 "Authorization Code" contra Google, implementado con cURL
 * directo (sin librería) para no sumar dependencias, igual que la
 * integración con la API de Brevo.
 */
final class GoogleAuthService
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const USERINFO_URL = 'https://www.googleapis.com/oauth2/v3/userinfo';

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $clientId = trim((string) config('google.client_id', ''));

        if ($clientId === '') {
            throw new RuntimeException('GOOGLE_CLIENT_ID no está configurado.');
        }

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /** @return array{email: string, email_verified: bool, name: string} */
    public function resolveProfile(string $code, string $redirectUri): array
    {
        $accessToken = $this->exchangeCode($code, $redirectUri);

        return $this->fetchProfile($accessToken);
    }

    private function exchangeCode(string $code, string $redirectUri): string
    {
        $clientId = trim((string) config('google.client_id', ''));
        $clientSecret = trim((string) config('google.client_secret', ''));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Credenciales de Google no configuradas.');
        }

        $payload = [
            'code' => $code,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code',
        ];

        [$status, $body] = $this->post(self::TOKEN_URL, $payload);

        $data = json_decode($body, true);

        if ($status < 200 || $status >= 300 || !is_array($data) || !isset($data['access_token'])) {
            throw new HttpException(
                401,
                'No se pudo validar la sesión de Google.',
                [],
                'GOOGLE_TOKEN_EXCHANGE_FAILED'
            );
        }

        return (string) $data['access_token'];
    }

    /** @return array{email: string, email_verified: bool, name: string} */
    private function fetchProfile(string $accessToken): array
    {
        $ch = curl_init(self::USERINFO_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $data = is_string($body) ? json_decode($body, true) : null;

        if ($status < 200 || $status >= 300 || !is_array($data) || !isset($data['email'])) {
            throw new HttpException(
                401,
                'No se pudo obtener el perfil de Google.',
                [],
                'GOOGLE_PROFILE_FAILED'
            );
        }

        return [
            'email' => (string) $data['email'],
            'email_verified' => filter_var($data['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'name' => (string) ($data['name'] ?? ''),
        ];
    }

    /** @param array<string, string> $payload @return array{0: int, 1: string} */
    private function post(string $url, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_HTTPHEADER => ['content-type: application/x-www-form-urlencoded'],
            CURLOPT_TIMEOUT => 10,
        ]);

        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [$status, is_string($body) ? $body : ''];
    }
}
