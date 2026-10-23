<?php

declare(strict_types=1);

namespace App\Services;

use JsonException;
use RuntimeException;

/**
 * Emite y valida tokens HMAC de sesión de kiosco.
 *
 * El token es stateless y de vida corta: payload JSON en base64url + firma
 * HMAC-SHA256 en base64url. No contiene datos de paciente ni de usuario.
 */
final class KioskTokenService
{
    private const VERSION = 1;
    private const AUDIENCE = 'kiosco';
    private const CLOCK_SKEW = 60;
    private const MAX_TOKEN_LENGTH = 4096;

    private string $secret;
    private string $deviceKey;
    private int $ttl;

    public function __construct(?string $secret = null, ?string $deviceKey = null, ?int $ttl = null)
    {
        $config = (array) config('app.kiosk', []);

        $this->secret = trim($secret ?? (string) ($config['hmac_secret'] ?? ''));
        $this->deviceKey = trim($deviceKey ?? (string) ($config['device_key'] ?? ''));
        $ttl = $ttl ?? (int) ($config['token_ttl'] ?? 600);
        $this->ttl = $ttl > 0 ? $ttl : 600;

        if ($this->secret === '') {
            throw new RuntimeException('KIOSK_HMAC_SECRET no está configurado.');
        }
    }

    public function deviceKeyValid(string $deviceKey): bool
    {
        return $this->deviceKey !== ''
            && $deviceKey !== ''
            && hash_equals($this->deviceKey, $deviceKey);
    }

    public function ttl(): int
    {
        return $this->ttl;
    }

    /**
     * @return array{token: string, expires_in: int}
     */
    public function issue(?int $now = null): array
    {
        $now ??= time();
        $payload = [
            'v' => self::VERSION,
            'aud' => self::AUDIENCE,
            'iat' => $now,
            'exp' => $now + $this->ttl,
            'jti' => bin2hex(random_bytes(16)),
        ];

        try {
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        } catch (JsonException $exception) {
            throw new RuntimeException('No se pudo construir el token de kiosco.', 0, $exception);
        }

        $encoded = self::base64UrlEncode($json);
        $signature = self::base64UrlEncode(hash_hmac('sha256', $encoded, $this->secret, true));

        return [
            'token' => $encoded . '.' . $signature,
            'expires_in' => $this->ttl,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function validate(string $token, ?int $now = null): ?array
    {
        $now ??= time();
        $token = trim($token);

        if ($token === '' || strlen($token) > self::MAX_TOKEN_LENGTH) {
            return null;
        }

        $parts = explode('.', $token);

        if (count($parts) !== 2) {
            return null;
        }

        [$encoded, $signature] = $parts;

        if ($encoded === '' || strlen($signature) !== 43) {
            return null;
        }

        $providedSignature = self::base64UrlDecode($signature);

        if ($providedSignature === null) {
            return null;
        }

        $expectedSignature = hash_hmac('sha256', $encoded, $this->secret, true);

        if (!hash_equals($expectedSignature, $providedSignature)) {
            return null;
        }

        $rawPayload = self::base64UrlDecode($encoded);

        if ($rawPayload === null) {
            return null;
        }

        try {
            $payload = json_decode($rawPayload, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (!is_array($payload)) {
            return null;
        }

        $version = $payload['v'] ?? null;
        $audience = $payload['aud'] ?? null;
        $issuedAt = $payload['iat'] ?? null;
        $expiresAt = $payload['exp'] ?? null;
        $tokenId = $payload['jti'] ?? null;

        if ($version !== self::VERSION || $audience !== self::AUDIENCE) {
            return null;
        }

        if (!is_int($issuedAt) || !is_int($expiresAt) || !is_string($tokenId)) {
            return null;
        }

        if (preg_match('/^[a-f0-9]{32}$/', $tokenId) !== 1) {
            return null;
        }

        if ($expiresAt <= $issuedAt || $expiresAt <= $now) {
            return null;
        }

        if ($issuedAt > $now + self::CLOCK_SKEW) {
            return null;
        }

        return $payload;
    }

    private static function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }

        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(
            strtr($value, '-_', '+/') . str_repeat('=', $padding),
            true
        );

        return $decoded === false ? null : $decoded;
    }
}
