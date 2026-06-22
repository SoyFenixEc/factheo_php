<?php
/**
 * autorizar_guia.php
 * Consulta la autorización de Guía de Remisión al WS del SRI
 * Adaptado de autoriza2.php para guias_remision
 */

require('../../../md_config/conexion.php');

$guia_id = $_GET['id'] ?? 0;
if (!$guia_id) {
    $imprime = [0, 'ID inválido'];
    echo json_encode($imprime);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT clave_acceso, estado_xml FROM guias_remision WHERE id = ?");
    $stmt->execute([$guia_id]);
    $guia = $stmt->fetch();
    if (!$guia || $guia['estado_xml'] !== 'RECIBIDA') {
        $imprime = [0, 'Estado inválido: ' . ($guia['estado_xml'] ?? 'sin datos')];
        echo json_encode($imprime);
        exit;
    }
    $clave_acceso = $guia['clave_acceso'];
} catch (Exception $e) {
    $imprime = [0, 'Error BD: ' . $e->getMessage()];
    echo json_encode($imprime);
    exit;
}

$webAutoriza = "https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl";
$parametros = ["claveAccesoComprobante" => $clave_acceso];
$imprime = [];

// Esperar 3 segundos por si el SRI aún está procesando
sleep(3);

try {
    $client = new SoapClient($webAutoriza, [
        'connection_timeout' => 60,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'soap_version' => SOAP_1_1,
        'trace' => true
    ]);
    $response = $client->autorizacionComprobante($parametros);

    if (!isset($response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion)) {
        $imprime = [0, 'ERROR_RESPUESTA', 'Estructura inesperada del SRI'];
        echo json_encode($imprime);
        exit;
    }

    $autorizacion = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
    $estado = $autorizacion->estado;

    if ($estado === 'AUTORIZADO') {
        $numero = $autorizacion->numeroAutorizacion;
        $fecha_xml = $autorizacion->fechaAutorizacion;

        $date = new DateTime($fecha_xml);
        $fecha = $date->format('Y-m-d H:i:s');

        $xml = $autorizacion->comprobante;
        $ruta = "../md_facturacion/autorizacion/comprobantes/autorizados/gui_$numero.xml";
        file_put_contents($ruta, $xml);

        $pdo->prepare("UPDATE guias_remision SET estado_xml = 'AUTORIZADO', numero_autorizacion = ?, fecha_autorizacion = ?, autorizado_sri = 1, fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$numero, $fecha, $guia_id]);

        $imprime = [1, 'AUTORIZADO', $numero];

    } elseif ($estado === 'RECIBIDA') {
        $imprime = [0, 'EN_PROCESO', 'El SRI aún está procesando el comprobante'];
    } else {
        $mensaje = '';
        if (isset($autorizacion->mensajes->mensaje)) {
            $msg = $autorizacion->mensajes->mensaje;
            $mensaje = $msg->mensaje ?? $msg->informacionAdicional ?? 'Rechazado';
        }
        $imprime = [1, 'RECHAZADO', $mensaje ?: $estado];
        $pdo->prepare("UPDATE guias_remision SET estado_xml = 'DEVUELTA', observacion_sri = ?, fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$mensaje, $guia_id]);
    }
} catch (SoapFault $e) {
    error_log("[Factheo] SOAP Error autorizar_guia: " . $e->getMessage() . " - Guía #$guia_id");
    $imprime = [0, 'ERROR_CONEXION', $e->getMessage()];
} catch (Exception $e) {
    error_log("[Factheo] Error autorizar_guia: " . $e->getMessage() . " - Guía #$guia_id");
    $imprime = [0, 'ERROR_GENERAL', $e->getMessage()];
}
echo json_encode($imprime);
