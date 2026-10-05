<section class="panel image-creator">
    <h1>Crear imagen</h1>

    <p class="page-description">
        Sube una imagen desde tu dispositivo o utiliza tu webcam
        para crear una nueva publicación.
    </p>

	<!--
        Formulario de creación de imagen.

        Usamos multipart/form-data porque necesitamos
        enviar archivos de imagen al servidor.
    -->
    <form
        method="POST"
        action="/create-image"
        enctype="multipart/form-data"
        id="image-form"
    >
        <!--
            Token CSRF para proteger el formulario
            frente a solicitudes externas.
        -->
        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                $csrfToken,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

    <?php if (isset($error)): ?>
        <!--
            Mostramos los errores de validación enviados
            por ImageController.
        -->
        <div class="alert alert-error">
            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['success'])): ?>
        <!--
            Este mensaje se utiliza mientras terminamos
            de implementar el procesamiento de overlays.
        -->
        <div class="alert alert-success">
            La imagen se ha subido correctamente.
        </div>
    <?php endif; ?>

    <!--
        Selector de origen de la imagen.

        Los botones no envían todavía ningún formulario.
        Simplemente permiten cambiar entre:
        - subida de archivo;
        - webcam.
    -->
    <div class="image-source-selector">

        <button
            type="button"
            class="button"
            id="upload-mode-button"
        >
            Subir imagen
        </button>

        <button
            type="button"
            class="button button-secondary"
            id="camera-mode-button"
        >
            Usar webcam
        </button>

    </div>

    <!--
        Área de subida desde el dispositivo.
    -->
    <div
        id="upload-mode"
        class="image-source-panel"
    >
        <label for="image">
            Selecciona una imagen
        </label>

        <input
            type="file"
            id="image"
            name="image"
            accept="image/jpeg,image/png"
        >
    </div>

    <!--
        Área de webcam.

        Inicialmente permanece oculta.
        JavaScript la mostrará cuando el usuario
        seleccione "Usar webcam".
    -->
    <div
        id="camera-mode"
        class="image-source-panel"
        hidden
    >
        <video
            id="camera-preview"
            autoplay
            playsinline
        ></video>

        <canvas
            id="camera-canvas"
            hidden
        ></canvas>

        <div class="camera-controls">

            <button
                type="button"
                class="button"
                id="start-camera-button"
            >
                Activar webcam
            </button>

            <button
                type="button"
                class="button"
                id="capture-button"
                disabled
            >
                Capturar imagen
            </button>

            <button
                type="button"
                class="button button-secondary"
                id="stop-camera-button"
                disabled
            >
                Detener webcam
            </button>

        </div>
    </div>

    <!--
        Vista previa de la imagen seleccionada o capturada.
    -->
    <div
        id="image-preview-container"
        class="image-preview-container"
        hidden
    >
        <h2>Vista previa</h2>

        <img
            id="image-preview"
            src=""
            alt="Vista previa de la imagen"
        >
    </div>

        <!--
            Este input será rellenado por JavaScript cuando
            el usuario capture una imagen con la webcam.
        -->
        <input
            type="file"
            id="camera-image"
            name="camera_image"
            hidden
        >

        <button
            type="submit"
            class="button"
            id="submit-image-button"
        >
            Continuar
        </button>
    </form>
</section>

<script>
/*
 * Referencias a los elementos principales de la interfaz.
 */
const uploadModeButton = document.getElementById(
    'upload-mode-button'
);

const cameraModeButton = document.getElementById(
    'camera-mode-button'
);

const uploadMode = document.getElementById(
    'upload-mode'
);

const cameraMode = document.getElementById(
    'camera-mode'
);

const imageInput = document.getElementById(
    'image'
);

const cameraImageInput = document.getElementById(
    'camera-image'
);

const cameraPreview = document.getElementById(
    'camera-preview'
);

const cameraCanvas = document.getElementById(
    'camera-canvas'
);

const startCameraButton = document.getElementById(
    'start-camera-button'
);

const captureButton = document.getElementById(
    'capture-button'
);

const stopCameraButton = document.getElementById(
    'stop-camera-button'
);

const imagePreviewContainer = document.getElementById(
    'image-preview-container'
);

const imagePreview = document.getElementById(
    'image-preview'
);

/*
 * Guardamos la referencia al stream de la webcam.
 *
 * Se utiliza posteriormente para detener todas las pistas
 * cuando el usuario cierre la cámara.
 */
let cameraStream = null;

/*
 * Muestra la sección de subida de archivos.
 */
function showUploadMode() {
    uploadMode.hidden = false;
    cameraMode.hidden = true;

    /*
     * Si cambiamos a subida de archivo, detenemos la webcam
     * para no mantener el acceso a la cámara innecesariamente.
     */
    stopCamera();
}

/*
 * Muestra la sección de webcam.
 */
function showCameraMode() {
    uploadMode.hidden = true;
    cameraMode.hidden = false;
}

/*
 * Activa la webcam del dispositivo.
 */
async function startCamera() {
    /*
     * Comprobamos que el navegador implemente
     * la API moderna de captura de vídeo.
     */
    if (!navigator.mediaDevices
        || !navigator.mediaDevices.getUserMedia) {

        alert(
            'Este navegador no permite acceder a la webcam.'
        );

        return;
    }

    try {
        /*
         * Solicitamos acceso únicamente al vídeo.
         *
         * No necesitamos utilizar el micrófono.
         */
        cameraStream = await navigator.mediaDevices.getUserMedia({
            video: true,
            audio: false
        });

        /*
         * Conectamos el stream directamente al elemento <video>.
         */
        cameraPreview.srcObject = cameraStream;

        /*
         * Una vez activa la cámara, permitimos capturar.
         */
        captureButton.disabled = false;
        stopCameraButton.disabled = false;
        startCameraButton.disabled = true;
    } catch (error) {
        /*
         * No mostramos detalles internos del error.
         */
        alert(
            'No se ha podido acceder a la webcam.'
        );
    }
}

/*
 * Captura un fotograma de la webcam.
 */
function captureImage() {
    /*
     * No hacemos nada si la cámara no está activa.
     */
    if (!cameraStream) {
        return;
    }

    /*
     * Utilizamos las dimensiones reales del vídeo
     * para mantener la resolución de la captura.
     */
    cameraCanvas.width = cameraPreview.videoWidth;
    cameraCanvas.height = cameraPreview.videoHeight;

    /*
     * Obtenemos el contexto 2D necesario para dibujar
     * el fotograma actual del vídeo.
     */
    const context = cameraCanvas.getContext('2d');

    if (!context) {
        alert(
            'No se ha podido preparar la captura.'
        );

        return;
    }

    /*
     * Dibujamos el fotograma actual de la webcam
     * dentro del canvas.
     */
    context.drawImage(
        cameraPreview,
        0,
        0,
        cameraCanvas.width,
        cameraCanvas.height
    );

    /*
     * Convertimos el contenido del canvas en un Blob JPEG.
     *
     * Posteriormente convertiremos ese Blob en un File
     * para que PHP lo reciba como una subida normal.
     */
    cameraCanvas.toBlob(
        function (blob) {
            if (!blob) {
                alert(
                    'No se ha podido capturar la imagen.'
                );

                return;
            }

            /*
             * Creamos un File con un nombre generado
             * por nuestra aplicación.
             */
            const capturedFile = new File(
                [blob],
                'webcam-capture.jpg',
                {
                    type: 'image/jpeg'
                }
            );

            /*
             * DataTransfer permite asignar el File generado
             * dinámicamente al input de tipo file.
             */
            const dataTransfer = new DataTransfer();

            dataTransfer.items.add(capturedFile);

            cameraImageInput.files = dataTransfer.files;

            /*
             * Mostramos la captura al usuario antes de enviarla.
             */
            imagePreview.src = URL.createObjectURL(blob);
            imagePreviewContainer.hidden = false;

            /*
             * Detenemos la cámara después de realizar
             * la captura.
             */
            stopCamera();
        },
        'image/jpeg',
        0.92
    );
}

/*
 * Detiene todas las pistas de vídeo de la webcam.
 */
function stopCamera() {
    if (cameraStream) {
        cameraStream.getTracks().forEach(function (track) {
            track.stop();
        });

        cameraStream = null;
    }

    /*
     * Restablecemos el estado de los botones.
     */
    captureButton.disabled = true;
    stopCameraButton.disabled = true;
    startCameraButton.disabled = false;

    /*
     * Eliminamos la referencia al stream del elemento <video>.
     */
    cameraPreview.srcObject = null;
}

/*
 * Cuando el usuario selecciona una imagen desde su dispositivo,
 * mostramos una vista previa.
 */
imageInput.addEventListener(
    'change',
    function () {
        const file = imageInput.files[0];

        if (!file) {
            return;
        }

        /*
         * Creamos una URL temporal para mostrar la imagen.
         */
        imagePreview.src = URL.createObjectURL(file);
        imagePreviewContainer.hidden = false;

        /*
         * Si se selecciona una imagen manualmente,
         * eliminamos cualquier captura anterior de la webcam.
         */
        cameraImageInput.value = '';
    }
);

/*
 * Cambiamos al modo subida.
 */
uploadModeButton.addEventListener(
    'click',
    showUploadMode
);

/*
 * Cambiamos al modo webcam.
 */
cameraModeButton.addEventListener(
    'click',
    showCameraMode
);

/*
 * Activamos la webcam.
 */
startCameraButton.addEventListener(
    'click',
    startCamera
);

/*
 * Capturamos una imagen.
 */
captureButton.addEventListener(
    'click',
    captureImage
);

/*
 * Permitimos detener manualmente la webcam.
 */
stopCameraButton.addEventListener(
    'click',
    stopCamera
);
</script>