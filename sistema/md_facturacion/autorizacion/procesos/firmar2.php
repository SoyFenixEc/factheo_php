<?php
/**
 * firmar.php - Versión final, compatible con tu estructura
 * Ruta: /sistema/md_facturacion/autorizacion/procesos/firmar.php
 */

// ================================================
// 1. CONFIGURACIÓN DE RUTAS (basado en tu estructura)
// ================================================

require("../../../md_config/conexion.php");

$factura_id = $_GET['id'] ?? 0;
if (!$factura_id) {
    die(json_encode(['ok' => false, 'error' => 'ID inválido']));
}

    $sql = "SELECT * FROM facturas fac
            JOIN empresa e ON fac.empresa_id = e.id
            WHERE fac.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $factura_id]);
    $empresa = $stmt->fetch();
	
	$certificado = $empresa['certificado'];
	$clave_certificado = $empresa['clave_certificado'];

// === Rutas absolutas ===
$jar_path        = "../librerias/QuijoteLuiFirmador/dist/QuijoteLuiFirmador.jar";
$ruta_generados  = "../comprobantes/generados";
$ruta_firmados   = "../comprobantes/firmados";
$ruta_errores    = "../comprobantes/errores";
$ruta_librerias  = "../librerias";
$cert_file       = "../../../md_empresa/certificados/$certificado";
$cert_pass       = "$clave_certificado";

// === Nombre del archivo XML (solo nombre) ===
$stmt = $pdo->prepare("SELECT archivo_xml FROM facturas WHERE id = ?");
$stmt->execute([$factura_id]);
$factura = $stmt->fetch();

if (!$factura || empty($factura['archivo_xml'])) {
    die(json_encode(['ok' => false, 'error' => 'Factura no encontrada']));
}

$nombre_xml = $factura['archivo_xml']; // Ej: FACTURA_16_25082025010950.xml

// === Validaciones ===
if (!file_exists($jar_path)) {
    die(json_encode(['ok' => false, 'error' => 'JAR no encontrado', 'ruta' => $jar_path]));
}

if (!file_exists("$ruta_generados/$nombre_xml")) {
    die(json_encode(['ok' => false, 'error' => 'XML no existe', 'archivo' => $nombre_xml]));
}

if (!file_exists($cert_file)) {
    die(json_encode(['ok' => false, 'error' => 'Certificado no encontrado', 'archivo' => 'firma.p12']));
}

if (!is_dir($ruta_firmados) || !is_writable($ruta_firmados)) {
    die(json_encode(['ok' => false, 'error' => 'Carpeta firmados no accesible']));
}

// === Comando corregido (rutas absolutas, formato correcto) ===
$cmd = "/opt/jdk8u422-b05/bin/java -jar " .
    escapeshellarg($jar_path) . " " .
    escapeshellarg($nombre_xml) . " " .
    escapeshellarg("$ruta_generados/") . " " .
    escapeshellarg("$ruta_firmados/") . " " .
    escapeshellarg($ruta_librerias) . " " .
    escapeshellarg($cert_file) . " " .
    escapeshellarg($cert_pass) . " " .
    "GEN 2>&1";

// Ejecutar
exec($cmd, $salida, $codigo);

// Log
$log = [
    'comando' => $cmd,
    'salida' => $salida,
    'codigo' => $codigo
];
file_put_contents("$ruta_errores/log_firma_$factura_id.txt", print_r($log, true));

// Validar
if ($codigo !== 0) {
    die(json_encode([
        'ok' => false,
        'error' => 'Fallo en ejecución de Java',
        'codigo' => $codigo,
        'salida' => $salida
    ]));
}

$ruta_firmado = "$ruta_firmados/$nombre_xml";
if (!file_exists($ruta_firmado)) {
    die(json_encode([
        'ok' => false,
        'error' => 'El archivo firmado no se generó',
        'esperado' => $ruta_firmado,
        'salida' => $salida
    ]));
}

// Actualizar BD
try {
    $pdo->prepare("
        UPDATE facturas 
        SET archivo_xml = ?, 
            xml_firmado = 1, 
            estado_xml = 'FIRMADO' 
        WHERE id = ?
    ")->execute([$nombre_xml, $factura_id]);
} catch (Exception $e) {
    error_log("Error al actualizar BD: " . $e->getMessage());
}

// Éxito
echo json_encode([
    'ok' => true,
    'mensaje' => 'XML firmado correctamente',
    'archivo' => $nombre_xml,
    'factura_id' => $factura_id
]);