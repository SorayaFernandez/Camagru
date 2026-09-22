<?php

declare(strict_types=1);

class Auth
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }
    }

    public static function login(int $userId): void
    {
        self::startSession();

        session_regenerate_id(true);

        $_SESSION['user_id'] = $userId;
    }

    public static function logout(): void
    {
        self::startSession();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        self::startSession();

        return isset($_SESSION['user_id'])
            && is_int($_SESSION['user_id']);
    }

    public static function userId(): ?int
    {
        self::startSession();

        if (!isset($_SESSION['user_id'])) {
            return null;
        }

        return is_int($_SESSION['user_id'])
            ? $_SESSION['user_id']
            : null;
    }
}