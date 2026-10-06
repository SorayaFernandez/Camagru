<?php

declare(strict_types=1);

/**
 * Servicio encargado de realizar la composición de imágenes
 * mediante la extensión GD de PHP.
 *
 * Responsabilidades:
 *
 * - abrir la imagen original;
 * - abrir el overlay PNG;
 * - adaptar el overlay al tamaño de la fotografía;
 * - combinar ambas imágenes respetando la transparencia;
 * - generar una nueva imagen JPEG;
 * - guardar la imagen final en storage/generated.
 *
 * Este servicio NO se encarga de:
 *
 * - validar formularios;
 * - comprobar CSRF;
 * - gestionar sesiones;
 * - guardar registros en MariaDB;
 * - recibir archivos HTTP.
 *
 * Esas responsabilidades pertenecen a otras capas de la aplicación.
 */
class ImageProcessor
{
    /**
     * Directorio donde se encuentran los overlays disponibles.
     */
    private const OVERLAY_DIRECTORY = __DIR__
        . '/../../storage/overlays';

    /**
     * Directorio donde se guardan las imágenes finales.
     */
    private const GENERATED_DIRECTORY = __DIR__
        . '/../../storage/generated';

    /**
     * Calidad utilizada al generar el JPEG final.
     *
     * 92 proporciona una buena relación entre:
     *
     * - calidad visual;
     * - tamaño del archivo.
     */
    private const JPEG_QUALITY = 92;

    /**
     * Procesa una imagen aplicándole un overlay.
     *
     * @param string $sourcePath Ruta absoluta de la imagen original.
     * @param string $overlayFilename Nombre del overlay PNG.
     *
     * @return array{
     *     filename: string,
     *     path: string,
     *     mime_type: string,
     *     width: int,
     *     height: int
     * }
     */
    public function process(
        string $sourcePath,
        string $overlayFilename
    ): array {
        $this->validateSourcePath($sourcePath);

        $overlayPath = $this->getOverlayPath(
            $overlayFilename
        );

        $sourceImage = $this->loadSourceImage(
            $sourcePath
        );

        $overlayImage = $this->loadOverlayImage(
            $overlayPath
        );

        try {
            $width = imagesx($sourceImage);
            $height = imagesy($sourceImage);

            /*
             * El overlay se adapta al tamaño exacto de la
             * fotografía.
             *
             * Esto permite que un mismo overlay de 1600x1200
             * pueda utilizarse con fotografías de diferentes
             * resoluciones.
             */
            $resizedOverlay = $this->resizeOverlay(
                $overlayImage,
                $width,
                $height
            );

            /*
             * Las imágenes PNG pueden contener transparencia.
             *
             * Activamos blending en la imagen final para que
             * los píxeles transparentes y semitransparentes
             * del overlay se mezclen correctamente con la foto.
             */
            imagealphablending(
                $sourceImage,
                true
            );

            imagesavealpha(
                $sourceImage,
                false
            );

            /*
             * Composición server-side:
             *
             * fotografía + overlay transparente
             *                     ↓
             *               imagen final
             */
            imagecopy(
                $sourceImage,
                $resizedOverlay,
                0,
                0,
                0,
                0,
                $width,
                $height
            );

            /*
             * Generamos un nombre completamente aleatorio.
             *
             * Nunca utilizamos el nombre original del archivo
             * del usuario para evitar colisiones y problemas
             * derivados de nombres proporcionados por el cliente.
             */
            $generatedFilename = bin2hex(
                random_bytes(32)
            ) . '.jpg';

            $generatedPath = self::GENERATED_DIRECTORY
                . '/'
                . $generatedFilename;

            $this->ensureGeneratedDirectory();

            /*
             * El resultado final se guarda siempre como JPEG.
             *
             * Esto proporciona un formato homogéneo para la galería
             * y elimina cualquier transparencia que pudiera tener
             * accidentalmente la imagen original.
             */
            if (!imagejpeg(
                $sourceImage,
                $generatedPath,
                self::JPEG_QUALITY
            )) {
                throw new RuntimeException(
                    'No se ha podido guardar la imagen generada.'
                );
            }

            return [
                'filename' => $generatedFilename,
                'path' => $generatedPath,
                'mime_type' => 'image/jpeg',
                'width' => $width,
                'height' => $height,
            ];
        } finally {
            /*
             * Liberamos siempre la memoria utilizada por GD,
             * incluso si se produce una excepción.
             */
            imagedestroy($overlayImage);
            imagedestroy($sourceImage);

            if (isset($resizedOverlay)) {
                imagedestroy($resizedOverlay);
            }
        }
    }

    /**
     * Comprueba que la imagen original existe y puede leerse.
     */
    private function validateSourcePath(
        string $sourcePath
    ): void {
        if (!is_file($sourcePath)) {
            throw new RuntimeException(
                'La imagen original no existe.'
            );
        }

        if (!is_readable($sourcePath)) {
            throw new RuntimeException(
                'La imagen original no se puede leer.'
            );
        }
    }

    /**
     * Obtiene la ruta absoluta de un overlay.
     *
     * Solo se permiten nombres simples de archivos PNG.
     *
     * No aceptamos rutas proporcionadas directamente por el usuario,
     * como:
     *
     *     ../../archivo.php
     *
     * Esto evita utilizar el servicio como mecanismo para acceder
     * a archivos arbitrarios del servidor.
     */
    private function getOverlayPath(
        string $overlayFilename
    ): string {
        if (
            $overlayFilename === ''
            || basename($overlayFilename) !== $overlayFilename
        ) {
            throw new RuntimeException(
                'El overlay seleccionado no es válido.'
            );
        }

        if (
            !str_ends_with(
                strtolower($overlayFilename),
                '.png'
            )
        ) {
            throw new RuntimeException(
                'El overlay debe ser un archivo PNG.'
            );
        }

        $overlayPath = self::OVERLAY_DIRECTORY
            . '/'
            . $overlayFilename;

        if (!is_file($overlayPath)) {
            throw new RuntimeException(
                'El overlay seleccionado no existe.'
            );
        }

        if (!is_readable($overlayPath)) {
            throw new RuntimeException(
                'El overlay seleccionado no se puede leer.'
            );
        }

        return $overlayPath;
    }

    /**
     * Carga la imagen original utilizando su MIME real.
     *
     * No confiamos en la extensión del archivo.
     */
    private function loadSourceImage(
        string $sourcePath
    ): GdImage {
        $fileInfo = new finfo(
            FILEINFO_MIME_TYPE
        );

        $mimeType = $fileInfo->file(
            $sourcePath
        );

        if ($mimeType === 'image/jpeg') {
            $image = imagecreatefromjpeg(
                $sourcePath
            );
        } elseif ($mimeType === 'image/png') {
            $image = imagecreatefrompng(
                $sourcePath
            );
        } else {
            throw new RuntimeException(
                'El formato de la imagen original no es compatible.'
            );
        }

        if ($image === false) {
            throw new RuntimeException(
                'No se ha podido abrir la imagen original.'
            );
        }

        /*
         * Las imágenes PNG originales pueden tener transparencia.
         *
         * Las convertiremos posteriormente a JPEG, por lo que
         * necesitamos preparar correctamente el canvas.
         */
        if ($mimeType === 'image/png') {
            $image = $this->flattenPng(
                $image
            );
        }

        return $image;
    }

    /**
     * Carga un overlay PNG.
     *
     * Los overlays deben conservar siempre su canal alfa.
     */
    private function loadOverlayImage(
        string $overlayPath
    ): GdImage {
        $image = imagecreatefrompng(
            $overlayPath
        );

        if ($image === false) {
            throw new RuntimeException(
                'No se ha podido abrir el overlay.'
            );
        }

        imagealphablending(
            $image,
            false
        );

        imagesavealpha(
            $image,
            true
        );

        return $image;
    }

    /**
     * Redimensiona el overlay para que tenga exactamente las
     * dimensiones de la fotografía.
     *
     * Se utiliza imagecopyresampled() para obtener un resultado
     * de mayor calidad que un escalado directo.
     */
    private function resizeOverlay(
        GdImage $overlay,
        int $targetWidth,
        int $targetHeight
    ): GdImage {
        $sourceWidth = imagesx($overlay);
        $sourceHeight = imagesy($overlay);

        $resized = imagecreatetruecolor(
            $targetWidth,
            $targetHeight
        );

        if ($resized === false) {
            throw new RuntimeException(
                'No se ha podido preparar el overlay.'
            );
        }

        /*
         * El nuevo canvas debe ser completamente transparente.
         */
        imagealphablending(
            $resized,
            false
        );

        $transparent = imagecolorallocatealpha(
            $resized,
            0,
            0,
            0,
            127
        );

        imagefilledrectangle(
            $resized,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $transparent
        );

        /*
         * Conservamos el canal alfa durante el escalado.
         */
        imagesavealpha(
            $resized,
            true
        );

        imagecopyresampled(
            $resized,
            $overlay,
            0,
            0,
            0,
            0,
            $targetWidth,
            $targetHeight,
            $sourceWidth,
            $sourceHeight
        );

        return $resized;
    }

    /**
     * Convierte una imagen PNG potencialmente transparente
     * en una imagen RGB adecuada para el JPEG final.
     *
     * El fondo utilizado es blanco.
     */
    private function flattenPng(
        GdImage $source
    ): GdImage {
        $width = imagesx($source);
        $height = imagesy($source);

        $background = imagecreatetruecolor(
            $width,
            $height
        );

        if ($background === false) {
            imagedestroy($source);

            throw new RuntimeException(
                'No se ha podido preparar la imagen PNG.'
            );
        }

        $white = imagecolorallocate(
            $background,
            255,
            255,
            255
        );

        imagefilledrectangle(
            $background,
            0,
            0,
            $width,
            $height,
            $white
        );

        imagealphablending(
            $background,
            true
        );

        imagecopy(
            $background,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height
        );

        imagedestroy($source);

        return $background;
    }

    /**
     * Comprueba que el directorio de imágenes generadas existe
     * y permite escritura al proceso PHP.
     */
    private function ensureGeneratedDirectory(): void
    {
        if (!is_dir(self::GENERATED_DIRECTORY)) {
            if (!mkdir(
                self::GENERATED_DIRECTORY,
                0775,
                true
            )) {
                throw new RuntimeException(
                    'No se ha podido crear el directorio de imágenes generadas.'
                );
            }
        }

        if (!is_writable(self::GENERATED_DIRECTORY)) {
            throw new RuntimeException(
                'El directorio de imágenes generadas no permite escritura.'
            );
        }
    }
}