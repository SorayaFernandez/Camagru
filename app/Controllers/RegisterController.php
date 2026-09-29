<?php

declare(strict_types=1);

/**
 * RegisterController
 *
 * Gestiona:
 *
 * - Mostrar el formulario de registro.
 * - Procesar el registro.
 * - Validar el token CSRF.
 * - Crear el usuario mediante AuthService.
 * - Enviar el correo de confirmación mediante MailService.
 */
class RegisterController extends Controller
{
    /**
     * Muestra el formulario de registro.
     */
    public function show(): void
    {
        /*
         * Generamos un token CSRF para proteger el formulario
         * frente a peticiones POST no autorizadas.
         */
        $csrfToken = Csrf::getToken();

        /*
         * Mostramos la vista de registro.
         */
        $this->render(
            'auth/register',
            [
                'csrfToken' => $csrfToken,
                'errors' => [],
                'old' => [],
            ]
        );
    }

    /**
     * Procesa el formulario de registro.
     */
    public function register(): void
    {
        /*
         * Comprobamos primero el token CSRF.
         *
         * Si el token no es válido, rechazamos inmediatamente
         * la petición para evitar ataques CSRF.
         */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $this->render(
                'auth/register',
                [
                    'csrfToken' => Csrf::getToken(),
                    'errors' => [
                        'general' => 'La solicitud no es válida. Inténtalo de nuevo.',
                    ],
                    'old' => [],
                ]
            );

            return;
        }

        /*
         * Recuperamos los datos enviados por el formulario.
         *
         * No confiamos en los valores recibidos del navegador.
         * AuthService realizará las validaciones correspondientes.
         */
        $username = (string) ($_POST['username'] ?? '');
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');

        /*
         * Guardamos los valores no sensibles para poder
         * volver a mostrarlos si se produce un error.
         *
         * La contraseña NUNCA se vuelve a mostrar.
         */
        $old = [
            'username' => $username,
            'email' => $email,
        ];

        /*
         * Creamos el servicio encargado de registrar usuarios.
         */
        $authService = new AuthService();

        try {
            /*
             * AuthService se encarga de:
             *
             * - validar los datos;
             * - comprobar username/email existentes;
             * - generar el hash de la contraseña;
             * - generar el token de confirmación;
             * - guardar el usuario en la base de datos.
             */
            $result = $authService->register(
                $username,
                $email,
                $password
            );

            /*
             * Si AuthService indica que el registro no es válido,
             * mostramos los errores al usuario.
             */
            if (($result['success'] ?? false) !== true) {
                $this->render(
                    'auth/register',
                    [
                        'csrfToken' => Csrf::getToken(),
                        'errors' => $result['errors'] ?? [
                            'general' => 'No se pudo completar el registro.',
                        ],
                        'old' => $old,
                    ]
                );

                return;
            }

            /*
             * Recuperamos la información necesaria para construir
             * el correo de confirmación.
             *
             * AuthService devuelve el token original solamente
             * durante este proceso.
             *
             * En la base de datos solamente se almacena su hash.
             */
            $confirmationToken = (string) (
                $result['confirmation_token'] ?? ''
            );

            $registeredUsername = (string) (
                $result['username'] ?? $username
            );

            $registeredEmail = (string) (
                $result['email'] ?? $email
            );

            /*
             * Comprobamos que exista el token.
             *
             * Sin él no podríamos construir un enlace de confirmación
             * válido para el usuario recién creado.
             */
            if ($confirmationToken === '') {
                throw new RuntimeException(
                    'No se pudo generar el token de confirmación.'
                );
            }

            /*
             * Recuperamos la URL base de la aplicación desde .env.
             *
             * Ejemplo:
             *
             * APP_URL=http://localhost:8080
             */
            require_once __DIR__ . '/../../config/config.php';

            $appUrl = rtrim(
                env('APP_URL', 'http://localhost:8080'),
                '/'
            );

            /*
             * Construimos la URL que recibirá el usuario.
             *
             * rawurlencode() garantiza que el token pueda viajar
             * correctamente dentro del parámetro de la URL.
             */
            $confirmationUrl = $appUrl
                . '/confirm?token='
                . rawurlencode($confirmationToken);

            /*
             * ---------------------------------------------------------
             * Generamos la versión HTML del correo.
             * ---------------------------------------------------------
             *
             * Utilizamos output buffering para cargar la plantilla PHP
             * y capturar el HTML resultante como una cadena.
             */
            $htmlBody = $this->renderEmailTemplate(
                'confirmation',
                [
                    'username' => $registeredUsername,
                    'confirmationUrl' => $confirmationUrl,
                ]
            );

            /*
             * ---------------------------------------------------------
             * Generamos la versión de texto plano.
             * ---------------------------------------------------------
             */
            $textBody = $this->renderEmailTemplate(
                'confirmation-text',
                [
                    'username' => $registeredUsername,
                    'confirmationUrl' => $confirmationUrl,
                ]
            );

            /*
             * Creamos el servicio SMTP.
             *
             * MailService obtiene automáticamente las credenciales
             * desde las variables de entorno configuradas en .env.
             */
            $mailService = new MailService();

            /*
             * Enviamos el correo de confirmación.
             */
            $mailService->send(
                $registeredEmail,
                'Confirma tu cuenta de Camagru',
                $htmlBody,
                $textBody
            );

            /*
             * Si hemos llegado hasta aquí:
             *
             * - el usuario ha sido creado;
             * - el token ha sido generado;
             * - el correo ha sido aceptado por el servidor SMTP.
             *
             * Mostramos la página de registro completado.
             */
            $this->render(
                'auth/register-success',
                [
                    'email' => $registeredEmail,
                ]
            );
        } catch (Throwable $exception) {
            /*
             * No mostramos al usuario detalles internos del sistema,
             * credenciales, errores SQL ni información SMTP.
             *
             * El detalle técnico sí queda registrado en el log de PHP
             * para poder depurarlo durante el desarrollo.
             */
            error_log(
                'Error durante el registro de Camagru: '
                . $exception->getMessage()
            );

            /*
             * Mostramos un mensaje genérico.
             */
            $this->render(
                'auth/register',
                [
                    'csrfToken' => Csrf::getToken(),
                    'errors' => [
                        'general' =>
                            'No se pudo completar el registro. '
                            . 'Inténtalo de nuevo más tarde.',
                    ],
                    'old' => $old,
                ]
            );
        }
    }

    /**
     * Carga una plantilla de correo y devuelve su contenido.
     *
     * Utilizamos output buffering porque las plantillas de correo
     * son archivos PHP que generan HTML o texto directamente.
     *
     * @param string $template Nombre de la plantilla sin extensión.
     * @param array<string, mixed> $data Variables disponibles para la plantilla.
     *
     * @return string Contenido generado por la plantilla.
     */
    private function renderEmailTemplate(
        string $template,
        array $data
    ): string {
        /*
         * Construimos la ruta absoluta de la plantilla.
         */
        $templatePath = __DIR__
            . '/../Views/emails/'
            . $template
            . '.php';

        /*
         * Comprobamos que la plantilla exista antes de intentar
         * incluirla.
         */
        if (!is_file($templatePath)) {
            throw new RuntimeException(
                'No existe la plantilla de correo: ' . $template
            );
        }

        /*
         * Convertimos las claves del array en variables locales.
         *
         * Por ejemplo:
         *
         * ['username' => 'Soraya']
         *
         * permite utilizar $username dentro de la plantilla.
         */
        extract($data, EXTR_SKIP);

        /*
         * Empezamos a capturar la salida generada por el archivo PHP.
         */
        ob_start();

        try {
            /*
             * Ejecutamos la plantilla.
             */
            require $templatePath;

            /*
             * Recuperamos el contenido generado.
             */
            $content = ob_get_clean();

            /*
             * ob_get_clean() puede devolver false si el buffer
             * no se ha podido recuperar correctamente.
             */
            if ($content === false) {
                throw new RuntimeException(
                    'No se pudo generar la plantilla de correo.'
                );
            }

            return $content;
        } catch (Throwable $exception) {
            /*
             * Si se produce un error dentro de la plantilla,
             * limpiamos el buffer antes de propagar la excepción.
             */
            ob_end_clean();

            throw $exception;
        }
    }
}