<?php

declare(strict_types=1);

/**
 * Controlador encargado de coordinar la creación de imágenes.
 *
 * ImageService valida y almacena la imagen original.
 * ImageProcessor aplica el overlay y genera el JPEG final.
 * Image guarda los datos de la imagen en MariaDB.
 */
class ImageController extends Controller
{
    /**
     * Overlay que se aplicará por defecto a cada fotografía.
     */
    private const DEFAULT_OVERLAY = 'neon-frame.png';

    /**
     * Muestra el formulario de creación de imágenes.
     */
    public function showCreate(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }
        
        // Obtenemos los overlays PNG disponibles en el servidor.
        $overlayDirectory = dirname(__DIR__, 2)
            . '/storage/overlays';

        $overlays = [];

        if (is_dir($overlayDirectory)) {
            $overlayFiles = glob(
                $overlayDirectory . '/*.png'
            ) ?: [];

            foreach ($overlayFiles as $overlayPath) {
                if (
                    is_file($overlayPath)
                    && is_readable($overlayPath)
                ) {
                    // La vista recibe el nombre, no una ruta del servidor.
                    $overlays[] = basename($overlayPath);
                }
            }

            // Orden estable para que el selector sea predecible.
            sort($overlays, SORT_NATURAL | SORT_FLAG_CASE);
        }

        $this->render('gallery/create', [
            'csrfToken' => Csrf::token(),
            'overlays' => $overlays,
        ]);
    }

    /**
     * Recibe una fotografía, aplica el overlay y guarda el resultado.
     */
    public function create(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
            return;
        }

        // Comprobamos el token CSRF antes de procesar archivos.
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);
            echo 'Solicitud no válida.';
            return;
        }

        $uploadedFile = $this->getUploadedFile();

        if ($uploadedFile === null) {
            $this->renderCreateWithError(
                'No se ha seleccionado ni capturado ninguna imagen.'
            );
            return;
        }


        // Leemos el overlay elegido en el formulario.
        $selectedOverlay = $_POST['overlay'] ?? '';

        // Comprobamos que el valor recibido sea una cadena.
        if (!is_string($selectedOverlay)) {
            $this->renderCreateWithError(
                'El overlay seleccionado no es válido.'
            );
            return;
        }

        // Obtenemos los nombres de los overlays disponibles.
        $overlayDirectory = dirname(__DIR__, 2)
            . '/storage/overlays';

        $overlayFiles = glob(
            $overlayDirectory . '/*.png'
        ) ?: [];

        $availableOverlays = array_map(
            'basename',
            $overlayFiles
        );

        // Rechazamos cualquier nombre que no esté disponible.
        if (
            $selectedOverlay !== ''
            && !in_array(
                $selectedOverlay,
                $availableOverlays,
                true
            )
        ) {
            $this->renderCreateWithError(
                'El overlay seleccionado no es válido.'
            );
            return;
        }


        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/login');
            return;
        }

        /*
         * Inicializamos los resultados para poder limpiar
         * los archivos si una fase posterior falla.
         */
        $uploadedImage = null;
        $generatedImage = null;
        $imageModel = null;
        $imageId = null;

        try {
            // 1. Validar y almacenar la fotografía original.
            $imageService = new ImageService();
            $uploadedImage = $imageService->upload($uploadedFile);

            // 2. Aplicar el overlay y generar el JPEG final.
            $imageProcessor = new ImageProcessor();

            $generatedImage = $imageProcessor->process(
                $uploadedImage['path'],
                $selectedOverlay
            );

            // 3. Preparar el modelo y guardar el registro.
            $imageModel = new Image(Database::connection());

            $imageId = $imageModel->create(
                $userId,
                $uploadedImage['filename'],
                $uploadedImage['mime_type'],
                $uploadedImage['width'],
                $uploadedImage['height']
            );

            // 4. Vincular el JPEG generado al registro de MariaDB.
            $imageModel->setGeneratedFilename(
                $imageId,
                $generatedImage['filename']
            );

            // 5. Mostrar la confirmación solo si todo ha funcionado.
            $this->render('gallery/create', [
                'csrfToken' => Csrf::token(),
                'success' => 'La imagen se ha creado correctamente.',
                'imageId' => $imageId,
            ]);
        } catch (RuntimeException $exception) {
            // Los errores controlados se pueden mostrar al usuario.
            $this->cleanupCreation(
                $uploadedImage,
                $generatedImage,
                $imageModel,
                $imageId
            );

            $this->renderCreateWithError(
                $exception->getMessage()
            );
        } catch (Throwable $exception) {
            /*
             * Los errores internos se registran en el log.
             * No mostramos detalles de base de datos o del servidor.
             */
            error_log(
                'Error al crear una imagen: ' . $exception->getMessage()
            );

            $this->cleanupCreation(
                $uploadedImage,
                $generatedImage,
                $imageModel,
                $imageId
            );

            $this->renderCreateWithError(
                'No se ha podido crear la imagen. Inténtalo de nuevo.'
            );
        }
    }

    /**
     * Busca la imagen enviada desde el formulario o la webcam.
     *
     * @return array<string, mixed>|null
     */
    private function getUploadedFile(): ?array
    {
        foreach (['camera_image', 'image'] as $field) {
            if (
                isset($_FILES[$field])
                && is_array($_FILES[$field])
                && isset($_FILES[$field]['error'])
                && $_FILES[$field]['error'] !== UPLOAD_ERR_NO_FILE
            ) {
                return $_FILES[$field];
            }
        }

        return null;
    }

    /**
     * Intenta revertir los cambios si falla la creación.
     *
     * Solo elimina archivos devueltos por los servicios de esta
     * operación y un registro cuyo ID se haya obtenido correctamente.
     */
    private function cleanupCreation(
        ?array $uploadedImage,
        ?array $generatedImage,
        ?Image $imageModel,
        ?int $imageId
    ): void {
        if ($imageId !== null && $imageModel !== null) {
            try {
                $imageModel->delete($imageId);
            } catch (Throwable $exception) {
                error_log(
                    'No se pudo eliminar el registro de imagen: '
                    . $exception->getMessage()
                );
            }
        }

        foreach ([$generatedImage, $uploadedImage] as $image) {
            if (
                is_array($image)
                && isset($image['path'])
                && is_string($image['path'])
                && is_file($image['path'])
            ) {
                if (!@unlink($image['path'])) {
                    error_log(
                        'No se pudo eliminar un archivo de una creación fallida.'
                    );
                }
            }
        }
    }

    /**
     * Vuelve a mostrar el formulario con un mensaje de error.
     */
    private function renderCreateWithError(string $error): void
    {
        $this->render('gallery/create', [
            'csrfToken' => Csrf::token(),
            'error' => $error,
        ]);
    }

    /**
     * Sirve una imagen generada que esté registrada en MariaDB.
     *
     * No permite acceder a las imágenes originales de storage/uploads.
     */
    public function showGenerated(): void
    {
        $filename = $_GET['filename'] ?? '';

        // Solo aceptamos nombres aleatorios con extensión JPEG.
        if (
            !is_string($filename)
            || !preg_match('/\A[a-f0-9]{64}\.jpg\z/', $filename)
        ) {
            http_response_code(404);
            echo 'Imagen no encontrada.';
            return;
        }

        try {
            $database = Database::connection();

            $statement = $database->prepare(
                'SELECT generated_filename
                FROM images
                WHERE generated_filename = :filename
                LIMIT 1'
            );

            $statement->execute([
                'filename' => $filename,
            ]);

            $image = $statement->fetch(PDO::FETCH_ASSOC);

            if (!$image) {
                http_response_code(404);
                echo 'Imagen no encontrada.';
                return;
            }

            $path = dirname(__DIR__, 2)
                . '/storage/generated/'
                . $filename;

            if (!is_file($path) || !is_readable($path)) {
                http_response_code(404);
                echo 'Imagen no encontrada.';
                return;
            }

            header('Content-Type: image/jpeg');
            header('Content-Length: ' . (string) filesize($path));
            header('X-Content-Type-Options: nosniff');
            header('Cache-Control: public, max-age=86400');

            readfile($path);
        } catch (Throwable $exception) {
            error_log(
                'Error al servir una imagen generada: '
                . $exception->getMessage()
            );

            http_response_code(500);
            echo 'No se ha podido cargar la imagen.';
        }
    }
}
