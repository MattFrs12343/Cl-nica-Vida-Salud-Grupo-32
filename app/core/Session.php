<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE || headers_sent()) {
            return;
        }

        $config = (array) config('app.session', []);
        $secure = (bool) ($config['secure'] ?? false);

        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $secure = true;
        }

        session_name((string) ($config['name'] ?? 'vidasalud_session'));
        session_set_cookie_params([
            'lifetime' => (int) ($config['lifetime'] ?? 7200),
            'path' => '/',
            'domain' => '',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => (string) ($config['samesite'] ?? 'Lax'),
        ]);

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');

        session_start();
    }

    private static function ensure(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            self::start();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::ensure();

        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        self::ensure();
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        self::ensure();

        return array_key_exists($key, $_SESSION);
    }

    public static function forget(string $key): void
    {
        self::ensure();
        unset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        self::ensure();
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    public static function flash(string $type, string $message): void
    {
        self::ensure();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return array<int, array{type: string, message: string}> */
    public static function flashes(): array
    {
        self::ensure();
        $flashes = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);

        return is_array($flashes) ? $flashes : [];
    }

    public static function csrfToken(): string
    {
        self::ensure();

        if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        $stored = self::get('_csrf');

        return is_string($token)
            && $token !== ''
            && is_string($stored)
            && hash_equals($stored, $token);
    }

    public static function rotateCsrf(): void
    {
        self::forget('_csrf');
        self::csrfToken();
    }

    /** @param array<string, mixed> $data */
    public static function setOld(array $data): void
    {
        self::set('_old', $data);
    }

    public static function old(string $key, mixed $default = null): mixed
    {
        $old = self::get('_old', []);

        return is_array($old) && array_key_exists($key, $old) ? $old[$key] : $default;
    }

    public static function clearOld(): void
    {
        self::forget('_old');
    }
}
