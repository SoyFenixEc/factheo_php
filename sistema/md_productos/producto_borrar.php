<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if (isset($_GET['id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_GET['id'];

    // Verificar que el producto pertenece al usuario actual
    $sql_check = "SELECT foto FROM productos WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $producto = $stmt_check->fetch();

    if (!$producto) {
        echo "<script>alert('No tienes permiso para eliminar este producto.'); window.location.href='producto_lista.php';</script>";
        exit();
    }

    // Eliminar el archivo de la foto si existe
    if ($producto && !empty($producto['foto']) && file_exists($producto['foto'])) {
        unlink($producto['foto']);
    }

    // Eliminar el producto
    $sql = "DELETE FROM productos WHERE id = :id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();

    echo "<script>alert('Producto eliminado correctamente.'); window.location.href='producto_lista.php';</script>";
} else {
    echo "<script>alert('ID no proporcionado.'); window.location.href='producto_lista.php';</script>";
}
?>