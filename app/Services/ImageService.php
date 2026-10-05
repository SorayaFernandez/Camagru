<?php

declare(strict_types=1);

/**
 * Servicio encargado de validar y almacenar imágenes.
 *
 * Toda imagen recibida por la aplicación debe pasar por este
 * servicio antes de ser almacenada.
 */
class ImageService
{
    /*
     * Tamaño máximo permitido para una imagen subida.
     *
     * 5 MB es suficiente para fotografías realizadas con webcam
     * y para imágenes subidas desde un dispositivo.
     */
    private const MAX_FILE_SIZE = 5 * 1024 * 1024;

    /*
     * Anchura máxima permitida para una imagen.
     *
     * Evita procesar imágenes con dimensiones excesivamente
     * grandes que puedan consumir demasiados recursos.
     */
    private const MAX_WIDTH = 6000;

    /*
     * Altura máxima permitida para una imagen.
     *
     * Se aplica la misma protección al eje vertical.
     */
    private const MAX_HEIGHT = 6000;

    /*
     * Número máximo de píxeles permitidos.
     *
     * El límite de píxeles añade una protección adicional:
     * una imagen puede tener dimensiones grandes aunque su
     * archivo comprimido ocupe relativamente poco espacio.
     */
    private const MAX_PIXELS = 25_000_000;

    /*
     * Directorio donde se almacenan temporalmente las imágenes
     * originales antes de aplicar overlays.
     */
    private const UPLOAD_DIRECTORY = __DIR__
        . '/../../storage/uploads';

    /**
     * Valida y almacena una imagen subida mediante HTTP.
     *
     * @param array<string, mixed> $file
     *
     * @return array{
     *     path: string,
     *     filename: string,
     *     mime_type: string,
     *     width: int,
     *     height: int
     * }
     *
     * @throws RuntimeException Si la imagen no es válida.
     */
    public function upload(array $file): array
    {
        /*
         * Comprobamos que PHP haya recibido correctamente
         * el archivo enviado mediante multipart/form-data.
         */
        if (
            !isset($file['error'])
            || !is_int($file['error'])
        ) {
            throw new RuntimeException(
                'La información del archivo no es válida.'
            );
        }

        /*
         * UPLOAD_ERR_OK significa que PHP ha recibido
         * correctamente el archivo.
         */
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(
                $this->getUploadErrorMessage($file['error'])
            );
        }

        /*
         * Comprobamos que el tamaño recibido sea un entero válido.
         */
        if (
            !isset($file['size'])
            || !is_int($file['size'])
        ) {
            throw new RuntimeException(
                'No se ha podido determinar el tamaño de la imagen.'
            );
        }

        /*
         * Rechazamos archivos vacíos o superiores al límite.
         */
        if ($file['size'] <= 0) {
            throw new RuntimeException(
                'La imagen está vacía.'
            );
        }

        if ($file['size'] > self::MAX_FILE_SIZE) {
            throw new RuntimeException(
                'La imagen supera el tamaño máximo permitido de 5 MB.'
            );
        }

        /*
         * PHP proporciona una ruta temporal para el archivo.
         *
         * No utilizamos el nombre original proporcionado por el usuario
         * para construir la ruta final.
         */
        if (
            !isset($file['tmp_name'])
            || !is_string($file['tmp_name'])
            || $file['tmp_name'] === ''
        ) {
            throw new RuntimeException(
                'No se ha recibido correctamente la imagen.'
            );
        }

        $temporaryPath = $file['tmp_name'];

        /*
         * is_uploaded_file() confirma que el archivo procede
         * realmente de una subida HTTP gestionada por PHP.
         *
         * Esto evita aceptar arbitrariamente una ruta enviada
         * por el cliente.
         */
        if (!is_uploaded_file($temporaryPath)) {
            throw new RuntimeException(
                'El archivo recibido no procede de una subida válida.'
            );
        }

        /*
         * Detectamos el MIME real examinando el contenido del archivo.
         *
         * NO confiamos en:
         * - la extensión;
         * - el nombre original;
         * - $_FILES['type'];
         *
         * porque esos valores pueden ser manipulados por el cliente.
         */
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);

        $mimeType = $fileInfo->file($temporaryPath);

        if ($mimeType === false) {
            throw new RuntimeException(
                'No se ha podido determinar el tipo de imagen.'
            );
        }

        /*
         * Solamente aceptamos los formatos que Camagru necesita.
         */
        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            throw new RuntimeException(
                'Formato de imagen no permitido. '
                . 'Solo se permiten JPEG y PNG.'
            );
        }

        /*
         * getimagesize() comprueba que el archivo contiene
         * información válida de una imagen reconocible por PHP.
         *
         * También obtenemos sus dimensiones.
         */
        $imageInfo = getimagesize($temporaryPath);

        if ($imageInfo === false) {
            throw new RuntimeException(
                'El archivo no contiene una imagen válida.'
            );
        }

        $width = $imageInfo[0] ?? 0;
        $height = $imageInfo[1] ?? 0;

        /*
         * Las dimensiones deben ser números positivos.
         */
        if (
            !is_int($width)
            || !is_int($height)
            || $width <= 0
            || $height <= 0
        ) {
            throw new RuntimeException(
                'Las dimensiones de la imagen no son válidas.'
            );
        }

        /*
         * Comprobamos que la imagen no supere las dimensiones
         * máximas permitidas.
         *
         * Esto evita aceptar imágenes excesivamente grandes
         * que posteriormente puedan consumir demasiada memoria
         * durante el procesamiento con GD.
         */
        if (
            $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
        ) {
            throw new RuntimeException(
                'Las dimensiones de la imagen superan el máximo permitido.'
            );
        }

        /*
         * Calculamos el número total de píxeles.
         *
         * Multiplicamos después de convertir ambos valores
         * a float para evitar problemas de overflow en otros
         * entornos con enteros de menor tamaño.
         */
        $totalPixels = (float) $width * (float) $height;

        /*
         * Rechazamos imágenes cuyo número total de píxeles
         * sea excesivo aunque sus dimensiones individuales
         * estén dentro de los límites.
         */
        if ($totalPixels > self::MAX_PIXELS) {
            throw new RuntimeException(
                'La resolución de la imagen es demasiado alta.'
            );
        }

        /*
         * Generamos nosotros mismos el nombre final.
         *
         * No utilizamos el nombre enviado por el usuario porque
         * podría contener caracteres problemáticos o intentar
         * manipular la ruta de almacenamiento.
         */
        $filename = bin2hex(random_bytes(32))
            . '.'
            . $allowedMimeTypes[$mimeType];

        $destination = self::UPLOAD_DIRECTORY
            . '/'
            . $filename;

        /*
         * Nos aseguramos de que el directorio exista.
         *
         * No creamos directorios con permisos 777.
         */
        if (!is_dir(self::UPLOAD_DIRECTORY)) {
            if (!mkdir(self::UPLOAD_DIRECTORY, 0775, true)) {
                throw new RuntimeException(
                    'No se ha podido crear el directorio de imágenes.'
                );
            }
        }

        /*
         * Movemos el archivo temporal a su ubicación definitiva.
         *
         * move_uploaded_file() solamente permite mover archivos
         * reconocidos por PHP como uploads HTTP.
         */
        if (!move_uploaded_file($temporaryPath, $destination)) {
            throw new RuntimeException(
                'No se ha podido guardar la imagen.'
            );
        }

        /*
         * Devolvemos únicamente la información que necesitarán
         * los siguientes componentes de Camagru.
         */
        return [
            'path' => $destination,
            'filename' => $filename,
            'mime_type' => $mimeType,
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * Devuelve un mensaje controlado para los errores
     * estándar de subida de PHP.
     */
    private function getUploadErrorMessage(int $error): string
    {
        /*
         * Traducimos los códigos técnicos de PHP a mensajes
         * comprensibles para la aplicación.
         */
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
                return 'La imagen supera el tamaño máximo permitido por PHP.';

            case UPLOAD_ERR_FORM_SIZE:
                return 'La imagen supera el tamaño máximo permitido.';

            case UPLOAD_ERR_PARTIAL:
                return 'La imagen se ha subido parcialmente.';

            case UPLOAD_ERR_NO_FILE:
                return 'No se ha seleccionado ninguna imagen.';

            case UPLOAD_ERR_NO_TMP_DIR:
                return 'No existe el directorio temporal de PHP.';

            case UPLOAD_ERR_CANT_WRITE:
                return 'No se ha podido escribir la imagen en el servidor.';

            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP ha detenido la subida.';

            default:
                return 'Se ha producido un error al subir la imagen.';
        }
    }
}
