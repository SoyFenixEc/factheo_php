<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_POST['id'];
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

    // Verificar que el punto de emisión pertenece al usuario actual
    $sql_check = "SELECT pe.id FROM punto_emision pe JOIN empresa e ON pe.empresa_id = e.id WHERE pe.id = :id AND e.usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar este punto de emisión.'); window.location.href='punto_emision_lista.php';</script>";
        exit();
    } 

    // Verificar que la empresa esté activa
    $sql_empresa = "SELECT activa FROM empresa WHERE id = :empresa_id AND usuario_id = :usuario_id";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->bindParam(':empresa_id', $empresa_id, PDO::PARAM_INT);
    $stmt_empresa->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_empresa->execute();
    $empresa = $stmt_empresa->fetch();

    if (!$empresa || $empresa['activa'] != '1') {
        echo "<script>alert('No puedes actualizar este punto de emisión porque su empresa está inactiva.'); window.location.href='punto_emision_lista.php';</script>";
        exit();
    }

    try {
        $sql = "UPDATE punto_emision SET 
                empresa_id = :empresa_id,
                punto_emision = :punto_emision,
                establecimiento = :establecimiento,
                secuencial_factura = :secuencial_factura,
                secuencial_nota_credito = :secuencial_nota_credito,
                secuencial_nota_debito = :secuencial_nota_debito,
                secuencial_comprobante_retencion = :secuencial_comprobante_retencion,
                secuencial_liquidacion_compra = :secuencial_liquidacion_compra,
                secuencial_guia_remision = :secuencial_guia_remision,
                descripcion = :descripcion,
                iva = :iva
                WHERE id = :id";

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
            ':iva' => $iva,
            ':id' => $id
        ]);

        echo "<script>alert('Punto de emisión actualizado exitosamente.'); window.location.href='punto_emision_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar el punto de emisión: " . addslashes($e->getMessage()) . "'); window.location.href='punto_emision_editar.php?id=" . $id . "';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='punto_emision_lista.php';</script>";
}
?>