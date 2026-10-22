<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\ErrorHandler;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);

    if ($value === false) {
        return $default;
    }

    return match ($value) {
        'null' => null,
        'true' => true,
        'false' => false,
        default => $value,
    };
}

function config(?string $key = null, mixed $default = null): mixed
{
    if ($key === null) {
        return Config::all();
    }

    return Config::get($key, $default);
}

function base_path(string $path = ''): string
{
    return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function app_path(string $path = ''): string
{
    return base_path('app' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function public_path(string $path = ''): string
{
    return base_path('public' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function storage_path(string $path = ''): string
{
    return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function database_path(string $path = ''): string
{
    return base_path('database' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
}

function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $path = ''): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    $base = rtrim((string) config('app.url', ''), '/');

    if ($base === '') {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $base = $scheme . '://' . $host;
    }

    return $base . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
        return $path;
    }

    return url('assets/' . ltrim($path, '/'));
}

/** @param array<string, mixed> $data */
function view(string $view, array $data = [], ?string $layout = null): string
{
    return View::render($view, $data, $layout);
}

function redirect(string $target, int $status = 302): Response
{
    if (!str_starts_with($target, 'http://') && !str_starts_with($target, 'https://')) {
        $target = url($target);
    }

    return Response::redirect($target, $status);
}

/** @param array<string, mixed> $meta */
function json_ok(mixed $data = [], array $meta = [], ?string $message = null, int $status = 200): Response
{
    return Response::json([
        'ok' => true,
        'data' => $data,
        'message' => $message,
        'meta' => $meta === [] ? (object) [] : $meta,
    ], $status);
}

/** @param array<string, string> $fields */
function json_error(string $code, string $message, array $fields = [], int $status = 422): Response
{
    return Response::json([
        'ok' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'fields' => $fields === [] ? (object) [] : $fields,
        ],
    ], $status);
}

/** @return array<string, mixed>|null */
function auth(): ?array
{
    $user = Session::get('usuario');

    return is_array($user) ? $user : null;
}

function has_role(string ...$roles): bool
{
    $user = auth();

    return $user !== null && in_array((string) ($user['rol'] ?? ''), $roles, true);
}

function role_home(?string $role = null): string
{
    $role ??= (string) (auth()['rol'] ?? '');

    return match ($role) {
        'super_admin', 'admin' => '/admin',
        'recepcionista', 'recepcionista_medico' => '/recepcion',
        'medico' => '/medico',
        'paciente' => '/portal',
        default => '/login',
    };
}

function csrf_token(): string
{
    return Session::csrfToken();
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    return is_string($token) && Session::verifyCsrf($token);
}

function flash(string $type, string $message): void
{
    Session::flash($type, $message);
}

/** @return array<int, array{type: string, message: string}> */
function flashes(): array
{
    return Session::flashes();
}

function old(string $key, mixed $default = null): mixed
{
    return Session::old($key, $default);
}

function abort(int $status, string $message = ''): never
{
    throw new HttpException($status, $message);
}

function logger(Throwable|string $error, array $context = []): void
{
    ErrorHandler::log($error, $context);
}

function is_api_request(): bool
{
    $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);

    return is_string($path) && str_starts_with($path, '/api/');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}
