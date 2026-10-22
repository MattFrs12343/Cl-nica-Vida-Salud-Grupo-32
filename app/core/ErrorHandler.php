<?php

declare(strict_types=1);

namespace App\Core;

use ErrorException;
use Throwable;

final class ErrorHandler
{
    private static bool $handled = false;

    public static function register(): void
    {
        error_reporting(E_ALL);
        ini_set('display_errors', '0');
        ini_set('log_errors', '1');
        ini_set('error_log', storage_path('logs/php.log'));

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleException(Throwable $exception): void
    {
        $status = $exception instanceof HttpException ? $exception->statusCode() : 500;
        $headers = $exception instanceof HttpException ? $exception->headers() : [];
        $errorCode = $exception instanceof HttpException ? $exception->errorCode() : null;
        $fields = $exception instanceof HttpException ? $exception->fields() : [];

        self::log($exception);
        self::render($status, self::messageFor($exception), $headers, $exception, $errorCode, $fields);
    }

    public static function handleShutdown(): void
    {
        if (self::$handled) {
            return;
        }

        $error = error_get_last();

        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }

        $exception = new ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        );

        self::handleException($exception);
    }

    public static function log(Throwable|string $error, array $context = []): void
    {
        $directory = storage_path('logs');

        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }

        if ($error instanceof Throwable) {
            $summary = sprintf(
                '%s: %s in %s:%d',
                $error::class,
                $error->getMessage(),
                $error->getFile(),
                $error->getLine()
            );

            if (config('app.debug')) {
                $summary .= PHP_EOL . $error->getTraceAsString();
            }
        } else {
            $summary = $error;
        }

        if ($context !== []) {
            $summary .= ' ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $line = sprintf(
            "[%s] %s %s | %s%s",
            date('c'),
            $_SERVER['REQUEST_METHOD'] ?? 'CLI',
            $_SERVER['REQUEST_URI'] ?? '-',
            $summary,
            PHP_EOL
        );

        if (@file_put_contents($directory . '/app.log', $line, FILE_APPEND | LOCK_EX) === false) {
            error_log($line);
        }
    }

    /**
     * @param array<string, string> $headers
     * @param array<string, string> $fields
     */
    private static function render(
        int $status,
        string $message,
        array $headers = [],
        ?Throwable $exception = null,
        ?string $errorCode = null,
        array $fields = []
    ): void
    {
        if (self::$handled) {
            return;
        }

        self::$handled = true;

        if (!headers_sent()) {
            http_response_code($status);

            foreach ($headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if (self::wantsJson()) {
            $payload = [
                'ok' => false,
                'error' => [
                    'code' => $errorCode ?? self::codeFor($status),
                    'message' => $message,
                    'fields' => $fields === [] ? (object) [] : $fields,
                ],
            ];

            if (config('app.debug') && $exception !== null) {
                $payload['error']['debug'] = [
                    'exception' => $exception::class,
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ];
            }

            if (!headers_sent()) {
                header('Content-Type: application/json; charset=UTF-8');
            }

            echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return;
        }

        try {
            $body = View::render('errors/error', [
                'status' => $status,
                'message' => $message,
                'title' => 'Error ' . $status,
            ]);
        } catch (Throwable) {
            $body = '<!doctype html><html lang="es"><meta charset="utf-8"><title>Error ' . $status
                . '</title><body style="font-family:system-ui;padding:3rem"><h1>Error ' . $status
                . '</h1><p>' . e($message) . '</p></body></html>';
        }

        echo $body;
    }

    private static function messageFor(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getMessage() !== '' ? $exception->getMessage() : self::defaultMessage($exception->statusCode());
        }

        if (config('app.debug') && $exception->getMessage() !== '') {
            return $exception->getMessage();
        }

        return 'Ocurrió un error interno. Intenta nuevamente o contacta al administrador.';
    }

    private static function defaultMessage(int $status): string
    {
        return match ($status) {
            400 => 'La solicitud no es válida.',
            401 => 'Debes iniciar sesión para continuar.',
            403 => 'No tienes permisos para realizar esta acción.',
            404 => 'La página que buscas no existe.',
            405 => 'El método HTTP no está permitido para esta ruta.',
            422 => 'Revisa los datos enviados.',
            default => 'Ocurrió un error interno. Intenta nuevamente o contacta al administrador.',
        };
    }

    private static function codeFor(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            422 => 'VALIDATION_ERROR',
            default => 'SERVER_ERROR',
        };
    }

    private static function wantsJson(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';

        if (str_starts_with($path, '/api/')) {
            return true;
        }

        $accept = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));

        return str_contains($accept, 'application/json');
    }
}
