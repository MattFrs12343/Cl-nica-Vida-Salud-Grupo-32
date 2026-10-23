<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;
use App\Services\KioskTokenService;

final class KioskMiddleware implements MiddlewareInterface
{
    public function __construct(private ?KioskTokenService $tokens = null)
    {
        $this->tokens ??= new KioskTokenService();
    }

    public function handle(Request $request, callable $next): Response
    {
        $token = $request->header('x-kiosk-token');

        if (!is_string($token) || trim($token) === '') {
            return json_error('UNAUTHENTICATED', 'Token de kiosco requerido.', [], 401);
        }

        $payload = $this->tokens->validate($token);

        if ($payload === null) {
            return json_error('UNAUTHENTICATED', 'Token de kiosco inválido o expirado.', [], 401);
        }

        return $next($request->withAttribute('kiosk', $payload));
    }
}
