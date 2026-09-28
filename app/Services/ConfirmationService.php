<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Models/User.php';

class ConfirmationService
{
    private User $userModel;

    public function __construct(?User $userModel = null)
    {
        // Permitimos inyectar User para facilitar las pruebas.
        $this->userModel = $userModel ?? new User();
    }

    /**
     * Confirma una cuenta utilizando el token recibido
     * en el enlace del email.
     */
    public function confirm(string $token): array
    {
        // Un token generado por nuestro sistema tiene 64 caracteres
        // hexadecimales porque procede de bin2hex(random_bytes(32)).
        if (
            strlen($token) !== 64
            || preg_match('/^[a-f0-9]{64}$/', $token) !== 1
        ) {
            return [
                'success' => false,
                'message' => 'Invalid confirmation token.',
            ];
        }

        // La base de datos solo contiene el hash del token original.
        $tokenHash = hash('sha256', $token);

        $user = $this->userModel->findByConfirmationTokenHash(
            $tokenHash
        );

        if ($user === null) {
            return [
                'success' => false,
                'message' => 'Invalid confirmation token.',
            ];
        }

        // Si ya está confirmado, no necesitamos volver a modificarlo.
        if ((bool) $user['is_confirmed']) {
            return [
                'success' => true,
                'message' => 'Account already confirmed.',
            ];
        }

        // Convertimos la fecha de MariaDB en un timestamp
        // para poder comprobar si el enlace ha caducado.
        $expiresAt = strtotime(
            (string) $user['confirmation_expires_at']
        );

        if ($expiresAt === false || $expiresAt < time()) {
            return [
                'success' => false,
                'message' => 'Confirmation token expired.',
            ];
        }

        // El token es válido y no ha caducado.
        $this->userModel->confirm((int) $user['id']);

        return [
            'success' => true,
            'message' => 'Account confirmed successfully.',
        ];
    }
}