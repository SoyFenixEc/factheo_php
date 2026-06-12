<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_POST['id'];
    $nombre_comercial = $_POST['nombre_comercial'];
    $razon_social = $_POST['razon_social'];
    $ruc = $_POST['ruc'];
    $direccion = $_POST['direccion'];
    $obligado_contabilidad = $_POST['obligado_contabilidad'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $clave_certificado = $_POST['clave_certificado'];

    // Verificar que la empresa pertenece al usuario actual antes de actualizar
    $sql_check = "SELECT id FROM empresa WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar esta empresa.'); window.location.href='empresa_lista.php';</script>";
        exit();
    }

    // Construir la consulta base
    $sql = "UPDATE empresa SET 
            nombre_comercial = :nombre_comercial, 
            razon_social = :razon_social, 
            obligado_contabilidad = :obligado_contabilidad, 
            ruc = :ruc, 
            direccion = :direccion, 
            telefono = :telefono, 
            email = :email, 
            clave_certificado = :clave_certificado 
            WHERE id = :id";
    $params = [
        ':nombre_comercial' => $nombre_comercial,
        ':razon_social' => $razon_social,
        ':obligado_contabilidad' => $obligado_contabilidad,
        ':ruc' => $ruc,
        ':direccion' => $direccion,
        ':telefono' => $telefono,
        ':email' => $email,
        ':clave_certificado' => $clave_certificado,
        ':id' => $id
    ];

    // Manejo del archivo certificado
    if (!empty($_FILES['certificado']['name'])) {
        $certificado = $_FILES['certificado'];
        $certificado_name = $certificado['name'];
        $ruta_certificado = 'certificados/' . basename($certificado['name']);
        if (move_uploaded_file($certificado['tmp_name'], $ruta_certificado)) {
            $sql .= ", certificado = :certificado";
            $params[':certificado'] = $certificado_name;
        }
    }

    // Manejo del archivo logo
    if (!empty($_FILES['logo']['name'])) {
        $logo = $_FILES['logo'];
        $ruta_logo = 'logos/' . basename($logo['name']);
        if (move_uploaded_file($logo['tmp_name'], $ruta_logo)) {
            $sql .= ", logo = :logo";
            $params[':logo'] = basename($logo['name']);
        }
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo "<script>alert('Empresa actualizada exitosamente.'); window.location.href='empresa_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar la empresa: " . addslashes($e->getMessage()) . "'); window.location.href='empresa_editar.php?id=" . $id . "';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='empresa_lista.php';</script>";
}
?>