<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Config
{
    /** @var array<string, array<string, mixed>> */
    private static array $items = [];

    public static function load(string $key, string $path): void
    {
        if (!is_file($path)) {
            throw new RuntimeException('No se encontró el archivo de configuración: ' . $path);
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new RuntimeException('La configuración debe devolver un array: ' . $path);
        }

        self::$items[$key] = $data;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $target = &self::$items;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $target[$segment] = $value;
                return;
            }

            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}
