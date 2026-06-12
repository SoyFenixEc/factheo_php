<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar si se ha recibido el ID
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    try {
        // Eliminar la forma de pago
        $sql = "DELETE FROM formas_pago WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id]);

        // Redirigir a la lista con un mensaje de éxito
        header("Location: formas_pago_lista.php?mensaje=Forma de pago eliminada con éxito.");
        exit;

    } catch (PDOException $e) {
        echo "Error al eliminar la forma de pago: " . $e->getMessage();
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='formas_pago_lista.php';</script>";
    exit;
}
?>
