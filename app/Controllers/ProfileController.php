<?php

declare(strict_types=1);

/**
 * ProfileController
 *
 * Gestiona el perfil del usuario autenticado.
 *
 * Funcionalidades:
 *
 * - Mostrar los datos actuales del usuario.
 * - Modificar username.
 * - Modificar email.
 * - Modificar contraseña.
 *
 * Todas las modificaciones requieren:
 *
 * - Usuario autenticado.
 * - Petición POST.
 * - Token CSRF válido.
 */
class ProfileController extends Controller
{
    /**
     * Muestra el perfil del usuario autenticado.
     */
    public function show(): void
    {
        /*
         * Comprobamos que exista una sesión autenticada.
         */
        if (!Auth::check()) {
            $this->redirect('/login');

            return;
        }

        /*
         * Obtenemos el ID almacenado en la sesión.
         */
        $userId = Auth::userId();

        /*
         * Auth::userId() puede devolver null.
         *
         * Aunque Auth::check() haya devuelto true, mantenemos
         * esta comprobación para trabajar con tipos estrictos.
         */
        if ($userId === null) {
            $this->redirect('/login');

            return;
        }

        /*
         * User utiliza métodos de instancia, por lo que creamos
         * una instancia del modelo.
         */
        $userModel = new User();

        /*
         * Recuperamos los datos del usuario.
         */
        $user = $userModel->findById($userId);

        /*
         * Si la cuenta ya no existe, eliminamos la sesión.
         */
        if ($user === null) {
            Auth::logout();

            $this->redirect('/login');

            return;
        }

        /*
         * Generamos el token CSRF para los formularios del perfil.
         */
        $csrfToken = Csrf::token();

        /*
         * Renderizamos la página.
         */
        $this->render(
            'auth/profile',
            [
                'csrfToken' => $csrfToken,
                'user' => $user,
                'errors' => [],
                'success' => null,
                'old' => [],
            ]
        );
    }

        /**
     * Actualiza username y correo electrónico.
     *
     * Si el usuario cambia su correo:
     * - Generamos un nuevo token de confirmación.
     * - Guardamos únicamente el hash del token.
     * - La cuenta pasa a quedar pendiente de confirmación.
     * - Enviamos un correo al nuevo email.
     * - Cerramos la sesión actual.
     */
    public function updateAccount(): void
    {
        /*
         * Solo un usuario autenticado puede modificar
         * los datos de su propia cuenta.
         */
        if (!Auth::check()) {
            $this->redirect('/login');

            return;
        }

        /*
         * Validamos el token CSRF para evitar que otra página
         * pueda realizar cambios en la cuenta del usuario.
         */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $this->renderProfileError(
                'La solicitud no es válida. Inténtalo de nuevo.'
            );

            return;
        }

        /*
         * Obtenemos el ID del usuario actualmente autenticado.
         */
        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/login');

            return;
        }

        /*
         * Creamos el modelo de usuario.
         */
        $userModel = new User();

        /*
         * Recuperamos los datos actuales de la cuenta.
         */
        $user = $userModel->findById($userId);

        if ($user === null) {
            /*
             * Si la sesión apunta a un usuario que ya no existe,
             * destruimos la sesión para evitar mantener un estado
             * de autenticación inválido.
             */
            Auth::logout();

            $this->redirect('/login');

            return;
        }

        /*
         * Recuperamos los datos enviados desde el formulario.
         */
        $username = trim(
            (string) ($_POST['username'] ?? '')
        );

        $email = trim(
            (string) ($_POST['email'] ?? '')
        );

        /*
         * Validamos el username utilizando las mismas reglas
         * utilizadas durante el registro.
         */
        if (!Validator::username($username)) {
            $this->renderProfileError(
                'El nombre de usuario debe tener entre 3 y 50 caracteres y solo puede contener letras, números y guiones bajos.',
                [
                    'username' => $username,
                    'email' => $email,
                ]
            );

            return;
        }

        /*
         * Validamos el correo electrónico.
         */
        if (!Validator::email($email)) {
            $this->renderProfileError(
                'Introduce un correo electrónico válido.',
                [
                    'username' => $username,
                    'email' => $email,
                ]
            );

            return;
        }

        /*
         * Normalizamos el correo electrónico para mantener
         * un formato consistente en la base de datos.
         */
        $email = strtolower($email);

        /*
         * Comprobamos que el nuevo username no pertenezca
         * a otra cuenta.
         */
        $existingUsername = $userModel->findByUsername($username);

        if (
            $existingUsername !== null
            && (int) $existingUsername['id'] !== $userId
        ) {
            $this->renderProfileError(
                'Ese nombre de usuario ya está en uso.',
                [
                    'username' => $username,
                    'email' => $email,
                ]
            );

            return;
        }

        /*
         * Comprobamos que el nuevo email no pertenezca
         * a otra cuenta.
         */
        $existingEmail = $userModel->findByEmail($email);

        if (
            $existingEmail !== null
            && (int) $existingEmail['id'] !== $userId
        ) {
            $this->renderProfileError(
                'Ese correo electrónico ya está en uso.',
                [
                    'username' => $username,
                    'email' => $email,
                ]
            );

            return;
        }

        /*
         * Obtenemos el email que tiene actualmente la cuenta.
         *
         * Lo normalizamos también para que la comparación
         * no dependa de mayúsculas/minúsculas.
         */
        $currentEmail = strtolower(
            trim((string) $user['email'])
        );

        /*
         * Comprobamos si realmente ha cambiado el email.
         */
        $emailChanged = $email !== $currentEmail;

        if (!$emailChanged) {
            /*
             * El email no ha cambiado.
             *
             * Solo actualizamos username y email mediante
             * el método normal de actualización.
             */
            $userModel->updateAccount(
                $userId,
                $username,
                $email
            );

            $this->renderProfileSuccess(
                'Los datos de tu cuenta se han actualizado correctamente.'
            );

            return;
        }

        /*
         * El email sí ha cambiado.
         *
         * Generamos un token criptográficamente seguro.
         *
         * El token original se enviará por correo, pero nunca
         * se almacenará directamente en la base de datos.
         */
        $confirmationToken = bin2hex(
            random_bytes(32)
        );

        /*
         * Guardamos únicamente el hash SHA-256 del token.
         */
        $confirmationTokenHash = hash(
            'sha256',
            $confirmationToken
        );

        /*
         * El enlace de confirmación será válido durante 24 horas.
         */
        $confirmationExpiresAt = date(
            'Y-m-d H:i:s',
            time() + 86400
        );

        /*
         * Actualizamos el email y dejamos la cuenta pendiente
         * de confirmación.
         */
        $userModel->updateEmailWithConfirmation(
            $userId,
            $email,
            $confirmationTokenHash,
            $confirmationExpiresAt
        );

        /*
         * Cargamos la configuración de la aplicación para
         * construir el enlace absoluto de confirmación.
         */
        require_once dirname(__DIR__, 2) . '/config/config.php';

        $appUrl = rtrim(
            env('APP_URL', 'http://localhost:8080'),
            '/'
        );

        $confirmationUrl = $appUrl
            . '/confirm?token='
            . urlencode($confirmationToken);

        /*
         * Cargamos el servicio encargado del envío de correo.
         */
        require_once dirname(__DIR__) . '/Services/MailService.php';

        /*
         * Preparamos la plantilla HTML del correo.
         *
         * Reutilizamos la misma plantilla que utilizamos
         * durante el registro para mantener un flujo uniforme.
         */
        $templateData = [
            'username' => $username,
            'confirmationUrl' => $confirmationUrl,
        ];

        /*
         * Generamos el contenido HTML del correo.
         */
        ob_start();

        try {
            extract($templateData);

            require dirname(__DIR__) . '/Views/emails/confirmation.php';

            $htmlBody = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();

            /*
             * Registramos el error técnico en los logs,
             * pero no mostramos información interna al usuario.
             */
            error_log(
                'Profile email confirmation template error: '
                . $exception->getMessage()
            );

            $this->renderProfileError(
                'No se ha podido preparar el correo de confirmación.'
            );

            return;
        }

        /*
         * Generamos también la versión de texto plano.
         *
         * Esto mejora la compatibilidad con clientes de correo
         * que no muestran HTML.
         */
        ob_start();

        try {
            extract($templateData);

            require dirname(__DIR__) . '/Views/emails/confirmation-text.php';

            $textBody = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();

            error_log(
                'Profile email confirmation text template error: '
                . $exception->getMessage()
            );

            $this->renderProfileError(
                'No se ha podido preparar el correo de confirmación.'
            );

            return;
        }

        /*
         * Enviamos el correo utilizando nuestro servicio SMTP.
         */
        try {
            $mailService = new MailService();

            $mailService->send(
                $email,
                'Confirma tu nueva dirección de correo - Camagru',
                $htmlBody,
                $textBody
            );
        } catch (Throwable $exception) {
            /*
             * El cambio ya se ha guardado en la base de datos.
             *
             * Si el correo falla, registramos el error técnico
             * y mostramos un mensaje genérico.
             */
            error_log(
                'Profile confirmation email error: '
                . $exception->getMessage()
            );

            $this->renderProfileError(
                'No se ha podido enviar el correo de confirmación. Inténtalo de nuevo más tarde.'
            );

            return;
        }

        /*
         * Cerramos la sesión porque la cuenta ahora está pendiente
         * de confirmar el nuevo correo electrónico.
         *
         * El usuario podrá volver a iniciar sesión después
         * de confirmar la nueva dirección.
         */
        Auth::logout();

        /*
         * Mostramos una página de éxito.
         *
         * Como hemos cerrado la sesión, no utilizamos
         * renderProfileSuccess(), que requiere un usuario autenticado.
         */
        $this->render(
            'auth/email-change-success'
        );
    }

    /**
     * Cambia la contraseña del usuario autenticado.
     */
    public function updatePassword(): void
    {
        /*
         * Comprobamos autenticación.
         */
        if (!Auth::check()) {
            $this->redirect('/login');

            return;
        }

        /*
         * Comprobamos CSRF.
         */
        if (!Csrf::verify($_POST['csrf_token'] ?? '')) {
            $this->renderProfileError(
                'La solicitud no es válida. Inténtalo de nuevo.'
            );

            return;
        }

        /*
         * Obtenemos los datos enviados.
         *
         * La contraseña nunca se almacena en variables de sesión
         * ni se vuelve a mostrar al usuario.
         */
        $currentPassword = (string) (
            $_POST['current_password'] ?? ''
        );

        $newPassword = (string) (
            $_POST['new_password'] ?? ''
        );

        $confirmPassword = (string) (
            $_POST['confirm_password'] ?? ''
        );

        /*
         * Obtenemos el usuario actual.
         */
        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/login');

            return;
        }

        $userModel = new User();

        $user = $userModel->findById($userId);

        if ($user === null) {
            Auth::logout();

            $this->redirect('/login');

            return;
        }

        /*
         * Verificamos la contraseña actual contra su hash.
         */
        if (
            !password_verify(
                $currentPassword,
                (string) $user['password_hash']
            )
        ) {
            $this->renderProfileError(
                'La contraseña actual no es correcta.'
            );

            return;
        }

        /*
        * Validamos la nueva contraseña con las mismas reglas
        * utilizadas durante el registro.
        */
        if (!Validator::password($newPassword)) {
            $this->renderProfileError(
                'La contraseña debe tener al menos 8 caracteres, una mayúscula, una minúscula y un número.'
            );

            return;
        }

        /*
         * Comprobamos que ambas nuevas contraseñas coincidan.
         */
        if ($newPassword !== $confirmPassword) {
            $this->renderProfileError(
                'Las nuevas contraseñas no coinciden.'
            );

            return;
        }

        /*
         * Generamos un nuevo hash seguro.
         *
         * Nunca almacenamos la contraseña original.
         */
        $passwordHash = password_hash(
            $newPassword,
            PASSWORD_DEFAULT
        );

        /*
         * Guardamos el nuevo hash.
         */
        $userModel->updatePassword(
            $userId,
            $passwordHash
        );

        /*
         * Mantenemos la sesión actual.
         */
        $this->renderProfileSuccess(
            'Tu contraseña se ha actualizado correctamente.'
        );
    }

    /**
     * Renderiza el perfil mostrando un error.
     *
     * @param string $message Mensaje de error.
     * @param array<string, string> $old Valores antiguos del formulario.
     */
    private function renderProfileError(
        string $message,
        array $old = []
    ): void {
        /*
         * Recuperamos el usuario actual.
         */
        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/login');

            return;
        }

        $userModel = new User();

        $user = $userModel->findById($userId);

        if ($user === null) {
            Auth::logout();

            $this->redirect('/login');

            return;
        }

        /*
         * Si no recibimos valores anteriores, mostramos
         * los datos actuales de la cuenta.
         */
        if ($old === []) {
            $old = [
                'username' => (string) $user['username'],
                'email' => (string) $user['email'],
            ];
        }

        /*
         * Renderizamos la vista con el mensaje de error.
         */
        $this->render(
            'auth/profile',
            [
                'csrfToken' => Csrf::token(),
                'user' => $user,
                'errors' => [
                    'general' => $message,
                ],
                'success' => null,
                'old' => $old,
            ]
        );
    }

    /**
     * Renderiza el perfil mostrando un mensaje de éxito.
     */
    private function renderProfileSuccess(
        string $message
    ): void {
        /*
         * Obtenemos nuevamente los datos después de la actualización.
         */
        $userId = Auth::userId();

        if ($userId === null) {
            $this->redirect('/login');

            return;
        }

        $userModel = new User();

        $user = $userModel->findById($userId);

        if ($user === null) {
            Auth::logout();

            $this->redirect('/login');

            return;
        }

        /*
         * Mostramos el perfil actualizado.
         */
        $this->render(
            'auth/profile',
            [
                'csrfToken' => Csrf::token(),
                'user' => $user,
                'errors' => [],
                'success' => $message,
                'old' => [
                    'username' => (string) $user['username'],
                    'email' => (string) $user['email'],
                ],
            ]
        );
    }
}