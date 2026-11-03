<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

final class View
{
    private string $viewsPath;

    public function __construct(string $viewsPath)
    {
        $this->viewsPath = rtrim($viewsPath, DIRECTORY_SEPARATOR);
    }

    /** @param array<string, mixed> $data */
    public function render(string $template, array $data = [], bool $layout = true): string
    {
        $content = $this->renderFile($template, $data);
        if (!$layout) {
            return $content;
        }

        return $this->renderFile('layouts/main', array_replace($data, ['content' => $content]));
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        if (!preg_match('#^[A-Za-z0-9_/]+$#', $template)) {
            throw new RuntimeException('Invalid view template.');
        }

        $path = $this->viewsPath . DIRECTORY_SEPARATOR . $template . '.php';
        if (!is_file($path)) {
            throw new RuntimeException('View template not found.');
        }

        $viewData = $data;
        $data = $viewData;
        ob_start();
        try {
            require $path;
            return (string) ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
    }
}
