<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $view, array $data = [], ?string $layout = null): Response
    {
        return Response::html(View::render($view, $data, $layout));
    }

    protected function json(mixed $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    /** @param array<string, mixed> $meta */
    protected function ok(mixed $data = [], array $meta = [], ?string $message = null): Response
    {
        return Response::json([
            'ok' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta === [] ? (object) [] : $meta,
        ]);
    }

    /** @param array<string, string> $fields */
    protected function error(string $code, string $message, array $fields = [], int $status = 422): Response
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
}
