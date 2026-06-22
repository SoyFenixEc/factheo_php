<?php
/**
 * enviar_guia.php
 * Envía XML firmado de Guía de Remisión al WS de Recepción del SRI
 * Adaptado de envio2.php para guias_remision
 */

require('../../../md_config/conexion.php');

$guia_id = $_GET['id'] ?? 0;
if (!$guia_id) {
    $imprime = [0, 'ID inválido'];
    echo json_encode($imprime);
    exit;
}

// Obtener guía
try {
    $stmt = $pdo->prepare("SELECT archivo_xml, estado_xml FROM guias_remision WHERE id = ?");
    $stmt->execute([$guia_id]);
    $guia = $stmt->fetch();

    if (!$guia) {
        $imprime = [0, 'Guía no encontrada'];
        echo json_encode($imprime);
        exit;
    }

    if ($guia['estado_xml'] !== 'FIRMADO') {
        $imprime = [0, 'Estado inválido: ' . $guia['estado_xml']];
        echo json_encode($imprime);
        exit;
    }

    $archivo_firmado = $guia['archivo_xml'];
} catch (Exception $e) {
    $imprime = [0, 'Error BD: ' . $e->getMessage()];
    echo json_encode($imprime);
    exit;
}

// Ruta del XML firmado
$ruta_xml = "../md_facturacion/autorizacion/comprobantes/firmados/$archivo_firmado";

if (!file_exists($ruta_xml)) {
    $imprime = [0, 'XML firmado no encontrado', $ruta_xml];
    echo json_encode($imprime);
    exit;
}

$ArchivoXML = file_get_contents($ruta_xml);
if (!$ArchivoXML) {
    $imprime = [0, 'No se pudo leer el XML'];
    echo json_encode($imprime);
    exit;
}

// WS Recepción SRI (Pruebas)
$webRecepcion = "https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl";

$parametros = array("xml" => $ArchivoXML);
$imprime = array();

try {
    $webServiceRecepcion = new SoapClient($webRecepcion, [
        'connection_timeout' => 30,
        'cache_wsdl' => WSDL_CACHE_NONE,
        'trace' => true,
        'soap_version' => SOAP_1_1
    ]);

    $validacion = $webServiceRecepcion->validarComprobante($parametros);
    $respuesta = $validacion->RespuestaRecepcionComprobante->estado;

    if ($respuesta === 'RECIBIDA') {
        $imprime[0] = 1;
        $imprime[1] = 'RECIBIDA';

        // Actualizar BD
        $pdo->prepare("UPDATE guias_remision SET estado_xml = 'RECIBIDA', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$guia_id]);

    } else {
        $imprime[0] = 1;
        $imprime[1] = 'DEVUELTA';

        $mensaje = '';
        if (isset($validacion->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes->mensaje)) {
            $msg = $validacion->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes->mensaje;
            $mensaje = trim($msg->mensaje ?? $msg->informacionAdicional ?? 'Error desconocido');
        }
        $imprime[2] = $mensaje;

        $pdo->prepare("UPDATE guias_remision SET estado_xml = 'DEVUELTA', observacion_sri = ?, fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$mensaje, $guia_id]);
    }

} catch (SoapFault $e) {
    $imprime[0] = 0;
    $imprime[1] = 'ERROR_CONEXION';
    $imprime[2] = $e->getMessage();
    error_log("SOAP Fault en enviar_guia: " . $e->getMessage());

} catch (Exception $e) {
    $imprime[0] = 0;
    $imprime[1] = 'ERROR_ENVIO';
    $imprime[2] = $e->getMessage();
}

echo json_encode($imprime);
