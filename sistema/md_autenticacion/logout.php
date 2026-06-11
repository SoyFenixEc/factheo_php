<?php
//ini_set('session.save_path','../../sistema/sesiones');
session_start();
 
// Conexión a la base de datos
require_once('../md_config/conexion.php');

// Eliminar token de sesión de la base de datos si existe
if (isset($_COOKIE['sesion_token'])) {
    $token = $_COOKIE['sesion_token'];

    // Eliminar de la base de datos
    $sql = "DELETE FROM sesiones WHERE token = :token";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':token' => $token]);

    // Borrar cookie
    setcookie('sesion_token', '', time() - 3600, "/");
}

// Destruir todas las variables de sesión
session_unset();
session_destroy();

// Redirigir al login
header("Location: login.php");
exit();
?>
