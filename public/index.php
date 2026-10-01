<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Auth.php';

// Iniciamos la sesión antes de enviar cualquier contenido HTML.
Auth::startSession();

require_once dirname(__DIR__) . '/app/Core/Csrf.php';
require_once dirname(__DIR__) . '/app/Core/Router.php';
require_once dirname(__DIR__) . '/app/Services/AuthService.php';
require_once dirname(__DIR__) . '/app/Services/MailService.php';
require_once dirname(__DIR__) . '/app/Controllers/HomeController.php';
require_once dirname(__DIR__) . '/app/Controllers/AuthController.php';
require_once dirname(__DIR__) . '/app/Controllers/ConfirmController.php';
require_once dirname(__DIR__) . '/app/Controllers/ProfileController.php';
require_once dirname(__DIR__) . '/app/Controllers/RegisterController.php';

$router = new Router();

// Controller de la página principal.
$homeController = new HomeController();

// Controller de registro.
$registerController = new RegisterController();

// Controller de confirmación.
$confirmController = new ConfirmController();

// Controller de autenticación.
$authController = new AuthController();

// Controller del perfil de usuario.
$profileController = new ProfileController();

// Página principal.
$router->get('/', [$homeController, 'index']);

// Formulario de registro.
$router->get('/register', [$registerController, 'show']);

// Procesamiento del formulario de registro.
$router->post('/register', [$registerController, 'register']);

// Confirmación mediante el enlace recibido por email.
$router->get('/confirm', [$confirmController, 'confirm']);

// Rutas de autenticación
// GET /login  → muestra el formulario.
$router->get('/login', [$authController, 'showLogin']);

// POST /login → procesa las credenciales.
$router->post('/login', [$authController, 'login']);

// POST /logout → cierra la sesión.
$router->post('/logout', [$authController, 'logout']);

// Perfil: mostrar la página de cuenta.
$router->get('/profile', [$profileController, 'show']);

// Perfil: procesar cambios de cuenta o contraseña.
$router->post('/profile', function () use ($profileController): void {

    // El campo "action" determina qué formulario se ha enviado.
    $action = $_POST['action'] ?? '';

    if ($action === 'account') {
        $profileController->updateAccount();
        return;
    }

    if ($action === 'password') {
        $profileController->updatePassword();
        return;
    }

    // Si la operación no existe, devolvemos un error de solicitud.
    http_response_code(400);
    echo 'Solicitud no válida.';
});

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($path === false || $path === null) {
    $path = '/';
}

$router->dispatch(
    $_SERVER['REQUEST_METHOD'],
    $path
);