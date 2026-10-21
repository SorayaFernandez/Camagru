<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Controller.php';

/**
 * Gestiona las interacciones de las publicaciones de Camagru.
 *
 * Centraliza los "Me gusta" y los comentarios, validando
 * la sesión, el token CSRF y los datos recibidos.
 */
class InteractionController extends Controller
{
    /**
     * Añade o elimina el "Me gusta" del usuario actual.
     */
    public function toggleLike(): void
    {
        // Solo los usuarios autenticados pueden interactuar.
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        // Verificamos la protección CSRF antes de modificar datos.
        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Solicitud no válida.';
            return;
        }

        $userId = Auth::userId();
        $imageId = filter_input(
            INPUT_POST,
            'image_id',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        if ($userId === null || $imageId === false || $imageId === null) {
            http_response_code(400);
            echo 'Datos de solicitud no válidos.';
            return;
        }

        $imageModel = new Image(Database::connection());
        $image = $imageModel->findById($imageId);

        // No permitimos interacciones con imágenes inexistentes
        // o que todavía no se han publicado en la galería.
        if (
            $image === null
            || !is_string($image['generated_filename'] ?? null)
            || preg_match(
                '/\A[a-f0-9]{64}\.jpg\z/',
                $image['generated_filename']
            ) !== 1
        ) {
            http_response_code(404);
            echo 'Imagen no encontrada.';
            return;
        }

        // Alternamos el estado del "Me gusta".
        if ($imageModel->hasUserLiked($imageId, $userId)) {
            $imageModel->removeLike($imageId, $userId);
        } else {
            $imageModel->addLike($imageId, $userId);
        }

        // Volvemos a la galería, conservando la página de origen.
        $this->redirectToImage($imageId);
    }

    /**
     * Guarda un comentario en una publicación.
     */
    public function addComment(): void
    {
        if (!Auth::check()) {
            $this->redirect('/login');
        }

        if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
            http_response_code(403);
            echo 'Solicitud no válida.';
            return;
        }

        $userId = Auth::userId();
        $imageId = filter_input(
            INPUT_POST,
            'image_id',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );
        $content = $_POST['content'] ?? null;

        if (
            $userId === null
            || $imageId === false
            || $imageId === null
            || !is_string($content)
        ) {
            http_response_code(400);
            echo 'Datos de solicitud no válidos.';
            return;
        }

        $content = trim($content);

        // Validamos que el comentario no esté vacío y no exceda
        // el límite del formulario, contando caracteres UTF-8.
        if (
            $content === ''
            || preg_match('//u', $content) !== 1
            || mb_strlen($content, 'UTF-8') > 1000
        ) {
            http_response_code(400);
            echo 'El comentario debe contener entre 1 y 1000 caracteres válidos.';
            return;
        }

        $imageModel = new Image(Database::connection());
        $image = $imageModel->findById($imageId);

        if (
            $image === null
            || !is_string($image['generated_filename'] ?? null)
            || preg_match(
                '/\A[a-f0-9]{64}\.jpg\z/',
                $image['generated_filename']
            ) !== 1
        ) {
            http_response_code(404);
            echo 'Imagen no encontrada.';
            return;
        }

        $imageModel->addComment($imageId, $userId, $content);

        $this->redirectToImage($imageId);
    }

    /**
     * Vuelve a la página de la galería desde la que se interactuó.
     *
     * El número de página se valida antes de utilizarlo.
     * El ID de la imagen también procede de un entero validado.
     */
    private function redirectToImage(int $imageId): void
    {
        $page = filter_input(
            INPUT_POST,
            'page',
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]
        );

        $page = $page === false || $page === null ? 1 : $page;

        $this->redirect(
            '/?page=' . $page . '#image-' . $imageId
        );
    }
}
