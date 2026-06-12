<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_POST['id'];
    $razon_social = $_POST['razon_social'];
    $identificacion = $_POST['identificacion'];
    $id_tipos_identificacion = $_POST['id_tipos_identificacion'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    // Verificar que el cliente pertenece al usuario
    $sql_check = "SELECT id FROM clientes WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar este cliente.'); window.location.href='cliente_lista.php';</script>";
        exit();
    }

    // Actualizar el cliente
    $sql_update = "UPDATE clientes SET 
                    razon_social = :razon_social, 
                    identificacion = :identificacion, 
                    id_tipos_identificacion = :id_tipos_identificacion,
                    direccion = :direccion, 
                    telefono = :telefono, 
                    email = :email 
                   WHERE id = :id";
    $params = [
        ':razon_social' => $razon_social,
        ':identificacion' => $identificacion,
        ':id_tipos_identificacion' => $id_tipos_identificacion,
        ':direccion' => $direccion,
        ':telefono' => $telefono,
        ':email' => $email,
        ':id' => $id
    ];

    try {
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute($params);
        echo "<script>alert('Cliente actualizado exitosamente.'); window.location.href='cliente_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar el cliente: " . $e->getMessage() . "'); window.location.href='cliente_editar.php?id=" . $id . "';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='cliente_lista.php';</script>";
}
?>