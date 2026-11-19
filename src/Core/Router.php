<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Roteador simples com suporte a parâmetros: /usuarios/{id}
 */
final class Router
{
    /** @var array<string, array<string, callable|array{class-string, string}>> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[strtoupper($method)][$this->normalize($path)] = $handler;
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalize($request->path());
        $allowed = [];

        foreach ($this->routes as $method => $routes) {
            foreach ($routes as $pattern => $handler) {
                $params = $this->match($pattern, $path);
                if ($params === null) {
                    continue;
                }
                if ($method !== $request->method()) {
                    $allowed[] = $method;
                    continue;
                }

                return $this->call($handler, $request, $params);
            }
        }

        if ($allowed !== []) {
            return Response::json(['error' => 'Método não permitido'], 405)
                ->withHeader('Allow', implode(', ', array_unique($allowed)));
        }

        return Response::html('<h1>404</h1><p>Página não encontrada.</p>', 404);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';

        if (!preg_match($regex, $path, $matches)) {
            return null;
        }

        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    /** @param array<string, string> $params */
    private function call(callable|array $handler, Request $request, array $params): Response
    {
        if (is_array($handler) && is_string($handler[0])) {
            $handler = [new $handler[0](), $handler[1]];
        }

        $result = $handler($request, ...$params);

        return match (true) {
            $result instanceof Response => $result,
            is_array($result)           => Response::json($result),
            default                     => Response::html((string) $result),
        };
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }
}
