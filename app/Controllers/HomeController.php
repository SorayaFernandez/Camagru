<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/Core/Controller.php';

class HomeController extends Controller
{
    public function index(): void
    {
        $this->render('home/index', [
            'title' => 'Home - Camagru',
        ]);
    }
}