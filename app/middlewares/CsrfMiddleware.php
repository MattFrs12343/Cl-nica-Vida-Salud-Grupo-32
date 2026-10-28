<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    /**
     * El modo `same-origin` permite peticiones JSON del mismo origen sin
     * token. Se usa únicamente en la reserva de la landing, cuyo JS ya existe
     * y no envía el header; el resto de mutaciones exige token.
     */
    public function __construct(private string $mode = 'token')
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->header('x-csrf-token') ?? $request->input('_token');

            if (!is_string($token) || !Session::verifyCsrf($token)) {
                if ($this->mode === 'same-origin' && $this->isSameOrigin($request)) {
                    return $next($request);
                }

                ErrorHandler::log('CSRF token inválido', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                throw new HttpException(
                    403,
                    'Token CSRF inválido o expirado. Recarga la página e intenta de nuevo.',
                    [],
                    'CSRF_TOKEN_MISMATCH'
                );
            }
        }

        return $next($request);
    }

    private function isSameOrigin(Request $request): bool
    {
        $source = $request->header('origin') ?? $request->header('referer');

        if (!is_string($source) || trim($source) === '') {
            return false;
        }

        $partes = parse_url(trim($source));
        $app = parse_url((string) config('app.url', ''));

        if (!is_array($partes) || !is_array($app)
            || !isset($partes['scheme'], $partes['host'], $app['scheme'], $app['host'])) {
            return false;
        }

        $puertoOrigen = (int) ($partes['port'] ?? ($partes['scheme'] === 'https' ? 443 : 80));
        $puertoApp = (int) ($app['port'] ?? ($app['scheme'] === 'https' ? 443 : 80));

        return strtolower((string) $partes['scheme']) === strtolower((string) $app['scheme'])
            && strtolower((string) $partes['host']) === strtolower((string) $app['host'])
            && $puertoOrigen === $puertoApp;
    }
}
