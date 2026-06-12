<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtener el ID del usuario actual de la sesión
    $usuario_id = $_SESSION['usuario_id'];
    
    $razon_social = $_POST['razon_social'];
    $identificacion = $_POST['identificacion'];
    $id_tipos_identificacion = $_POST['id_tipos_identificacion'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    try {
        $sql = "INSERT INTO clientes (razon_social, identificacion, direccion, telefono, email, id_tipos_identificacion, usuario_id) 
                VALUES (:razon_social, :identificacion, :direccion, :telefono, :email, :id_tipos_identificacion, :usuario_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':razon_social' => $razon_social,
            ':identificacion' => $identificacion,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':email' => $email,
            ':id_tipos_identificacion' => $id_tipos_identificacion,
            ':usuario_id' => $usuario_id  // <-- ¡ESTA ES LA LÍNEA CLAVE QUE FALTABA!
        ]);
        
        echo "<script>alert('Cliente guardado exitosamente.'); window.location.href='cliente_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al guardar el cliente: " . addslashes($e->getMessage()) . "'); window.location.href='cliente_nuevo.php';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='cliente_lista.php';</script>";
}
?>