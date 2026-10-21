<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    /**
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $bodyParams
     * @param array<string, string> $headerBag
     * @param array<string, mixed> $files
     */
    private function __construct(
        private string $httpMethod,
        private string $requestPath,
        private array $queryParams,
        private array $bodyParams,
        private array $headerBag,
        private array $files,
        private string $clientIp
    ) {
    }

    public static function capture(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $query = $_GET;
        $body = $_POST;
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($contentType, 'application/json')) {
            $raw = file_get_contents('php://input');
            $decoded = json_decode($raw !== false && $raw !== '' ? $raw : '[]', true);

            if (is_array($decoded)) {
                $body = $decoded;
            }
        }

        $override = $body['_method'] ?? ($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null);

        if ($method === 'POST' && is_string($override)) {
            $override = strtoupper($override);

            if (in_array($override, ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = $override;
            }
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        return new self(
            $method,
            self::normalizePath($path),
            $query,
            $body,
            self::captureHeaders(),
            $_FILES,
            (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')
        );
    }

    /**
     * Constructor útil para pruebas sin pasar por Apache.
     *
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public static function create(string $method, string $path, array $body = [], array $headers = []): self
    {
        return new self(
            strtoupper($method),
            self::normalizePath($path),
            [],
            $body,
            array_change_key_case($headers, CASE_LOWER),
            [],
            '127.0.0.1'
        );
    }

    public function method(): string
    {
        return $this->httpMethod;
    }

    public function path(): string
    {
        return $this->requestPath;
    }

    public function isMethod(string $method): bool
    {
        return $this->httpMethod === strtoupper($method);
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->queryParams;
        }

        return $this->queryParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function body(): array
    {
        return $this->bodyParams;
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->bodyParams[$key] ?? $this->queryParams[$key] ?? $default;
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($this->queryParams, $this->bodyParams);
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->all(), array_flip($keys));
    }

    /**
     * @param array<int, string> $keys
     * @return array<string, mixed>
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->all(), array_flip($keys));
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->bodyParams) || array_key_exists($key, $this->queryParams);
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key);

        if ($value === null) {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function integer(string $key, int $default = 0): int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function header(string $name, ?string $default = null): ?string
    {
        return $this->headerBag[strtolower($name)] ?? $default;
    }

    /** @return array<string, mixed> */
    public function files(): array
    {
        return $this->files;
    }

    public function file(string $key): mixed
    {
        return $this->files[$key] ?? null;
    }

    public function ip(): string
    {
        return $this->clientIp;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('content-type', '')), 'application/json');
    }

    public function wantsJson(): bool
    {
        if (str_starts_with($this->requestPath, '/api/')) {
            return true;
        }

        return str_contains(strtolower((string) $this->header('accept', '')), 'application/json');
    }

    private static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    /** @return array<string, string> */
    private static function captureHeaders(): array
    {
        $headers = [];

        if (function_exists('getallheaders')) {
            foreach ((array) getallheaders() as $name => $value) {
                if (is_string($name) && is_scalar($value)) {
                    $headers[strtolower($name)] = (string) $value;
                }
            }
        }

        foreach ($_SERVER as $name => $value) {
            if (str_starts_with((string) $name, 'HTTP_')) {
                $key = strtolower(str_replace('_', '-', substr((string) $name, 5)));
                $headers[$key] ??= (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        if (isset($_SERVER['CONTENT_LENGTH'])) {
            $headers['content-length'] = (string) $_SERVER['CONTENT_LENGTH'];
        }

        return $headers;
    }
}
