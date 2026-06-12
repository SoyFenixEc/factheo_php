<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar si se ha recibido el ID de la forma de pago
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];

    try {
        // Actualizar la forma de pago en la base de datos
        $sql = "UPDATE formas_pago SET nombre = :nombre, descripcion = :descripcion WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion, ':id' => $id]);

        // Redirigir a la lista de formas de pago con un mensaje de éxito
        header("Location: formas_pago_lista.php?mensaje=Forma de pago actualizada con éxito.");
        exit;

    } catch (PDOException $e) {
        echo "Error al actualizar la forma de pago: " . $e->getMessage();
    }
} else {
    echo "<script>alert('Datos no proporcionados correctamente'); window.location.href='formas_pago_lista.php';</script>";
    exit;
}
?>
