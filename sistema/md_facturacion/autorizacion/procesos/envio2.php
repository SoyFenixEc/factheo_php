<?php
/**
 * envio.php - Versión corregida del script comprado
 * Envía el XML firmado al SRI (web service de recepción)
 */

// ================================================
// 1. CONFIGURACIÓN Y CONEXIÓN
// ================================================

require('../../../md_config/conexion.php');

$factura_id = $_GET['id'] ?? 0;
if (!$factura_id) {
    $imprime = [0, 'ID inválido'];
    echo json_encode($imprime);
    exit;
}

// ================================================
// 2. OBTENER DATOS DE LA FACTURA
// ================================================
try {
    $stmt = $pdo->prepare("SELECT archivo_xml, estado_xml FROM facturas WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch();

    if (!$factura) {
        $imprime = [0, 'Factura no encontrada'];
        echo json_encode($imprime);
        exit;
    }

    if ($factura['estado_xml'] !== 'FIRMADO') {
        $imprime = [0, 'Estado inválido: ' . $factura['estado_xml']];
        echo json_encode($imprime);
        exit;
    }

    $archivo_firmado = $factura['archivo_xml'];
} catch (Exception $e) {
    $imprime = [0, 'Error BD: ' . $e->getMessage()];
    echo json_encode($imprime);
    exit;
}

// ================================================
// 3. RUTA DEL XML FIRMADO
// ================================================
$ruta_xml = "../comprobantes/firmados/$archivo_firmado";

if (!file_exists($ruta_xml)) {
    $imprime = [0, 'XML firmado no encontrado'];
    echo json_encode($imprime);
    exit;
}

// Leer el contenido del XML
$ArchivoXML = file_get_contents($ruta_xml);
if (!$ArchivoXML) {
    $imprime = [0, 'No se pudo leer el XML'];
    echo json_encode($imprime);
    exit;
}

// ================================================
// 4. CONFIGURACIÓN DEL SRI (PRUEBAS)
// ================================================
//$webRecepcion = "https://celcer.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl"; // Pruebas
$webRecepcion = "https://cel.sri.gob.ec/comprobantes-electronicos-ws/RecepcionComprobantesOffline?wsdl"; // Producción

$parametros = array("xml" => $ArchivoXML);
$imprime = array();

// ================================================
// 5. ENVIAR AL SRI
// ================================================
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
        $pdo->prepare("UPDATE facturas SET estado_xml = 'RECIBIDA', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$factura_id]);

    } else {
        $imprime[0] = 1;
        $imprime[1] = 'DEVUELTA';

        // Extraer mensaje de error
        $mensaje = '';
        if (isset($validacion->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes->mensaje)) {
            $msg = $validacion->RespuestaRecepcionComprobante->comprobantes->comprobante->mensajes->mensaje;
            $mensaje = trim($msg->mensaje ?? $msg->informacionAdicional ?? 'Error desconocido');
        }
        $imprime[2] = $mensaje;

        // Actualizar BD
        $pdo->prepare("UPDATE facturas SET estado_xml = 'DEVUELTA', observacion_sri = ? WHERE id = ?")
            ->execute([$mensaje, $factura_id]);
    }

} catch (SoapFault $e) {
    $imprime[0] = 0;
    $imprime[1] = 'ERROR_CONEXION';
    $imprime[2] = $e->getMessage();

    // Log de error
    error_log("SOAP Fault en envio.php: " . $e->getMessage());

} catch (Exception $e) {
    $imprime[0] = 0;
    $imprime[1] = 'ERROR_ENVIO';
    $imprime[2] = $e->getMessage();
}

// ================================================
// 6. RESPUESTA
// ================================================
echo json_encode($imprime);