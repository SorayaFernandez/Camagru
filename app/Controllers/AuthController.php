<?php

declare(strict_types=1);

/**
 * AuthController
 *
 * Gestiona las operaciones relacionadas con la autenticación:
 *
 * - Mostrar el formulario de login.
 * - Iniciar sesión.
 * - Cerrar sesión.
 */
class AuthController extends Controller
{
    /**
     * Muestra el formulario de inicio de sesión.
     */
    public function showLogin(): void
    {
        /*
         * Generamos el token CSRF que protegerá
         * el formulario de login.
         */
        $csrfToken = Csrf::token();

        /*
         * Renderizamos la vista.
         */
        $this->render(
            'auth/login',
            [
                'csrfToken' => $csrfToken,
                'errors' => [],
                'old' => [],
            ]
        );
    }

    /**
     * Procesa el formulario de inicio de sesión.
     */
    public function login(): void
    {
        /*
         * Comprobamos el token CSRF antes de procesar
         * cualquier dato de autenticación.
         */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $this->render(
                'auth/login',
                [
                    'csrfToken' => Csrf::token(),
                    'errors' => [
                        'general' =>
                            'La solicitud no es válida. Inténtalo de nuevo.',
                    ],
                    'old' => [],
                ]
            );

            return;
        }

        /*
         * Recuperamos únicamente los datos necesarios.
         *
         * La contraseña nunca se conserva para volver a mostrarla.
         */
        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        $password = (string) (
            $_POST['password'] ?? ''
        );

        /*
         * Conservamos el email para poder mostrarlo de nuevo
         * si las credenciales son incorrectas.
         */
        $old = [
            'email' => $email,
        ];

        /*
         * Validamos que ambos campos hayan sido introducidos.
         */
        if ($email === '' || $password === '') {
            $this->render(
                'auth/login',
                [
                    'csrfToken' => Csrf::token(),
                    'errors' => [
                        'general' =>
                            'Introduce tu correo electrónico y contraseña.',
                    ],
                    'old' => $old,
                ]
            );

            return;
        }

        /*
         * Buscamos el usuario por email.
         */
        $user = User::findForLogin($email);

        /*
         * Utilizamos un mensaje genérico para las credenciales
         * incorrectas.
         *
         * No indicamos si el email existe o no, evitando así
         * revelar información sobre las cuentas registradas.
         */
        if (
            $user === null
            || !password_verify(
                $password,
                (string) $user['password_hash']
            )
        ) {
            $this->render(
                'auth/login',
                [
                    'csrfToken' => Csrf::token(),
                    'errors' => [
                        'general' =>
                            'El correo electrónico o la contraseña no son correctos.',
                    ],
                    'old' => $old,
                ]
            );

            return;
        }

        /*
         * Una cuenta recién registrada debe confirmarse mediante
         * el enlace enviado por email antes de poder iniciar sesión.
         */
        if (!$user['is_confirmed']) {
            $this->render(
                'auth/login',
                [
                    'csrfToken' => Csrf::token(),
                    'errors' => [
                        'general' =>
                            'Debes confirmar tu cuenta mediante el correo electrónico antes de iniciar sesión.',
                    ],
                    'old' => $old,
                ]
            );

            return;
        }

        /*
         * Las credenciales son correctas y la cuenta está confirmada.
         *
         * Auth::login() se encarga de regenerar el identificador
         * de sesión para evitar session fixation.
         */
        Auth::login(
            (int) $user['id'],
            (string) $user['username']
        );

        /*
         * Una vez autenticado, enviamos al usuario a la página
         * principal de Camagru.
         */
        $this->redirect('/');
    }

    /**
     * Cierra la sesión del usuario actual.
     *
     * El logout utiliza POST y CSRF porque cerrar una sesión
     * modifica el estado de autenticación.
     */
    public function logout(): void
    {
        /*
        * Comprobamos el token CSRF antes de cerrar la sesión.
        *
        * De esta forma una página externa no puede provocar
        * un logout mediante una petición no autorizada.
        */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            /*
            * Si el token no es válido, no modificamos la sesión.
            */
            http_response_code(403);

            echo 'Solicitud no válida.';

            return;
        }

        /*
        * Eliminamos los datos de autenticación y destruimos
        * la sesión actual.
        */
        Auth::logout();

        /*
        * Volvemos a la página principal.
        */
        $this->redirect('/');
    }
}