<?php
/**
 * Configuración general de Factheo
 * Detecta automáticamente el entorno (desarrollo/producción)
 */

// Detectar entorno por nombre de servidor
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$document_root = $_SERVER['DOCUMENT_ROOT'] ?? '';

if (
    strpos($server_name, 'factheo.com') !== false ||
    strpos($document_root, 'app.factheo.com') !== false
) {
    // 🖥 Producción: dominio raíz
    define('URL_BASE', '/');
    define('RUTA_SISTEMA', '/var/www/app.factheo.com/sistema');
} else {
    // 💻 Desarrollo local (Kali, localhost/factheo/)
    define('URL_BASE', '/factheo/');
    define('RUTA_SISTEMA', '/var/www/html/factheo/sistema');
}
