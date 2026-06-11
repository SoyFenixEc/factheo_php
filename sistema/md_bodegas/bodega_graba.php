<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    try {
        $sql = "INSERT INTO bodegas (nombre, direccion, telefono, email) VALUES (:nombre, :direccion, :telefono, :email)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':email' => $email
        ]);

        echo "<script>alert('Bodega guardada exitosamente.'); window.location.href='bodega_lista.php';</script>";
    } catch (PDOException $e) {
        echo "Error al guardar la bodega: " . $e->getMessage();
    }
} else {
    echo "Acceso no permitido.";
}
