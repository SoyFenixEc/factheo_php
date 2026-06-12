<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if (isset($_GET['id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_GET['id'];

    // Verificar que la empresa pertenece al usuario actual
    $sql_check = "SELECT id FROM empresa WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para eliminar esta empresa.'); window.location.href='empresa_lista.php';</script>";
        exit();
    }

    // Proceder con la eliminación
    $sql_delete = "DELETE FROM empresa WHERE id = :id";
    $stmt_delete = $pdo->prepare($sql_delete);
    $stmt_delete->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_delete->execute();

    echo "<script>alert('Empresa eliminada correctamente.'); window.location.href='empresa_lista.php';</script>";
} else {
    echo "<script>alert('ID no proporcionado.'); window.location.href='empresa_lista.php';</script>";
}
?>