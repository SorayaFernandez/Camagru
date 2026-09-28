<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

/**
 * Devuelve la configuración SMTP de Camagru.
 *
 * Las credenciales se leen desde las variables de entorno.
 * Nunca deben escribirse directamente en el código fuente.
 */
function getMailConfig(): array
{
    return [
        // Servidor SMTP y puerto de conexión.
        'host' => env('SMTP_HOST', ''),
        'port' => (int) env('SMTP_PORT', '587'),

        // Credenciales de autenticación SMTP.
        'username' => env('SMTP_USER', ''),
        'password' => env('SMTP_PASSWORD', ''),

        // Identidad del remitente del correo.
        'from_address' => env('MAIL_FROM_ADDRESS', ''),
        'from_name' => env('MAIL_FROM_NAME', 'Camagru'),

        // Método de cifrado de la conexión SMTP.
        'encryption' => env('SMTP_ENCRYPTION', 'starttls'),
    ];
}