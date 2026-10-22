<?php

declare(strict_types=1);

namespace App\Middlewares;

use App\Core\MiddlewareInterface;
use App\Core\Request;
use App\Core\Response;

final class RoleMiddleware implements MiddlewareInterface
{
    /** @var array<int, string> */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = array_values(array_filter($roles, static fn (string $role): bool => $role !== ''));
    }

    public function handle(Request $request, callable $next): Response
    {
        $usuario = auth();

        if ($usuario === null) {
            if ($request->wantsJson()) {
                return json_error('UNAUTHENTICATED', 'No autenticado.', [], 401);
            }

            flash('error', 'Inicia sesión para continuar.');

            return redirect('/login');
        }

        if ($this->roles === [] || in_array((string) $usuario['rol'], $this->roles, true)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            return json_error('FORBIDDEN', 'No tienes permisos para esta sección.', [], 403);
        }

        flash('error', 'No tienes permisos para esta sección.');

        return redirect(role_home((string) $usuario['rol']));
    }
}
