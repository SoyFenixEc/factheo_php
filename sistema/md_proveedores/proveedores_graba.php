<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = $_POST['nombre'];
    $ruc = $_POST['ruc'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    try {
        $sql = "INSERT INTO proveedores (nombre, ruc, direccion, telefono, email) VALUES (:nombre, :ruc, :direccion, :telefono, :email)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':nombre' => $nombre,
            ':ruc' => $ruc,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':email' => $email
        ]);

        echo "<script>alert('Proveedor guardado exitosamente.'); window.location.href='proveedores_lista.php';</script>";
    } catch (PDOException $e) {
        echo "Error al guardar el proveedor: " . $e->getMessage();
    }
} else {
    echo "Acceso no permitido.";
}
