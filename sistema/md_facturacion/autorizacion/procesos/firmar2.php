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

// === Detectar Java automáticamente ===
// QuijoteLuiFirmador es compatible con Java 8 (máximo). Java 9+ requiere --add-exports.
// Priorizamos JDK 8 y Zulu 7/8 antes que Java moderno.
$java_paths = [
    '/opt/jdk8u422-b05/bin/java',           // Kali dev (JDK 8)
    '/usr/lib/jvm/zulu-8-amd64/bin/java',   // Zulu 8
    '/usr/lib/jvm/zulu-7-amd64/bin/java',   // Producción (Zulu 7)
    '/usr/lib/jvm/jdk8/bin/java',
    '/usr/lib/jvm/jdk-8/bin/java',
    '/usr/lib/jvm/java-8-oracle/bin/java',
    '/usr/lib/jvm/java-11-openjdk-amd64/bin/java',
    '/usr/lib/jvm/java-21-openjdk-amd64/bin/java',
    '/usr/lib/jvm/java-25-openjdk-amd64/bin/java',
    '/usr/lib/jvm/default-java/bin/java',
];
$java_bin = null;
foreach ($java_paths as $path) {
    if (file_exists($path) && is_executable($path)) {
        $java_bin = $path;
        break;
    }
}
// Fallback: buscar en PATH del sistema con which
if (!$java_bin) {
    $which = trim(shell_exec('which java 2>/dev/null') ?? '');
    if ($which && file_exists($which) && is_executable($which)) {
        $java_bin = $which;
    }
}
// Último recurso: confiar en que el PATH del shell lo resuelva
if (!$java_bin) {
    $java_bin = 'java';
}

// Flags JVM para compatibilidad con Java 9+ (ignorados en Java ≤8)
$jvm_flags = '--add-exports java.xml/com.sun.org.apache.xerces.internal.dom=ALL-UNNAMED --add-opens java.xml/com.sun.org.apache.xerces.internal.dom=ALL-UNNAMED --add-opens java.base/java.lang=ALL-UNNAMED';

// === Comando ===
$cmd = escapeshellcmd($java_bin) . " $jvm_flags -jar " .
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
    'java_bin' => $java_bin,
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
        'java_bin' => $java_bin,
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