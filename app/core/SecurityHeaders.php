<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Cabeceras de seguridad comunes a todas las respuestas.
 *
 * Se aplican en el Router (respuestas normales) y en ErrorHandler (errores
 * no controlados). No se agrega una CSP estricta porque las vistas actuales
 * usan scripts y estilos inline; esa decisión queda para una iteración con
 * nonces.
 */
final class SecurityHeaders
{
    /** @return array<string, string> */
    public static function values(string $path = ''): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'X-Permitted-Cross-Domain-Policies' => 'none',
        ];

        if (self::isHttps()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        if (str_starts_with($path, '/api/')) {
            $headers['Cache-Control'] = 'no-store';
        }

        return $headers;
    }

    public static function apply(Response $response, string $path = ''): Response
    {
        $actuales = $response->headers();

        foreach (self::values($path) as $name => $value) {
            if (!array_key_exists($name, $actuales)) {
                $response = $response->withHeader($name, $value);
            }
        }

        return $response;
    }

    public static function send(string $path = ''): void
    {
        if (headers_sent()) {
            return;
        }

        foreach (self::values($path) as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }

    private static function isHttps(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));

        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        $forwarded = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));

        return $forwarded === 'https';
    }
}
