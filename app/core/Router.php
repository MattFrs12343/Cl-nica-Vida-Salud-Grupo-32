<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class Router
{
    /** @var array<string, array<string, array{handler: mixed, middlewares: array<int, mixed>}>> */
    private array $routes = [];

    /** @var array<int, array{prefix: string, middlewares: array<int, mixed>}> */
    private array $groupStack = [];

    /** @var array<string, class-string> */
    private array $middlewareAliases = [
        'csrf' => \App\Middlewares\CsrfMiddleware::class,
    ];

    public function get(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, mixed $handler, array $middlewares = []): void
    {
        $this->add('DELETE', $path, $handler, $middlewares);
    }

    public function add(string $method, string $path, mixed $handler, array $middlewares = []): void
    {
        $prefix = '';
        $groupMiddlewares = [];

        foreach ($this->groupStack as $group) {
            $prefix .= $group['prefix'];
            $groupMiddlewares = array_merge($groupMiddlewares, $group['middlewares']);
        }

        $fullPath = '/' . trim($prefix . '/' . trim($path, '/'), '/');

        if ($fullPath === '//') {
            $fullPath = '/';
        }

        $this->routes[strtoupper($method)][$fullPath] = [
            'handler' => $handler,
            'middlewares' => array_merge($groupMiddlewares, $middlewares),
        ];
    }

    public function group(string $prefix, callable $callback, array $middlewares = []): void
    {
        $this->groupStack[] = [
            'prefix' => '/' . trim($prefix, '/'),
            'middlewares' => $middlewares,
        ];

        $callback($this);

        array_pop($this->groupStack);
    }

    public function dispatch(Request $request): Response
    {
        $params = [];
        $route = $this->match($request->method(), $request->path(), $params);

        if ($route === null) {
            $allowed = $this->allowedMethods($request->path());

            if ($allowed !== []) {
                throw new HttpException(405, 'Método no permitido.', [
                    'Allow' => implode(', ', $allowed),
                ]);
            }

            throw new HttpException(404, 'Página no encontrada.');
        }

        $middlewares = $this->resolveMiddlewares($route['middlewares']);
        $next = function (Request $currentRequest) use ($route, $params): Response {
            return $this->invoke($route['handler'], $currentRequest, $params);
        };

        foreach (array_reverse($middlewares) as $middleware) {
            $nextMiddleware = $next;
            $next = static function (Request $currentRequest) use ($middleware, $nextMiddleware): Response {
                if ($middleware instanceof MiddlewareInterface) {
                    return $middleware->handle($currentRequest, $nextMiddleware);
                }

                return $middleware($currentRequest, $nextMiddleware);
            };
        }

        return $next($request);
    }

    /**
     * @param array<string, string> $params
     * @return array{handler: mixed, middlewares: array<int, mixed>}|null
     */
    private function match(string $method, string $path, array &$params): ?array
    {
        $params = [];

        if (isset($this->routes[$method][$path])) {
            return $this->routes[$method][$path];
        }

        foreach ($this->routes[$method] ?? [] as $routePath => $route) {
            $routeSegments = explode('/', trim($routePath, '/'));
            $pathSegments = explode('/', trim($path, '/'));

            if (count($routeSegments) !== count($pathSegments)) {
                continue;
            }

            $candidateParams = [];
            $matches = true;

            foreach ($routeSegments as $index => $segment) {
                if (str_starts_with($segment, '{') && str_ends_with($segment, '}')) {
                    $name = substr($segment, 1, -1);

                    if ($name === '' || $pathSegments[$index] === '') {
                        $matches = false;
                        break;
                    }

                    $candidateParams[$name] = rawurldecode($pathSegments[$index]);
                    continue;
                }

                if ($segment !== $pathSegments[$index]) {
                    $matches = false;
                    break;
                }
            }

            if ($matches) {
                $params = $candidateParams;

                return $route;
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function allowedMethods(string $path): array
    {
        $allowed = [];

        foreach (array_keys($this->routes) as $method) {
            $params = [];

            if ($this->match($method, $path, $params) !== null) {
                $allowed[] = $method;
            }
        }

        return $allowed;
    }

    /** @param array<int, mixed> $definitions @return array<int, MiddlewareInterface|callable> */
    private function resolveMiddlewares(array $definitions): array
    {
        $resolved = [];

        foreach ($definitions as $definition) {
            if ($definition instanceof MiddlewareInterface || is_callable($definition)) {
                $resolved[] = $definition;
                continue;
            }

            if (!is_string($definition)) {
                throw new RuntimeException('Definición de middleware inválida.');
            }

            $args = [];

            if (str_contains($definition, ':')) {
                [$name, $argumentList] = explode(':', $definition, 2);
                $args = array_map('trim', explode(',', $argumentList));
            } else {
                $name = $definition;
            }

            $class = $this->middlewareAliases[$name] ?? $name;

            if (!class_exists($class)) {
                throw new RuntimeException('Middleware no encontrado: ' . $name);
            }

            $resolved[] = new $class(...$args);
        }

        return $resolved;
    }

    /**
     * @param array<string, string> $params
     */
    private function invoke(mixed $handler, Request $request, array $params): Response
    {
        if (is_string($handler) && str_contains($handler, '@')) {
            $handler = explode('@', $handler, 2);
        }

        if (is_array($handler) && count($handler) === 2) {
            [$class, $method] = $handler;

            if (is_string($class)) {
                if (!class_exists($class)) {
                    throw new RuntimeException('Controlador no encontrado: ' . $class);
                }

                $class = new $class();
            }

            if (!is_object($class) || !is_string($method) || !method_exists($class, $method)) {
                throw new RuntimeException('Acción de controlador inválida.');
            }

            $result = $class->{$method}($request, $params);
        } elseif (is_callable($handler)) {
            $result = $handler($request, $params);
        } else {
            throw new RuntimeException('Handler de ruta inválido.');
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        if ($result === null) {
            return Response::noContent();
        }

        return Response::html((string) $result);
    }
}
