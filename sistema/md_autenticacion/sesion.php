<?php
date_default_timezone_set('America/Guayaquil');

// Iniciar sesión si no ha sido iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configurar sesión para que dure mucho tiempo
    ini_set('session.gc_maxlifetime', 31536000); // 1 año en segundos
    session_set_cookie_params([
        'lifetime' => 31536000, // 1 año
        'path' => '/',
        'domain' => '',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Conexión a la base de datos
require('../md_config/conexion.php');

// Verificar si el usuario tiene una sesión activa
if (!isset($_SESSION['usuario_id'])) {
    // Verificar si hay una cookie con un token válido
    if (isset($_COOKIE['sesion_token'])) {
        $token = $_COOKIE['sesion_token'];

        // Consultar la base de datos para validar el token
        $sql = "SELECT usuario_id, expiracion FROM sesiones WHERE token = :token";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
        $sesion = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sesion) {
            // ¡IMPORTANTE! Eliminamos la verificación de expiración para que sea "permanente"
            // if (strtotime($sesion['expiracion']) > time()) {

            // Restaurar la sesión
            $_SESSION['usuario_id'] = $sesion['usuario_id'];

            // Actualizar la fecha de expiración del token a 10 años en el futuro
            $nueva_expiracion = date('Y-m-d H:i:s', time() + 315360000); // 10 años
            $sql = "UPDATE sesiones SET expiracion = :expiracion WHERE token = :token";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':expiracion' => $nueva_expiracion, ':token' => $token]);

            // Actualizar la cookie con el nuevo tiempo de expiración (10 años)
            setcookie('sesion_token', $token, time() + 315360000, "/", "", true, true);

            // } else {
            //     // Token expirado - comentado para hacerlo permanente
            //     setcookie('sesion_token', '', time() - 3600, "/");
            // }
        } else {
            // Token no existe en la base de datos
            setcookie('sesion_token', '', time() - 3600, "/");
        }
    }
}

// Verificar si el usuario no tiene una sesión válida y redirigir al login
$current_file = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['usuario_id']) && $current_file != 'login.php') {
    header("Location: ../md_autenticacion/login.php");
    exit();
}
?>