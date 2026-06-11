<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if (isset($_GET['id'])) {
    $id = $_GET['id'];

    $sql = "DELETE FROM proveedores WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);

    echo "<script>alert('Proveedor eliminado correctamente.'); window.location.href='proveedores_lista.php';</script>";
} else {
    echo "<script>alert('ID no proporcionado.'); window.location.href='proveedores_lista.php';</script>";
}
