<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $nombre_comercial = $_POST['nombre_comercial'];
    $razon_social = $_POST['razon_social'];
    $ruc = $_POST['ruc'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];
    $obligado_contabilidad = $_POST['obligado_contabilidad'];
    $clave_certificado = $_POST['clave_certificado'];

    // Manejo del archivo certificado
    $certificado = $_FILES['certificado'];
    $certificado_name = $certificado['name'];
    $ruta_certificado = 'certificados/' . basename($certificado['name']);

    // Manejo del archivo logo
    $logo = $_FILES['logo'];
    $ruta_logo = 'logos/' . basename($logo['name']);
    $name_logo = basename($logo['name']);

    // Primero verificamos si la empresa ya existe por RUC
    try {
        $sql_verificar = "SELECT id FROM empresa WHERE ruc = :ruc";
        $stmt_verificar = $pdo->prepare($sql_verificar);
        $stmt_verificar->execute([':ruc' => $ruc]);
        
        if ($stmt_verificar->rowCount() > 0) {
            echo "<script>alert('La empresa con RUC $ruc ya existe. Contactar con soporte técnico.'); window.location.href='empresa_nueva.php';</script>";
            exit();
        }
    } catch (PDOException $e) {
        echo "<script>alert('Error al verificar la empresa: " . addslashes($e->getMessage()) . "'); window.location.href='empresa_nueva.php';</script>";
        exit();
    }

    // Si no existe, procedemos a subir los archivos y crear la empresa
    if (move_uploaded_file($certificado['tmp_name'], $ruta_certificado) && move_uploaded_file($logo['tmp_name'], $ruta_logo)) {
        try {
            $sql = "INSERT INTO empresa (nombre_comercial, razon_social, obligado_contabilidad, ruc, direccion, telefono, email, certificado, clave_certificado, logo, usuario_id) 
                    VALUES (:nombre_comercial, :razon_social, :obligado_contabilidad, :ruc, :direccion, :telefono, :email, :certificado, :clave_certificado, :logo, :usuario_id)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nombre_comercial' => $nombre_comercial,
                ':razon_social' => $razon_social,
                ':obligado_contabilidad' => $obligado_contabilidad,
                ':ruc' => $ruc,
                ':direccion' => $direccion,
                ':telefono' => $telefono,
                ':email' => $email,
                ':certificado' => $certificado_name,
                ':clave_certificado' => $clave_certificado,
                ':logo' => $name_logo,
                ':usuario_id' => $usuario_id
            ]);
            echo "<script>alert('Empresa guardada exitosamente.'); window.location.href='empresa_lista.php';</script>";
        } catch (PDOException $e) {
            echo "<script>alert('Error al guardar la empresa: " . addslashes($e->getMessage()) . "'); window.location.href='empresa_nueva.php';</script>";
        }
    } else {
        echo "<script>alert('Error al subir los archivos de certificado o logo.'); window.location.href='empresa_nueva.php';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='empresa_lista.php';</script>";
}
?>