<?php

declare(strict_types=1);

namespace App\Core;

use Closure;

final class Router
{
    /** @var list<array{method: string, pattern: string, handler: Closure}> */
    private array $routes = [];

    /** @param Closure(Request, array<string, string>): Response $notFoundHandler */
    public function __construct(private readonly Closure $notFoundHandler)
    {
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function get(string $path, Closure $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    public function post(string $path, Closure $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    /** @param Closure(Request, array<string, string>): Response $handler */
    private function add(string $method, string $path, Closure $handler): void
    {
        if (!preg_match('#^/[A-Za-z0-9_/{}/-]*$#', $path)) {
            throw new \InvalidArgumentException('Invalid route pattern.');
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $path,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): Response
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method()) {
                continue;
            }

            $parameters = $this->match($route['pattern'], $request->path());
            if ($parameters !== null) {
                return ($route['handler'])($request, $parameters);
            }
        }

        return ($this->notFoundHandler)($request, []);
    }

    /** @return array<string, string>|null */
    private function match(string $pattern, string $path): ?array
    {
        $parameterNames = [];
        $regex = preg_replace_callback(
            '/\{([A-Za-z][A-Za-z0-9_]*)\}/',
            static function (array $matches) use (&$parameterNames): string {
                $parameterNames[] = $matches[1];
                if ($matches[1] === 'id') {
                    return '(?<id>(?:usr|cat|tsk)_[a-f0-9]{16})';
                }

                return '(?<' . $matches[1] . '>[^/]+)';
            },
            $pattern
        );

        if (!is_string($regex)) {
            return null;
        }

        $result = preg_match('#^' . $regex . '$#', $path, $matches);
        if ($result !== 1) {
            return null;
        }

        $parameters = [];
        foreach ($parameterNames as $name) {
            $parameters[$name] = (string) $matches[$name];
        }

        return $parameters;
    }
}
