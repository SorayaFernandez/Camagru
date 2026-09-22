<?php

declare(strict_types=1);

class Validator
{
    public static function email(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    public static function username(string $username): bool
    {
        return preg_match(
            '/^[A-Za-z0-9_]{3,50}$/',
            $username
        ) === 1;
    }

    public static function password(string $password): bool
    {
        if (strlen($password) < 8) {
            return false;
        }

        if (preg_match('/[A-Z]/', $password) !== 1) {
            return false;
        }

        if (preg_match('/[a-z]/', $password) !== 1) {
            return false;
        }

        if (preg_match('/[0-9]/', $password) !== 1) {
            return false;
        }

        return true;
    }
}