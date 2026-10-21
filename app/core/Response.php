<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        private string $responseBody = '',
        private int $statusCode = 200,
        private array $headers = []
    ) {
    }

    public static function html(string $body, int $status = 200, array $headers = []): self
    {
        return new self($body, $status, array_merge([
            'Content-Type' => 'text/html; charset=UTF-8',
        ], $headers));
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = '{"ok":false,"error":{"code":"ENCODING_ERROR","message":"No se pudo generar la respuesta."}}';
            $status = 500;
        }

        return new self($json, $status, array_merge([
            'Content-Type' => 'application/json; charset=UTF-8',
        ], $headers));
    }

    public static function redirect(string $url, int $status = 302): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function noContent(): self
    {
        return new self('', 204);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }

    public function status(): int
    {
        return $this->statusCode;
    }

    public function body(): string
    {
        return $this->responseBody;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->statusCode);

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }

        if ($this->statusCode !== 204) {
            echo $this->responseBody;
        }
    }
}
