<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    // Verificar que la bodega pertenece al usuario
    $sql_check = "SELECT id FROM bodegas WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar esta bodega.'); window.location.href='bodega_lista.php';</script>";
        exit();
    }

    // Actualizar la bodega
    $sql_update = "UPDATE bodegas SET nombre = :nombre, direccion = :direccion, telefono = :telefono, email = :email WHERE id = :id";
    $params = [
        ':nombre' => $nombre,
        ':direccion' => $direccion,
        ':telefono' => $telefono,
        ':email' => $email,
        ':id' => $id
    ];

    try {
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute($params);
        echo "<script>alert('Bodega actualizada exitosamente.'); window.location.href='bodega_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar la bodega.'); window.location.href='bodega_lista.php';</script>";
        error_log("Error al actualizar bodega: " . $e->getMessage());
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='bodega_lista.php';</script>";
}
?>