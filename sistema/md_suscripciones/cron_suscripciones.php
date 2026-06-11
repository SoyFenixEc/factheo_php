<?php
require('../md_config/conexion.php');

// 1. Verificar y expirar suscripciones
$sql_expirar = "UPDATE empresa e 
                JOIN suscripciones s ON e.id = s.empresa_id 
                SET e.suscripcion_activa = 0 
                WHERE s.fecha_expiracion < CURDATE() AND s.estado = 'activa'";
$pdo->exec($sql_expirar);

// 2. Activar suscripciones con comprobante verificado
$sql_activar = "UPDATE empresa e 
                JOIN suscripciones s ON e.id = s.empresa_id 
                SET e.suscripcion_activa = 1, 
                    e.fecha_expiracion_suscripcion = s.fecha_expiracion,
                    e.limite_facturas_mes = (SELECT max_facturas_mes FROM planes WHERE id = s.plan_id)
                WHERE s.estado = 'pendiente' AND s.comprobante IS NOT NULL";

// 3. Resetear contador de facturas mensuales el primer día de cada mes
if (date('j') === '1') {
    $sql_reset = "UPDATE empresa 
                  SET facturas_emitidas_mes = 0, 
                      ultimo_reseteo_mes = CURDATE()";
    $pdo->exec($sql_reset);
}

// 4. Notificar suscripciones que expiran en 7 días
$sql_notificar = "SELECT e.*, u.email 
                 FROM empresa e 
                 JOIN suscripciones s ON e.id = s.empresa_id 
                 JOIN usuarios u ON e.usuario_id = u.id 
                 WHERE s.fecha_expiracion = DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                 AND e.suscripcion_activa = 1";
// Aquí puedes agregar el código para enviar emails de recordatorio