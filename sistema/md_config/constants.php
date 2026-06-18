<?php
/**
 * Configuración general de Factheo
 * Detecta automáticamente el entorno (desarrollo/producción)
 */

// Detectar si estamos en producción (app.factheo.com)
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';

$es_produccion = (
    strpos($server_name, 'factheo.com') !== false ||
    strpos($server_name, 'app.factheo') !== false ||
    strpos($document_root, '/var/www/app.factheo') !== false ||
    strpos($request_uri, '/factheo/') === false  // Si en la URL no aparece /factheo/, es raíz = producción
);

if ($es_produccion) {
    define('URL_BASE', '/');
    define('RUTA_SISTEMA', '/var/www/app.factheo.com/sistema');
} else {
    define('URL_BASE', '/factheo/');
    define('RUTA_SISTEMA', '/var/www/html/factheo/sistema');
}
