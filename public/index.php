<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Router.php';
require_once dirname(__DIR__) . '/app/Controllers/HomeController.php';
require_once dirname(__DIR__) . '/app/Controllers/RegisterController.php';

$router = new Router();

// Controller de la página principal.
$homeController = new HomeController();

// Controller de registro.
$registerController = new RegisterController();

// Página principal.
$router->get('/', [$homeController, 'index']);

// Formulario de registro.
$router->get('/register', [$registerController, 'show']);

// Procesamiento del formulario de registro.
$router->post('/register', [$registerController, 'register']);

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === false || $path === null) {
    $path = '/';
}

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $path
);