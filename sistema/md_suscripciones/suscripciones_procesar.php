<?php
require('../../md_autenticacion/sesion.php');
require('../../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['id'];
    $empresa_id = $_POST['empresa_id'];
    $plan_id = $_POST['plan_id'];
    $metodo_pago = $_POST['metodo_pago'];
    
    // Obtener información del plan
    $sql_plan = "SELECT * FROM planes WHERE id = ?";
    $stmt_plan = $pdo->prepare($sql_plan);
    $stmt_plan->execute([$plan_id]);
    $plan = $stmt_plan->fetch();

    // Calcular monto final con comisión si es PayPhone
    $monto_final = $plan['precio_total'];
    $comision_payphone = 0;
    
    if ($metodo_pago === 'payphone') {
        $comision = 0.05; // 5%
        $iva = 0.15; // 15%
        $comision_con_iva = $comision * (1 + $iva);
        $comision_payphone = $plan['precio_total'] * $comision_con_iva;
        $monto_final = $plan['precio_total'] + $comision_payphone;
    }

    // Manejar comprobante de pago
    $comprobante = null;
    if ($metodo_pago === 'transferencia' && isset($_FILES['comprobante'])) {
        $archivo = $_FILES['comprobante'];
        $extension = pathinfo($archivo['name'], PATHINFO_EXTENSION);
        $nombreUnico = uniqid('comprobante_') . '.' . $extension;
        $rutaDestino = '../md_pagos/comprobantes/' . $nombreUnico;
        
        if (move_uploaded_file($archivo['tmp_name'], $rutaDestino)) {
            $comprobante = $nombreUnico;
        }
    }

    // Calcular fechas
    $fecha_inicio = date('Y-m-d');
    $fecha_expiracion = date('Y-m-d', strtotime("+{$plan['duracion_dias']} days"));

    try {
        $pdo->beginTransaction();

        // Insertar suscripción
        $sql_suscripcion = "INSERT INTO suscripciones (usuario_id, empresa_id, plan_id, fecha_inicio, fecha_expiracion, 
                           monto_pagado, metodo_pago, comision_payphone, comprobante, estado) 
                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendiente')";
        $stmt_suscripcion = $pdo->prepare($sql_suscripcion);
        $stmt_suscripcion->execute([
            $usuario_id, $empresa_id, $plan_id, $fecha_inicio, $fecha_expiracion, 
            $monto_final, $metodo_pago, $comision_payphone, $comprobante
        ]);

        // Si es PayPhone, redirigir al procesamiento de pago
        if ($metodo_pago === 'payphone') {
            $suscripcion_id = $pdo->lastInsertId();
            header("Location: ../md_pagos/pago_payphone.php?suscripcion_id=$suscripcion_id&monto=$monto_final");
            exit;
        }

        $pdo->commit();

        $_SESSION['mensaje_exito'] = 'Suscripción procesada. Será activada después de la verificación del pago.';
        header('Location: suscripciones_lista.php');
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['mensaje_error'] = 'Error al procesar la suscripción: ' . $e->getMessage();
        header('Location: suscripciones_nueva.php');
        exit;
    }
}