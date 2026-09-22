<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Models/User.php';
require_once dirname(__DIR__) . '/Core/Validator.php';

class AuthService
{
    private User $userModel;

    public function __construct(?User $userModel = null)
    {
        $this->userModel = $userModel ?? new User();
    }

    public function register(
        string $username,
        string $email,
        string $password
    ): array {
        $username = trim($username);
        $email = strtolower(trim($email));

        $errors = [];

        if (!Validator::username($username)) {
            $errors['username'] = 'Invalid username.';
        }

        if (!Validator::email($email)) {
            $errors['email'] = 'Invalid email address.';
        }

        if (!Validator::password($password)) {
            $errors['password'] = 'Invalid password.';
        }

        if ($this->userModel->findByUsername($username) !== null) {
            $errors['username'] = 'Username already exists.';
        }

        if ($this->userModel->findByEmail($email) !== null) {
            $errors['email'] = 'Email already exists.';
        }

        if ($errors !== []) {
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $passwordHash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        if ($passwordHash === false) {
            throw new RuntimeException('Password hashing failed.');
        }

        $confirmationToken = $this->generateConfirmationToken();

        $userId = $this->userModel->create(
            $username,
            $email,
            $passwordHash,
            $confirmationToken['hash'],
            $confirmationToken['expires_at']
        );

        return [
            'success' => true,
            'user_id' => $userId,
            'username' => $username,
            'email' => $email,
            'confirmation_token' => $confirmationToken['token'],
        ];
    }

    private function generateConfirmationToken(): array
    {
        $token = bin2hex(random_bytes(32));

        return [
            'token' => $token,
            'hash' => hash('sha256', $token),
            'expires_at' => date(
                'Y-m-d H:i:s',
                time() + 86400
            ),
        ];
    }
}