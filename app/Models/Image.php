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
     * Obtiene las imágenes que forman parte de la galería pública.
     *
     * De momento utilizamos la imagen generada como referencia
     * para la galería.
     *
     * Las imágenes sin resultado generado todavía no se muestran.
     *
     * @return array<int, array<string, mixed>>
     */
    public function findForGallery(
        int $limit = 20,
        int $offset = 0
    ): array {
        /*
         * Limit y offset no se pueden utilizar directamente
         * como parámetros PDO en todas las configuraciones.
         *
         * Por eso los convertimos explícitamente a enteros antes
         * de incorporarlos a la consulta.
         */
        $limit = max(1, min($limit, 100));
        $offset = max(0, $offset);

        $statement = $this->database->query(
            'SELECT
                images.id,
                images.user_id,
                images.generated_filename,
                images.width,
                images.height,
                images.created_at,
                users.username
             FROM images
             INNER JOIN users
                ON users.id = images.user_id
             WHERE images.generated_filename IS NOT NULL
             ORDER BY images.created_at DESC, images.id DESC
             LIMIT ' . $limit . '
             OFFSET ' . $offset
        );

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