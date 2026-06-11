<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $empresa_id = $_POST['empresa_id'];
    $punto_emision = $_POST['punto_emision'];
    $establecimiento = $_POST['establecimiento'];
    $secuencial_factura = $_POST['secuencial_factura'];
    $secuencial_nota_credito = $_POST['secuencial_nota_credito'];
    $secuencial_nota_debito = $_POST['secuencial_nota_debito'];
    $secuencial_comprobante_retencion = $_POST['secuencial_comprobante_retencion'];
    $secuencial_liquidacion_compra = $_POST['secuencial_liquidacion_compra'];
    $secuencial_guia_remision = $_POST['secuencial_guia_remision'];
    $descripcion = $_POST['descripcion'];
    $iva = $_POST['iva'];

    // Verificar que la empresa pertenece al usuario y está activa
    $sql_empresa = "SELECT id FROM empresa WHERE id = :empresa_id AND usuario_id = :usuario_id AND activa = '1'";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_empresa->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_empresa->execute(); 

    if ($stmt_empresa->rowCount() === 0) {
        echo "<script>alert('La empresa seleccionada no existe, no te pertenece o está inactiva.'); window.location.href='punto_emision_nuevo.php';</script>";
        exit();
    }

    try {
        $sql = "INSERT INTO punto_emision (
                    empresa_id, 
                    punto_emision, 
                    establecimiento, 
                    secuencial_factura, 
                    secuencial_nota_credito, 
                    secuencial_nota_debito, 
                    secuencial_comprobante_retencion, 
                    secuencial_liquidacion_compra, 
                    secuencial_guia_remision, 
                    descripcion,
                    iva
                ) VALUES (
                    :empresa_id, 
                    :punto_emision, 
                    :establecimiento,
                    :secuencial_factura, 
                    :secuencial_nota_credito, 
                    :secuencial_nota_debito, 
                    :secuencial_comprobante_retencion, 
                    :secuencial_liquidacion_compra, 
                    :secuencial_guia_remision, 
                    :descripcion,
                    :iva
                )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresa_id,
            ':punto_emision' => $punto_emision,
            ':establecimiento' => $establecimiento,
            ':secuencial_factura' => $secuencial_factura,
            ':secuencial_nota_credito' => $secuencial_nota_credito,
            ':secuencial_nota_debito' => $secuencial_nota_debito,
            ':secuencial_comprobante_retencion' => $secuencial_comprobante_retencion,
            ':secuencial_liquidacion_compra' => $secuencial_liquidacion_compra,
            ':secuencial_guia_remision' => $secuencial_guia_remision,
            ':descripcion' => $descripcion,
            ':iva' => $iva
        ]);

        echo "<script>alert('Punto de emisión creado exitosamente.'); window.location.href='punto_emision_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al crear el punto de emisión: " . addslashes($e->getMessage()) . "'); window.location.href='punto_emision_nuevo.php';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='punto_emision_lista.php';</script>";
}
?>