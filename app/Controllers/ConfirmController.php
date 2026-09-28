<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Controller.php';
require_once dirname(__DIR__) . '/Services/ConfirmationService.php';

class ConfirmController extends Controller
{
    private ConfirmationService $confirmationService;

    public function __construct(
        ?ConfirmationService $confirmationService = null
    ) {
        // El Controller delega la lógica de negocio en el Service.
        $this->confirmationService =
            $confirmationService ?? new ConfirmationService();
    }

    /**
     * Procesa el enlace de confirmación recibido por email.
     *
     * Ejemplo:
     * /confirm?token=abc123...
     */
    public function confirm(): void
    {
        $token = $_GET['token'] ?? '';

        if (!is_string($token)) {
            $token = '';
        }

        $result = $this->confirmationService->confirm($token);

        $this->render('auth/confirm', [
            'title' => 'Account confirmation - Camagru',
            'success' => $result['success'],
            'message' => $result['message'],
        ]);
    }
}