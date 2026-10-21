<?php

declare(strict_types=1);

// Cargamos el controlador base de la aplicación.
require_once dirname(__DIR__) . '/Core/Controller.php';

class HomeController extends Controller
{
    /**
     * Muestra la página principal y la galería paginada.
     */
    public function index(): void
    {
        // Creamos el modelo con la conexión PDO.
        $imageModel = new Image(Database::connection());

        // Identificamos al usuario conectado, si existe.
        $currentUserId = Auth::check()
            ? Auth::userId()
            : null;

        /*
         * Definimos el tamaño de página.
         * Mostramos 6 imágenes, superando el mínimo exigido
         * de 5 elementos por página.
         */
        $imagesPerPage = 6;

        // Leemos el número de página solicitado.
        $requestedPage = filter_input(
            INPUT_GET,
            'page',
            FILTER_VALIDATE_INT,
            [
                'options' => [
                    'min_range' => 1,
                ],
            ]
        );

        // Si el parámetro falta o no es válido, usamos la página 1.
        $currentPage = $requestedPage === false
            || $requestedPage === null
            ? 1
            : $requestedPage;

        // Contamos las imágenes disponibles para la galería.
        $totalImages = $imageModel->countGalleryImages();

        // Calculamos el total de páginas, con un mínimo de una.
        $totalPages = max(
            1,
            (int) ceil($totalImages / $imagesPerPage)
        );

        // Evitamos solicitar una página posterior a la última.
        $currentPage = min($currentPage, $totalPages);

        // Calculamos cuántos registros hay que saltar.
        $offset = ($currentPage - 1) * $imagesPerPage;

        // Recuperamos únicamente las imágenes de esta página.
        $images = $imageModel->findForGallery(
            $imagesPerPage,
            $offset,
            $currentUserId
        );

        /*
         * Cargamos los comentarios de cada imagen mostrada.
         * Solo consultamos las imágenes de la página actual.
         */
        foreach ($images as &$image) {
            $image['comments'] = $imageModel->findCommentsForImage(
                (int) $image['id']
            );
        }

        // Eliminamos la referencia del último elemento.
        unset($image);

        // Generamos el token CSRF para los formularios.
        $csrfToken = Csrf::token();

        // Renderizamos la página con todos los datos necesarios.
        $this->render('home/index', [
            'title' => 'Camagru - Galería',
            'images' => $images,
            'currentUserId' => $currentUserId,
            'csrfToken' => $csrfToken,
            'currentPage' => $currentPage,
            'totalPages' => $totalPages,
            'totalImages' => $totalImages,
            'imagesPerPage' => $imagesPerPage,
        ]);
    }
}
