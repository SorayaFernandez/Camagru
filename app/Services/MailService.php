<?php

declare(strict_types=1);

/**
 * MailService
 *
 * Cliente SMTP sencillo implementado utilizando únicamente
 * las funciones estándar de PHP.
 *
 * No utilizamos PHPMailer, Symfony Mailer ni ninguna otra librería
 * externa porque el proyecto Camagru debe funcionar con PHP estándar.
 *
 * El servicio se conecta directamente al servidor SMTP de Gmail
 * utilizando STARTTLS y autenticación SMTP.
 */
class MailService
{
    /**
     * Configuración SMTP cargada desde config/mail.php.
     */
    private array $config;

    /**
     * Socket utilizado para comunicarnos con el servidor SMTP.
     *
     * @var resource|null
     */
    private $socket = null;

    /**
     * Constructor.
     *
     * Cargamos la configuración SMTP desde las variables de entorno.
     */
    public function __construct()
    {
        require_once __DIR__ . '/../../config/mail.php';

        $this->config = getMailConfig();
    }

    /**
     * Envía un correo electrónico.
     *
     * @param string $to Dirección del destinatario.
     * @param string $subject Asunto del correo.
     * @param string $htmlBody Contenido HTML.
     * @param string $textBody Versión de texto plano.
     *
     * @return bool True si el envío termina correctamente.
     *
     * @throws RuntimeException Si se produce un error SMTP.
     */
    public function send(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): bool {
        /*
         * Validamos los datos que posteriormente se utilizarán
         * dentro de las cabeceras SMTP/MIME.
         *
         * Nunca debemos permitir caracteres CR o LF en estos valores,
         * ya que podrían utilizarse para inyectar cabeceras de correo.
         */
        $this->validateHeaderValue($to, 'destinatario');
        $this->validateHeaderValue($subject, 'asunto');

        /*
         * Validamos específicamente que el destinatario tenga
         * un formato de correo electrónico válido.
         */
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('La dirección de correo del destinatario no es válida.');
        }

        /*
         * Comprobamos que la configuración SMTP necesaria exista.
         */
        $this->validateConfiguration();

        try {
            /*
             * 1. Abrimos la conexión TCP con Gmail.
             */
            $this->connect();

            /*
             * 2. Gmail debe responder inicialmente con código 220.
             */
            $this->expectResponse([220]);

            /*
             * 3. Presentamos nuestro cliente ante el servidor SMTP.
             */
            $this->sendCommand(
                'EHLO camagru.local',
                [250]
            );

            /*
             * 4. Activamos STARTTLS.
             *
             * A partir de este momento toda la comunicación SMTP
             * viajará cifrada.
             */
            $this->sendCommand(
                'STARTTLS',
                [220]
            );

            /*
             * Activamos TLS sobre el socket existente.
             */
            $this->enableTls();

            /*
             * Después de activar TLS debemos volver a identificarnos
             * mediante EHLO.
             */
            $this->sendCommand(
                'EHLO camagru.local',
                [250]
            );

            /*
             * 5. Autenticamos la cuenta de Gmail.
             */
            $this->authenticate();

            /*
             * 6. Indicamos quién envía el mensaje.
             */
            $this->sendCommand(
                'MAIL FROM:<' . $this->config['from_address'] . '>',
                [250]
            );

            /*
             * 7. Indicamos el destinatario.
             */
            $this->sendCommand(
                'RCPT TO:<' . $to . '>',
                [250, 251]
            );

            /*
             * 8. Indicamos que vamos a enviar el contenido del mensaje.
             */
            $this->sendCommand(
                'DATA',
                [354]
            );

            /*
             * 9. Construimos las cabeceras y el cuerpo MIME.
             */
            $message = $this->buildMessage(
                $to,
                $subject,
                $htmlBody,
                $textBody
            );

            /*
             * En SMTP, las líneas que empiezan por un punto deben
             * recibir otro punto delante.
             *
             * Esto se conoce como "dot-stuffing".
             */
            $message = $this->dotStuff($message);

            /*
             * El final de un mensaje SMTP se indica mediante:
             *
             * <CRLF>.<CRLF>
             */
            $this->write($message . "\r\n.\r\n");

            /*
             * Gmail debe confirmar la aceptación del mensaje.
             */
            $this->expectResponse([250]);

            /*
             * Cerramos correctamente la sesión SMTP.
             */
            $this->sendCommand(
                'QUIT',
                [221]
            );

            return true;
        } finally {
            /*
             * Aunque se produzca una excepción, siempre cerramos
             * el socket para no dejar conexiones abiertas.
             */
            $this->close();
        }
    }

    /**
     * Comprueba que exista toda la configuración SMTP necesaria.
     */
    private function validateConfiguration(): void
    {
        $required = [
            'host',
            'port',
            'username',
            'password',
            'from_address',
        ];

        foreach ($required as $key) {
            if (
                !isset($this->config[$key])
                || $this->config[$key] === ''
            ) {
                throw new RuntimeException(
                    'Falta la configuración SMTP: ' . $key
                );
            }
        }

        /*
         * El puerto debe ser un número válido.
         */
        if ($this->config['port'] <= 0) {
            throw new RuntimeException('El puerto SMTP no es válido.');
        }

        /*
         * La dirección configurada como remitente también debe
         * tener un formato de correo válido.
         */
        if (
            filter_var(
                $this->config['from_address'],
                FILTER_VALIDATE_EMAIL
            ) === false
        ) {
            throw new RuntimeException(
                'La dirección configurada como remitente no es válida.'
            );
        }
    }

    /**
     * Abre una conexión TCP con el servidor SMTP.
     */
    private function connect(): void
    {
        /*
         * Usamos tcp:// porque STARTTLS comienza inicialmente
         * como una conexión SMTP normal y posteriormente cambia
         * a una conexión TLS mediante STARTTLS.
         */
        $address = sprintf(
            'tcp://%s:%d',
            $this->config['host'],
            $this->config['port']
        );

        $errorCode = 0;
        $errorMessage = '';

        /*
         * Timeout de 15 segundos para evitar que una conexión
         * SMTP bloqueada deje la aplicación esperando indefinidamente.
         */
        $this->socket = @stream_socket_client(
            $address,
            $errorCode,
            $errorMessage,
            15,
            STREAM_CLIENT_CONNECT
        );

        if ($this->socket === false) {
            throw new RuntimeException(
                'No se pudo conectar con el servidor SMTP: '
                . $errorMessage
            );
        }

        /*
         * Establecemos también un timeout para las operaciones
         * posteriores de lectura/escritura.
         */
        stream_set_timeout($this->socket, 15);
    }

    /**
     * Activa TLS sobre la conexión SMTP.
     */
    private function enableTls(): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException(
                'No existe una conexión SMTP activa.'
            );
        }

        /*
         * Activamos TLS utilizando la negociación criptográfica
         * disponible en OpenSSL.
         *
         * Los certificados del servidor deben ser verificados.
         */
        $cryptoEnabled = @stream_socket_enable_crypto(
            $this->socket,
            true,
            STREAM_CRYPTO_METHOD_TLS_CLIENT
        );

        if ($cryptoEnabled !== true) {
            throw new RuntimeException(
                'No se pudo establecer la conexión TLS con Gmail.'
            );
        }
    }

    /**
     * Autentica la cuenta de Gmail mediante AUTH LOGIN.
     */
    private function authenticate(): void
    {
        /*
         * Indicamos que queremos utilizar autenticación LOGIN.
         */
        $this->sendCommand(
            'AUTH LOGIN',
            [334]
        );

        /*
         * SMTP AUTH LOGIN espera el usuario codificado en Base64.
         */
        $this->sendCommand(
            base64_encode($this->config['username']),
            [334]
        );

        /*
         * A continuación espera la contraseña codificada en Base64.
         *
         * Es la contraseña de aplicación de Google, no la contraseña
         * principal de la cuenta.
         */
        $this->sendCommand(
            base64_encode($this->config['password']),
            [235]
        );
    }

    /**
     * Construye el mensaje MIME.
     *
     * Se incluyen simultáneamente:
     * - versión de texto plano;
     * - versión HTML.
     *
     * Los clientes de correo podrán utilizar la versión compatible
     * con cada dispositivo.
     */
    private function buildMessage(
        string $to,
        string $subject,
        string $htmlBody,
        string $textBody
    ): string {
        /*
         * Creamos un límite único para separar las dos partes MIME.
         */
        $boundary = '=_Camagru_' . bin2hex(random_bytes(16));

        /*
         * Codificamos el asunto para permitir caracteres UTF-8
         * como tildes y otros caracteres especiales.
         */
        $encodedSubject = $this->encodeHeader($subject);

        /*
         * Codificamos el nombre del remitente si contiene
         * caracteres que requieran UTF-8.
         */
        $fromName = $this->encodeHeader(
            (string) $this->config['from_name']
        );

        /*
         * Todas las cabeceras utilizan CRLF, que es el formato
         * requerido por SMTP.
         */
        $headers = [
            'From: ' . $fromName
                . ' <' . $this->config['from_address'] . '>',
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <'
                . bin2hex(random_bytes(16))
                . '@camagru.local>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="'
                . $boundary
                . '"',
        ];

        /*
         * Normalizamos los saltos de línea del contenido.
         */
        $textBody = $this->normalizeLineEndings($textBody);
        $htmlBody = $this->normalizeLineEndings($htmlBody);

        /*
         * Construimos las dos partes del mensaje.
         */
        $body = '';

        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n";
        $body .= "\r\n";
        $body .= $textBody . "\r\n";

        $body .= '--' . $boundary . "\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: 8bit\r\n";
        $body .= "\r\n";
        $body .= $htmlBody . "\r\n";

        $body .= '--' . $boundary . "--\r\n";

        return implode("\r\n", $headers)
            . "\r\n\r\n"
            . $body;
    }

    /**
     * Envía un comando SMTP y comprueba la respuesta recibida.
     *
     * @param string $command Comando que se enviará.
     * @param array<int> $expectedCodes Códigos SMTP aceptados.
     */
    private function sendCommand(
        string $command,
        array $expectedCodes
    ): void {
        $this->write($command . "\r\n");

        $this->expectResponse($expectedCodes);
    }

    /**
     * Escribe datos directamente en el socket.
     */
    private function write(string $data): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException(
                'No existe una conexión SMTP activa.'
            );
        }

        $length = strlen($data);
        $written = 0;

        /*
         * fwrite() puede escribir solamente una parte de los datos.
         * Por eso continuamos hasta enviar el mensaje completo.
         */
        while ($written < $length) {
            $result = fwrite(
                $this->socket,
                substr($data, $written)
            );

            if ($result === false) {
                throw new RuntimeException(
                    'No se pudieron enviar datos al servidor SMTP.'
                );
            }

            $written += $result;
        }
    }

    /**
	 * Lee una respuesta SMTP completa y comprueba su código.
	 *
	 * SMTP puede devolver respuestas multilínea. Por ejemplo:
	 *
	 * 250-servidor
	 * 250-AUTH LOGIN
	 * 250 OK
	 *
	 * Debemos leer todas las líneas hasta encontrar una respuesta
	 * cuyo cuarto carácter sea un espacio.
	 */
	private function expectResponse(array $expectedCodes): void
	{
		/*
		* Guardamos toda la respuesta recibida para poder mostrarla
		* si el servidor devuelve un código inesperado.
		*/
		$response = '';

		while (true) {
			/*
			* Leemos una línea del servidor SMTP.
			*/
			$line = fgets($this->socket);

			/*
			* Si no podemos leer la respuesta, significa que el servidor
			* ha cerrado la conexión o se ha producido un error de lectura.
			*/
			if ($line === false) {
				throw new RuntimeException(
					'El servidor SMTP cerró la conexión inesperadamente.'
				);
			}

			/*
			* Añadimos la línea completa a la respuesta.
			*/
			$response .= $line;

			/*
			* Una respuesta SMTP tiene:
			*
			* 3 dígitos + "-" → todavía quedan líneas.
			* 3 dígitos + " " → esta es la última línea.
			*/
			if (
				strlen($line) >= 4
				&& $line[3] === ' '
			) {
				break;
			}
		}

		/*
		* La última línea contiene el código SMTP.
		*
		* Eliminamos los saltos de línea y espacios innecesarios
		* y obtenemos directamente los primeros tres caracteres.
		*/
		$lines = preg_split(
			"/\r\n|\n|\r/",
			trim($response)
		);

		/*
		* Si por alguna razón no conseguimos ninguna línea válida,
		* consideramos la respuesta incorrecta.
		*/
		if (
			$lines === false
			|| count($lines) === 0
		) {
			throw new RuntimeException(
				'No se pudo interpretar la respuesta del servidor SMTP.'
			);
		}

		/*
		* La última línea es la que contiene el código SMTP definitivo.
		*/
		$lastLine = $lines[count($lines) - 1];

		/*
		* Extraemos los tres primeros caracteres.
		*/
		$code = (int) substr($lastLine, 0, 3);

		/*
		* Comprobamos si el código recibido coincide con alguno
		* de los códigos que esperábamos.
		*/
		if (!in_array($code, $expectedCodes, true)) {
			throw new RuntimeException(
				'Respuesta SMTP inesperada. '
				. 'Código: ' . $code
				. '. Respuesta: ' . trim($response)
			);
		}
	}

    /**
     * Escapa las líneas que empiezan por "." para cumplir
     * la especificación SMTP.
     */
    private function dotStuff(string $message): string
    {
        /*
         * Normalizamos primero los saltos de línea.
         */
        $message = $this->normalizeLineEndings($message);

        /*
         * Añadimos un punto delante de cualquier línea que
         * comience originalmente por otro punto.
         */
        return preg_replace(
            '/(^|\r\n)\./',
            '$1..',
            $message
        ) ?? $message;
    }

    /**
     * Normaliza cualquier salto de línea a CRLF.
     */
    private function normalizeLineEndings(string $value): string
    {
        $value = str_replace(
            ["\r\n", "\r"],
            "\n",
            $value
        );

        return str_replace(
            "\n",
            "\r\n",
            $value
        );
    }

    /**
     * Codifica una cabecera que puede contener caracteres UTF-8.
     */
    private function encodeHeader(string $value): string
    {
        return '=?UTF-8?B?'
            . base64_encode($value)
            . '?=';
    }

    /**
     * Evita CRLF injection en valores que terminan formando
     * parte de cabeceras SMTP.
     */
    private function validateHeaderValue(
        string $value,
        string $field
    ): void {
        if (
            str_contains($value, "\r")
            || str_contains($value, "\n")
        ) {
            throw new RuntimeException(
                'El campo ' . $field
                . ' contiene caracteres no permitidos.'
            );
        }
    }

    /**
     * Cierra el socket SMTP si continúa abierto.
     */
    private function close(): void
    {
        if (is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}