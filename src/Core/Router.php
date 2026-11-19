<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

/**
 * Roteador com parâmetros (/alunos/{id}), grupos e middlewares.
 *
 * Middleware = classe com handle(Request $request, Closure $next): Response.
 */
final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable|array, middleware: list<class-string>}> */
    private array $routes = [];

    private string $prefix = '';

    /** @var list<class-string> */
    private array $middleware = [];

    public function get(string $path, callable|array $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** @param list<class-string> $middleware */
    public function group(string $prefix, array $middleware, Closure $routes): void
    {
        [$prevPrefix, $prevMiddleware] = [$this->prefix, $this->middleware];

        $this->prefix .= $prefix;
        $this->middleware = [...$this->middleware, ...$middleware];
        $routes($this);

        [$this->prefix, $this->middleware] = [$prevPrefix, $prevMiddleware];
    }

    public function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [
            'method'     => strtoupper($method),
            'pattern'    => $this->normalize($this->prefix . $path),
            'handler'    => $handler,
            'middleware' => $this->middleware,
        ];
    }

    public function dispatch(Request $request): Response
    {
        $path = $this->normalize($request->path());
        $method = $request->method() === 'HEAD' ? 'GET' : $request->method();
        $allowed = [];

        foreach ($this->routes as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params === null) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed[] = $route['method'];
                continue;
            }

            return $this->run($route, $request, $params);
        }

        if ($allowed !== []) {
            throw new HttpException(405, '', ['Allow' => implode(', ', array_unique($allowed))]);
        }

        throw new HttpException(404);
    }

    /**
     * @param array{handler: callable|array, middleware: list<class-string>} $route
     * @param array<string, string> $params
     */
    private function run(array $route, Request $request, array $params): Response
    {
        $next = fn (Request $r): Response => $this->call($route['handler'], $r, $params);

        foreach (array_reverse($route['middleware']) as $class) {
            $next = static fn (Request $r): Response => (new $class())->handle($r, $next);
        }

        return $next($request);
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
