<?php

declare(strict_types=1);

class Controller
{
    protected function render(
        string $view,
        array $data = []
    ): void {
        extract($data, EXTR_SKIP);

        $viewPath = dirname(__DIR__) . '/Views/' . $view . '.php';

        if (!is_file($viewPath)) {
            throw new RuntimeException('View not found.');
        }

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        $title = $data['title'] ?? 'Camagru';

        require dirname(__DIR__) . '/Views/layouts/main.php';
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}