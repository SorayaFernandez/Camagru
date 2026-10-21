<?php

declare(strict_types=1);

/**
 * Modelo encargado de trabajar con las imágenes almacenadas
 * en la base de datos.
 *
 * Este modelo NO se encarga de:
 * - validar archivos;
 * - mover archivos;
 * - aplicar overlays;
 * - generar imágenes.
 *
 * Esas responsabilidades pertenecen a los servicios
 * correspondientes.
 */
class Image
{
    /*
     * Conexión PDO utilizada para realizar las consultas.
     */
    private PDO $database;

    /**
     * Recibe la conexión a la base de datos.
     */
    public function __construct(PDO $database)
    {
        $this->database = $database;
    }

    /**
     * Crea un nuevo registro de imagen.
     *
     * La imagen física ya debe haber sido validada y almacenada
     * por ImageService antes de llamar a este método.
     *
     * @return int ID de la imagen creada.
     */
    public function create(
        int $userId,
        string $storedFilename,
        string $mimeType,
        int $width,
        int $height
    ): int {
        /*
         * Insertamos únicamente información controlada por
         * nuestra aplicación.
         *
         * Las consultas preparadas evitan inyección SQL.
         */
        $statement = $this->database->prepare(
            'INSERT INTO images (
                user_id,
                stored_filename,
                mime_type,
                width,
                height
            )
            VALUES (
                :user_id,
                :stored_filename,
                :mime_type,
                :width,
                :height
            )'
        );

        $statement->execute([
            'user_id' => $userId,
            'stored_filename' => $storedFilename,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
        ]);

        /*
         * MariaDB genera automáticamente el ID.
         */
        return (int) $this->database->lastInsertId();
    }

    /**
     * Guarda el nombre del archivo generado después
     * de aplicar los overlays.
     */
    public function setGeneratedFilename(
        int $imageId,
        string $generatedFilename
    ): void {
        /*
         * El archivo generado se almacena en storage/generated/.
         *
         * Aquí solamente guardamos su nombre en la base de datos.
         */
        $statement = $this->database->prepare(
            'UPDATE images
             SET generated_filename = :generated_filename
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'generated_filename' => $generatedFilename,
            'id' => $imageId,
        ]);
    }

    /**
     * Busca una imagen por su ID.
     */
    public function findById(int $imageId): ?array
    {
        /*
         * Obtenemos también el username para que las consultas
         * de la galería puedan mostrar quién publicó la imagen.
         */
        $statement = $this->database->prepare(
            'SELECT
                images.id,
                images.user_id,
                images.stored_filename,
                images.generated_filename,
                images.mime_type,
                images.width,
                images.height,
                images.created_at,
                users.username
             FROM images
             INNER JOIN users
                ON users.id = images.user_id
             WHERE images.id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $imageId,
        ]);

        $image = $statement->fetch();

        return $image !== false ? $image : null;
    }

    /**
     * Obtiene todas las imágenes de un usuario.
     *
     * Las más recientes aparecen primero.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findByUserId(int $userId): array
    {
        $statement = $this->database->prepare(
            'SELECT
                id,
                user_id,
                stored_filename,
                generated_filename,
                mime_type,
                width,
                height,
                created_at
             FROM images
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC'
        );

        $statement->execute([
            'user_id' => $userId,
        ]);

        return $statement->fetchAll();
    }

    
    /**
     * Obtiene las imágenes de la galería con su información de "Me gusta".
     *
     * @return array<int, array<string, mixed>>
     */
    public function findForGallery(
        int $limit = 20,
        int $offset = 0,
        ?int $currentUserId = null
    ): array {
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $userLikeSql = $currentUserId !== null
            ? 'EXISTS (
                SELECT 1
                FROM likes AS user_likes
                WHERE user_likes.image_id = images.id
                  AND user_likes.user_id = :current_user_id
            )'
            : '0';

        $sql = '
            SELECT
                images.id,
                images.user_id,
                images.generated_filename,
                images.width,
                images.height,
                images.created_at,
                users.username,
                (
                    SELECT COUNT(*)
                    FROM likes AS image_likes
                    WHERE image_likes.image_id = images.id
                ) AS likes_count,
                ' . $userLikeSql . ' AS user_liked
            FROM images
            INNER JOIN users
                ON users.id = images.user_id
            WHERE images.generated_filename IS NOT NULL
            ORDER BY images.created_at DESC, images.id DESC
            LIMIT ' . $limit . '
            OFFSET ' . $offset;

        $statement = $this->database->prepare($sql);

        if ($currentUserId !== null) {
            $statement->bindValue(
                ':current_user_id',
                $currentUserId,
                PDO::PARAM_INT
            );
        }

        $statement->execute();

        return $statement->fetchAll();
    }


    /**
     * Cuenta las imágenes publicadas que pueden aparecer
     * en la galería y que ya tienen una imagen generada.
     *
     * Este total permite calcular cuántas páginas necesita
     * la galería sin cargar todas las imágenes en memoria.
     */
    public function countGalleryImages(): int
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*)
             FROM images
             WHERE generated_filename IS NOT NULL'
        );

        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * Cuenta los "Me gusta" de una imagen.
     */
    public function countLikes(int $imageId): int
    {
        $statement = $this->database->prepare(
            'SELECT COUNT(*)
             FROM likes
             WHERE image_id = :image_id'
        );

        $statement->execute([
            'image_id' => $imageId,
        ]);

        return (int) $statement->fetchColumn();
    }

    /**
     * Comprueba si un usuario ya ha dado "Me gusta".
     */
    public function hasUserLiked(
        int $imageId,
        int $userId
    ): bool {
        $statement = $this->database->prepare(
            'SELECT 1
             FROM likes
             WHERE image_id = :image_id
               AND user_id = :user_id
             LIMIT 1'
        );

        $statement->execute([
            'image_id' => $imageId,
            'user_id' => $userId,
        ]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * Registra un "Me gusta".
     *
     * La restricción UNIQUE de la base de datos impide
     * que un usuario tenga duplicados para la misma imagen.
     */
    public function addLike(int $imageId, int $userId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO likes (user_id, image_id)
             VALUES (:user_id, :image_id)
             ON DUPLICATE KEY UPDATE user_id = VALUES(user_id)'
        );

        $statement->execute([
            'user_id' => $userId,
            'image_id' => $imageId,
        ]);
    }

    /**
     * Elimina el "Me gusta" de ese usuario para esa imagen.
     */
    public function removeLike(int $imageId, int $userId): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM likes
             WHERE image_id = :image_id
               AND user_id = :user_id'
        );

        $statement->execute([
            'image_id' => $imageId,
            'user_id' => $userId,
        ]);
    }

    /**
     * Guarda un comentario.
     */
    public function addComment(
        int $imageId,
        int $userId,
        string $content
    ): void {
        $statement = $this->database->prepare(
            'INSERT INTO comments (user_id, image_id, content)
             VALUES (:user_id, :image_id, :content)'
        );

        $statement->execute([
            'user_id' => $userId,
            'image_id' => $imageId,
            'content' => $content,
        ]);
    }

    /**
     * Recupera los comentarios de una imagen con sus autores.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findCommentsForImage(int $imageId): array
    {
        $statement = $this->database->prepare(
            'SELECT
                comments.id,
                comments.user_id,
                comments.image_id,
                comments.content,
                comments.created_at,
                users.username
             FROM comments
             INNER JOIN users
                ON users.id = comments.user_id
             WHERE comments.image_id = :image_id
             ORDER BY comments.created_at ASC, comments.id ASC'
        );

        $statement->execute([
            'image_id' => $imageId,
        ]);

        return $statement->fetchAll();
    }

    /**
     * Elimina una imagen de la base de datos.
     *
     * La eliminación física de los archivos se realizará
     * desde el servicio correspondiente, no desde el modelo.
     */
    public function delete(int $imageId): void
    {
        $statement = $this->database->prepare(
            'DELETE FROM images
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute([
            'id' => $imageId,
        ]);
    }
}