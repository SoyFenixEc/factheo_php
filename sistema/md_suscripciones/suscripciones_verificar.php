<?php
function verificarSuscripcionActiva($empresa_id) {
    global $pdo;
    
    $sql = "SELECT e.suscripcion_activa, e.fecha_expiracion_suscripcion, 
                   e.facturas_emitidas_mes, e.limite_facturas_mes,
                   p.nombre as plan_nombre
            FROM empresa e 
            LEFT JOIN planes p ON e.plan_id = p.id 
            WHERE e.id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch();

    if (!$empresa || !$empresa['suscripcion_activa']) {
        return [
            'activa' => false, 
            'mensaje' => 'Suscripción no activa. Por favor renueva tu suscripción.',
            'codigo' => 'SUSCRIPCION_INACTIVA'
        ];
    }

    if (strtotime($empresa['fecha_expiracion_suscripcion']) < time()) {
        return [
            'activa' => false, 
            'mensaje' => 'Suscripción expirada. Por favor renueva tu suscripción.',
            'codigo' => 'SUSCRIPCION_EXPIRADA'
        ];
    }

    if ($empresa['facturas_emitidas_mes'] >= $empresa['limite_facturas_mes']) {
        return [
            'activa' => false, 
            'mensaje' => 'Límite mensual de facturas alcanzado (' . $empresa['limite_facturas_mes'] . ').',
            'codigo' => 'LIMITE_FACTURAS'
        ];
    }

    return [
        'activa' => true,
        'plan' => $empresa['plan_nombre'],
        'facturas_restantes' => $empresa['limite_facturas_mes'] - $empresa['facturas_emitidas_mes']
    ];
}

// Usar en cada archivo que necesite verificación
// require('../md_suscripciones/suscripciones_verificar.php');
// $verificacion = verificarSuscripcionActiva($empresa_id);