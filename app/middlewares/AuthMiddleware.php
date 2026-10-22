<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (auth() !== null) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return json_error('UNAUTHENTICATED', 'No autenticado.', [], 401);
        }

        flash('error', 'Inicia sesión para continuar.');

        return redirect('/login');
    }
}
