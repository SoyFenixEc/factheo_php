<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $ruc = $_POST['ruc'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    $sql = "UPDATE proveedores SET nombre = :nombre, ruc = :ruc, direccion = :direccion, telefono = :telefono, email = :email WHERE id = :id";
    $params = [
        ':nombre' => $nombre,
        ':ruc' => $ruc,
        ':direccion' => $direccion,
        ':telefono' => $telefono,
        ':email' => $email,
        ':id' => $id
    ];

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo "<script>alert('Proveedor actualizado exitosamente.'); window.location.href='proveedores_lista.php';</script>";
    } catch (PDOException $e) {
        echo "Error al actualizar el proveedor: " . $e->getMessage();
    }
} else {
    echo "Acceso no permitido.";
}
