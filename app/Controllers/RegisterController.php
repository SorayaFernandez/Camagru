<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Controller.php';
require_once dirname(__DIR__) . '/Core/Csrf.php';
require_once dirname(__DIR__) . '/Services/AuthService.php';

class RegisterController extends Controller
{
    private AuthService $authService;

    public function __construct(?AuthService $authService = null)
    {
        // Permitimos inyectar el servicio para facilitar las pruebas.
        $this->authService = $authService ?? new AuthService();
    }

    /**
     * Muestra el formulario de registro.
     */
    public function show(): void
    {
        $this->render('auth/register', [
            'title' => 'Register - Camagru',
            'csrfToken' => Csrf::token(),
            'errors' => [],
            'old' => [],
        ]);
    }

    /**
     * Procesa el formulario de registro.
     */
    public function register(): void
    {
        // Solo aceptamos peticiones POST para crear usuarios.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo 'Method Not Allowed.';
            return;
        }

        // Comprobamos primero la protección CSRF.
        $csrfToken = $_POST['csrf_token'] ?? null;

        if (!is_string($csrfToken) || !Csrf::verify($csrfToken)) {
            http_response_code(403);
            echo 'Invalid CSRF token.';
            return;
        }

        // Convertimos los valores recibidos en strings seguros para trabajar.
        $username = $_POST['username'] ?? '';
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!is_string($username)) {
            $username = '';
        }

        if (!is_string($email)) {
            $email = '';
        }

        if (!is_string($password)) {
            $password = '';
        }

        $result = $this->authService->register(
            $username,
            $email,
            $password
        );

        // Si la validación falla, volvemos a mostrar el formulario.
        if (!$result['success']) {
            $this->render('auth/register', [
                'title' => 'Register - Camagru',
                'csrfToken' => Csrf::token(),
                'errors' => $result['errors'],
                'old' => [
                    'username' => $username,
                    'email' => $email,
                ],
            ]);

            return;
        }

        // Por ahora no enviamos todavía el email de confirmación.
        // Primero dejamos funcionando correctamente la creación del usuario.
        $this->render('auth/register-success', [
            'title' => 'Registration successful - Camagru',
            'email' => $email,
        ]);
    }
}