<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    /**
     * Renderiza una vista. Si se indica layout, el HTML de la vista se pasa
     * al layout como $content.
     *
     * @param array<string, mixed> $data
     */
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $content = self::renderFile(self::path($view), $data);

        if ($layout === null || $layout === '') {
            return $content;
        }

        return self::renderFile(self::path($layout), array_merge($data, ['content' => $content]));
    }

    public static function exists(string $view): bool
    {
        return is_file(self::path($view));
    }

    private static function path(string $view): string
    {
        if (str_contains($view, '..')) {
            throw new RuntimeException('Nombre de vista inválido: ' . $view);
        }

        $relative = str_replace('.', '/', $view);
        $file = BASE_PATH . '/app/views/' . $relative . '.php';

        if (!is_file($file)) {
            throw new RuntimeException('Vista no encontrada: ' . $view);
        }

        return $file;
    }

    /** @param array<string, mixed> $data */
    private static function renderFile(string $file, array $data): string
    {
        extract($data, EXTR_SKIP);

        ob_start();

        try {
            require $file;

            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
