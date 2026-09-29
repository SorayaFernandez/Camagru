<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Database.php';

class User
{
    private PDO $database;

    public function __construct(?PDO $database = null)
    {
        $this->database = $database ?? Database::connection();
    }

    public function findById(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, username, email, password_hash, is_confirmed,
                    comment_notifications, created_at, updated_at
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $id,
        ]);

        $user = $statement->fetch();

        return $user !== false ? $user : null;
    }

    public function findByEmail(string $email): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, username, email, password_hash, is_confirmed,
                    comment_notifications, created_at, updated_at
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->execute([
            'email' => $email,
        ]);

        $user = $statement->fetch();

        return $user !== false ? $user : null;
    }

    public function findByUsername(string $username): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, username, email, password_hash, is_confirmed,
                    comment_notifications, created_at, updated_at
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $statement->execute([
            'username' => $username,
        ]);

        $user = $statement->fetch();

        return $user !== false ? $user : null;
    }

    public function create(
        string $username,
        string $email,
        string $passwordHash,
        string $confirmationTokenHash,
        string $confirmationExpiresAt
    ): int {
        $statement = $this->database->prepare(
            'INSERT INTO users (
                username,
                email,
                password_hash,
                confirmation_token_hash,
                confirmation_expires_at
            ) VALUES (
                :username,
                :email,
                :password_hash,
                :confirmation_token_hash,
                :confirmation_expires_at
            )'
        );

        $statement->execute([
            'username' => $username,
            'email' => $email,
            'password_hash' => $passwordHash,
            'confirmation_token_hash' => $confirmationTokenHash,
            'confirmation_expires_at' => $confirmationExpiresAt,
        ]);

        return (int) $this->database->lastInsertId();
    }

        /**
     * Busca un usuario mediante el hash del token de confirmación.
     *
     * El token original nunca se guarda en la base de datos.
     * Solo almacenamos su SHA-256.
     */
    public function findByConfirmationTokenHash(
        string $tokenHash
    ): ?array {
        $statement = $this->database->prepare(
            'SELECT
                id,
                username,
                email,
                is_confirmed,
                confirmation_expires_at
             FROM users
             WHERE confirmation_token_hash = :token_hash
             LIMIT 1'
        );

        $statement->execute([
            'token_hash' => $tokenHash,
        ]);

        $user = $statement->fetch();

        return $user !== false ? $user : null;
    }

        /**
     * Activa la cuenta y elimina el token de confirmación.
     *
     * Al eliminar el token, el mismo enlace no puede volver
     * a utilizarse posteriormente.
     */
    public function confirm(int $userId): void
    {
        $statement = $this->database->prepare(
            'UPDATE users
             SET
                is_confirmed = TRUE,
                confirmation_token_hash = NULL,
                confirmation_expires_at = NULL
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $userId,
        ]);
    }

    /**
     * Busca un usuario por su dirección de correo electrónico
     * y devuelve toda la información necesaria para iniciar sesión.
     *
     * El método reutiliza la búsqueda existente por email, pero
     * mantiene toda la lógica de acceso a datos dentro del modelo.
     *
     * @param string $email Dirección de correo del usuario.
     *
     * @return array<string, mixed>|null Datos del usuario o null
     * si no existe.
     */
    public static function findForLogin(string $email): ?array
    {
        /*
        * Obtenemos la conexión PDO mediante la clase Database.
        */
        $database = Database::connection();

        /*
        * Utilizamos una consulta preparada para evitar
        * inyección SQL.
        */
        $statement = $database->prepare(
            'SELECT
                id,
                username,
                email,
                password_hash,
                is_confirmed
            FROM users
            WHERE email = :email
            LIMIT 1'
        );

        /*
        * Normalizamos el email antes de realizar la búsqueda.
        */
        $normalizedEmail = strtolower(trim($email));

        /*
        * Ejecutamos la consulta pasando el email como parámetro.
        */
        $statement->execute([
            'email' => $normalizedEmail,
        ]);

        /*
        * Recuperamos el usuario como array asociativo.
        */
        $user = $statement->fetch();

        /*
        * Si no existe ningún usuario con ese email,
        * devolvemos null.
        */
        if ($user === false) {
            return null;
        }

        /*
        * Convertimos explícitamente los campos booleanos y numéricos
        * para trabajar con tipos coherentes en PHP.
        */
        $user['id'] = (int) $user['id'];
        $user['is_confirmed'] = (bool) $user['is_confirmed'];

        return $user;
    }
}