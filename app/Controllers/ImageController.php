<?php

declare(strict_types=1);

/**
 * Controlador encargado de la creación de imágenes.
 *
 * Este controlador coordina:
 *
 * - la autenticación del usuario;
 * - la protección CSRF;
 * - la recepción de imágenes;
 * - la validación mediante ImageService;
 * - el almacenamiento del archivo;
 * - la creación del registro en MariaDB.
 *
 * La validación de archivos pertenece a ImageService.
 * El acceso a MariaDB pertenece al modelo Image.
 */
class ImageController extends Controller
{
    /**
     * Muestra la página definitiva para crear una imagen.
     *
     * El usuario puede elegir entre:
     *
     * - subir una imagen desde su dispositivo;
     * - utilizar la webcam.
     */
    public function showCreate(): void
    {
        /*
         * Solamente los usuarios autenticados pueden
         * acceder a la creación de imágenes.
         */
        if (!Auth::check()) {
            $this->redirect('/login');

            return;
        }

        /*
         * Generamos el token CSRF necesario para proteger
         * el formulario contra solicitudes externas.
         */
        $csrfToken = Csrf::token();

        /*
         * Mostramos la vista definitiva de creación.
         */
        $this->render('gallery/create', [
            'csrfToken' => $csrfToken,
        ]);
    }

    /**
     * Procesa una imagen subida desde el dispositivo
     * o capturada mediante la webcam.
     */
    public function create(): void
    {
        /*
         * Comprobamos que el usuario esté autenticado.
         */
        if (!Auth::check()) {
            $this->redirect('/login');

            return;
        }

        /*
         * Verificamos el token CSRF antes de procesar
         * cualquier archivo enviado por el navegador.
         */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            http_response_code(403);

            echo 'Solicitud no válida.';

            return;
        }

        /*
         * Determinamos qué tipo de imagen ha enviado
         * el navegador.
         *
         * La vista utiliza:
         *
         * - "image" para archivos seleccionados;
         * - "camera_image" para capturas de webcam.
         */
        $uploadedFile = $this->getUploadedFile();

        /*
         * Si no existe ningún archivo válido, mostramos
         * un mensaje controlado al usuario.
         */
        if ($uploadedFile === null) {
            $this->renderCreateWithError(
                'No se ha seleccionado ni capturado ninguna imagen.'
            );

            return;
        }

        try {
            /*
             * ImageService realiza las comprobaciones de seguridad:
             *
             * - tamaño;
             * - MIME real;
             * - formato;
             * - dimensiones;
             * - número de píxeles;
             * - nombre aleatorio;
             * - almacenamiento seguro.
             */
            $imageService = new ImageService();

            $uploadedImage = $imageService->upload($uploadedFile);

            /*
             * Obtenemos el ID del usuario autenticado.
             */
            $userId = Auth::userId();

            /*
             * Esta comprobación es defensiva.
             */
            if ($userId === null) {
                $this->redirect('/login');

                return;
            }

            /*
             * Creamos el modelo Image utilizando la conexión
             * centralizada de la aplicación.
             */
            $imageModel = new Image(
                Database::connection()
            );

            /*
             * Creamos el registro de la imagen en MariaDB.
             *
             * Todavía no existe generated_filename porque
             * la fase de composición con overlays llegará
             * posteriormente.
             */
            $imageId = $imageModel->create(
                $userId,
                $uploadedImage['filename'],
                $uploadedImage['mime_type'],
                $uploadedImage['width'],
                $uploadedImage['height']
            );

            /*
             * Guardamos temporalmente el ID en una variable
             * que utilizaremos en la siguiente fase del flujo.
             *
             * En este punto ya tenemos:
             *
             * - archivo validado;
             * - archivo almacenado;
             * - registro creado en MariaDB.
             */
            $this->render('gallery/create', [
                'csrfToken' => Csrf::token(),
                'success' => 'La imagen se ha subido correctamente.',
                'imageId' => $imageId,
            ]);
        } catch (RuntimeException $exception) {
            /*
             * Mostramos solamente errores controlados.
             */
            $this->renderCreateWithError(
                $exception->getMessage()
            );
        }
    }

    /**
     * Determina qué archivo ha enviado el navegador.
     *
     * La vista utiliza dos campos:
     *
     * - image: subida desde dispositivo;
     * - camera_image: captura de webcam.
     *
     * @return array<string, mixed>|null
     */
    private function getUploadedFile(): ?array
    {
        /*
         * Primero comprobamos si existe una captura
         * procedente de la webcam.
         */
        if (
            isset($_FILES['camera_image'])
            && is_array($_FILES['camera_image'])
            && isset($_FILES['camera_image']['error'])
            && $_FILES['camera_image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            return $_FILES['camera_image'];
        }

        /*
         * Si no hay captura de webcam, comprobamos
         * la subida normal desde el dispositivo.
         */
        if (
            isset($_FILES['image'])
            && is_array($_FILES['image'])
            && isset($_FILES['image']['error'])
            && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
        ) {
            return $_FILES['image'];
        }

        /*
         * No se ha recibido ninguna imagen.
         */
        return null;
    }

    /**
     * Vuelve a mostrar la página de creación con un error.
     */
    private function renderCreateWithError(string $error): void
    {
        /*
         * Generamos un token CSRF para el formulario
         * que se vuelve a mostrar.
         */
        $csrfToken = Csrf::token();

        $this->render('gallery/create', [
            'csrfToken' => $csrfToken,
            'error' => $error,
        ]);
    }
}