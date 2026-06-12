<?php
//chdir(dirname(__FILE__));
require('../../../md_config/conexion.php');

$factura_id = $_GET['id'] ?? 0;
if (!$factura_id) {
    $imprime = [0, 'ID inválido'];
    echo json_encode($imprime);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT clave_acceso, estado_xml FROM facturas WHERE id = ?");
    $stmt->execute([$factura_id]);
    $factura = $stmt->fetch();
    if (!$factura || $factura['estado_xml'] !== 'RECIBIDA') {
        $imprime = [0, 'Estado inválido'];
        echo json_encode($imprime);
        exit;
    }
    $clave_acceso = $factura['clave_acceso'];
} catch (Exception $e) {
    $imprime = [0, 'Error BD'];
    echo json_encode($imprime);
    exit;
}

$webAutoriza = "https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl";
$parametros = ["claveAccesoComprobante" => $clave_acceso];
$imprime = [];

try {
    $client = new SoapClient($webAutoriza, ['connection_timeout' => 30, 'cache_wsdl' => WSDL_CACHE_NONE]);
    $response = $client->autorizacionComprobante($parametros);
    $autorizacion = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion;
    $estado = $autorizacion->estado;
    if ($estado === 'AUTORIZADO') {
        $numero = $autorizacion->numeroAutorizacion;
        $fecha_xml = $autorizacion->fechaAutorizacion;

        // ✅ Conversión de formato de fecha
        $date = new DateTime($fecha_xml);
        $fecha = $date->format('Y-m-d H:i:s');

        $xml = $autorizacion->comprobante;
        $ruta = "../comprobantes/autorizados/fac_$numero.xml";
        file_put_contents($ruta, $xml);

        // ✅ Actualiza sin la columna que no existe (archivo_xml_autorizado)
        $pdo->prepare("UPDATE facturas SET estado_xml = 'AUTORIZADO', numero_autorizacion = ?, fecha_autorizacion = ?, autorizado_sri = 1 WHERE id = ?")
            ->execute([$numero, $fecha, $factura_id]);

        $imprime = [1, 'AUTORIZADO', $numero];
    } else {
        $mensaje = $autorizacion->mensajes->mensaje->mensaje ?? 'Rechazado';
        $imprime = [1, 'RECHAZADO', $mensaje];
        $pdo->prepare("UPDATE facturas SET estado_xml = 'DEVUELTA', observacion_sri = ? WHERE id = ?")
            ->execute([$mensaje, $factura_id]);
    }
} catch (SoapFault $e) {
    $imprime = [0, 'ERROR_CONEXION', $e->getMessage()];
}
echo json_encode($imprime);