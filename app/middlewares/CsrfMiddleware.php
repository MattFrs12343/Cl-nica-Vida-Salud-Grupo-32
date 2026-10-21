<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\HttpException;
use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

final class CsrfMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $token = $request->header('x-csrf-token') ?? $request->input('_token');

            if (!is_string($token) || !Session::verifyCsrf($token)) {
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
}
