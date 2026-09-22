<?php

declare(strict_types=1);

require_once __DIR__ . '/Auth.php';

class Csrf
{
    /**
     * Genera un token CSRF para la sesión actual.
     *
     * El token se guarda en la sesión y se incluye
     * posteriormente en los formularios.
     */
    public static function token(): string
    {
        Auth::startSession();

        if (
            !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
        ) {
            // random_bytes() genera datos criptográficamente seguros.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * Comprueba que el token enviado por el formulario
     * coincide con el almacenado en la sesión.
     */
    public static function verify(?string $token): bool
    {
        Auth::startSession();

        if (
            $token === null
            || !isset($_SESSION['csrf_token'])
            || !is_string($_SESSION['csrf_token'])
        ) {
            return false;
        }

        // hash_equals() evita comparaciones vulnerables a timing attacks.
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}