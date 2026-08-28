<?php

function debuguear($variable)
{
    echo "<pre>";
    var_dump($variable);
    echo "</pre>";
    exit;
}

// Escapa / Sanitizar el HTML
function s($html)
{
    $s = htmlspecialchars($html);
    return $s;
}

// Función que revisa que el usuario este autenticado
function isAuth()
{
    session_start();
    if (!isset($_SESSION['login'])) {
        header('Location: /');
    }
}
function isAuthApi()
{
    getHeadersApi();
    session_start();
    if (!isset($_SESSION['auth_user'])) {
        echo json_encode([
            "mensaje" => "No esta autenticado",

            "codigo" => 4,
        ]);
        exit;
    }
}

function isNotAuth()
{
    session_start();
    if (isset($_SESSION['auth'])) {
        header('Location: /auth/');
    }
}


function hasPermission(array $permisos)
{

    $comprobaciones = [];
    foreach ($permisos as $permiso) {

        $comprobaciones[] = !isset($_SESSION[$permiso]) ? false : true;
    }

    if (array_search(true, $comprobaciones) !== false) {
    } else {
        header('Location: /');
    }
}

function hasPermissionApi(array $permisos)
{
    getHeadersApi();
    $comprobaciones = [];
    foreach ($permisos as $permiso) {

        $comprobaciones[] = !isset($_SESSION[$permiso]) ? false : true;
    }

    if (array_search(true, $comprobaciones) !== false) {
    } else {
        echo json_encode([
            "mensaje" => "No tiene permisos",

            "codigo" => 4,
        ]);
        exit;
    }
}

function getHeadersApi()
{
    return header("Content-type:application/json; charset=utf-8");
}

function asset($ruta)
{
    return "/" . $_ENV['APP_NAME'] . "/public/" . $ruta;
}


if (!function_exists('urlBase')) {
    // URL base de la app (para las llamadas fetch() del frontend, sin /public/)
    function urlBase()
    {
        return "/" . $_ENV['APP_NAME'];
    }
}

if (!function_exists('subirImagen')) {
    /**
     * Sube una imagen a public/uploads y devuelve el nombre de archivo
     * generado (no la ruta completa), o null si no se envio archivo.
     * Lanza \Exception si el archivo no es valido.
     */
    function subirImagen($archivo, $prefijo = '')
    {
        if (empty($archivo['name']) || $archivo['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        if ($archivo['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Ocurrió un error al subir la imagen');
        }

        $extensionesPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));

        if (!in_array($extension, $extensionesPermitidas, true)) {
            throw new Exception('Formato de imagen no permitido. Usa jpg, png o webp');
        }

        $tamanoMaximo = 5 * 1024 * 1024; // 5 MB
        if ($archivo['size'] > $tamanoMaximo) {
            throw new Exception('La imagen supera el tamaño máximo permitido (5 MB)');
        }

        $carpetaDestino = __DIR__ . '/../public/uploads';
        if (!is_dir($carpetaDestino)) {
            mkdir($carpetaDestino, 0775, true);
        }

        $prefijoLimpio = $prefijo ? preg_replace('/[^A-Za-z0-9_-]/', '', $prefijo) . '_' : '';
        $nombreArchivo = $prefijoLimpio . bin2hex(random_bytes(12)) . '.' . $extension;

        if (!move_uploaded_file($archivo['tmp_name'], "{$carpetaDestino}/{$nombreArchivo}")) {
            throw new Exception('No se pudo guardar la imagen en el servidor');
        }

        return $nombreArchivo;
    }
}

if (!function_exists('eliminarImagen')) {
    function eliminarImagen($nombreArchivo)
    {
        if (!$nombreArchivo) {
            return;
        }
        $ruta = __DIR__ . '/../public/uploads/' . $nombreArchivo;
        if (is_file($ruta)) {
            unlink($ruta);
        }
    }
}

if (!function_exists('responderJSON')) {
    // Respuesta JSON estandar: {codigo: 1|0, mensaje, datos}
    function responderJSON($codigo, $mensaje = '', $datos = null)
    {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'codigo'  => $codigo,
            'mensaje' => $mensaje,
            'datos'   => $datos,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
