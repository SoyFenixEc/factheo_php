<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar si se ha proporcionado un ID de punto de emisión
if (isset($_GET['id'])) {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_GET['id'];

    // Verificar que el punto de emisión pertenece al usuario actual
    $sql_check = "SELECT pe.id FROM punto_emision pe JOIN empresa e ON pe.empresa_id = e.id WHERE pe.id = :id AND e.usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para eliminar este punto de emisión.'); window.location.href='punto_emision_lista.php';</script>";
        exit;
    }

    try {
        // Preparar la consulta SQL para eliminar el punto de emisión
        $sql = "DELETE FROM punto_emision WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        // Redirigir a la lista de puntos de emisión con un mensaje de éxito
        echo "<script>alert('Punto de emisión eliminado con éxito.'); window.location.href='punto_emision_lista.php';</script>";
        exit;

    } catch (PDOException $e) {
        // En caso de error, mostrar el mensaje de error
        echo "<script>alert('Error al eliminar el punto de emisión: " . addslashes($e->getMessage()) . "'); window.location.href='punto_emision_lista.php';</script>";
        exit;
    }
} else {
    // Si no se ha recibido el ID, redirigir a la lista de puntos de emisión
    echo "<script>alert('ID no proporcionado'); window.location.href='punto_emision_lista.php';</script>";
    exit;
}
?>