<?php

declare(strict_types=1);

// Cargamos el controlador base de la aplicación.
require_once dirname(__DIR__) . '/Core/Controller.php';

class HomeController extends Controller
{
    /**
     * Muestra la página de inicio y las últimas imágenes publicadas.
     */
    public function index(): void
    {
        // Obtenemos la conexión PDO y se la pasamos al modelo Image.
        $imageModel = new Image(Database::connection());

        // Mostramos como máximo las 20 imágenes más recientes.
        $images = $imageModel->findForGallery(20, 0);

        // Enviamos los datos a la vista de inicio.
        $this->render('home/index', [
            'title' => 'Camagru - Galería',
            'images' => $images,
        ]);
    }
}
