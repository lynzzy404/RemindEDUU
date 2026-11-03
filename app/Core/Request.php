<?php

declare(strict_types=1);

namespace App\Core;

final class Request
{
    public function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    public function path(): string
    {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($requestUri, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        $source = $this->method() === 'POST' ? $_POST : $_GET;

        return array_key_exists($key, $source) ? $source[$key] : $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_string($value) ? $value : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return filter_var($value, FILTER_VALIDATE_INT) !== false
            ? (int) $value
            : $default;
    }

    public function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }
}
