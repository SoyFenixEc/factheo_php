<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if (isset($_POST['id'])) {
    $cliente_id = $_POST['id'];

    // Obtener la información del cliente
    $sql = "SELECT identificacion, direccion, email, telefono, razon_social FROM clientes WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $cliente_id]);
    $cliente = $stmt->fetch();

    if ($cliente) {
        echo json_encode($cliente);
    } else {
        echo json_encode(['error' => 'Cliente no encontrado']);
    }
} else {
    echo json_encode(['error' => 'ID no proporcionado']);
}
?>
