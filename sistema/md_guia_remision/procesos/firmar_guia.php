<?php
/**
 * firmar_guia.php
 * Firma XML de Guía de Remisión usando QuijoteLuiFirmador (XAdES_BES)
 * Adaptado de firmar2.php de facturación, pero leyendo desde guias_remision
 */

require("../../../md_config/conexion.php");

$guia_id = $_GET['id'] ?? 0;
if (!$guia_id) {
    die(json_encode(['ok' => false, 'error' => 'ID inválido']));
}

// Obtener guía + empresa para certificado
$sql = "SELECT g.*, e.certificado, e.clave_certificado
        FROM guias_remision g
        JOIN empresa e ON g.empresa_id = e.id
        WHERE g.id = :id";
$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $guia_id]);
$guia = $stmt->fetch();

if (!$guia) {
    die(json_encode(['ok' => false, 'error' => 'Guía no encontrada']));
}

$certificado = $guia['certificado'];
$clave_certificado = $guia['clave_certificado'];

// === Rutas absolutas (compartidas con facturación) ===
$jar_path        = __DIR__ . "/../../md_facturacion/autorizacion/librerias/QuijoteLuiFirmador/dist/QuijoteLuiFirmador.jar";
$ruta_generados  = __DIR__ . "/../../md_facturacion/autorizacion/comprobantes/generados";
$ruta_firmados   = __DIR__ . "/../../md_facturacion/autorizacion/comprobantes/firmados";
$ruta_errores    = __DIR__ . "/../../md_facturacion/autorizacion/comprobantes/errores";
$ruta_librerias  = __DIR__ . "/../../md_facturacion/autorizacion/librerias";
$cert_file       = __DIR__ . "/../../md_empresa/certificados/$certificado";
$cert_pass       = "$clave_certificado";

// === Nombre del archivo XML ===
$nombre_xml = $guia['archivo_xml'];

if (empty($nombre_xml)) {
    die(json_encode(['ok' => false, 'error' => 'Archivo XML no encontrado en BD']));
}

// === Validaciones ===
if (!file_exists($jar_path)) {
    die(json_encode(['ok' => false, 'error' => 'JAR no encontrado', 'ruta' => $jar_path]));
}

if (!file_exists("$ruta_generados/$nombre_xml")) {
    die(json_encode(['ok' => false, 'error' => 'XML no existe en generados', 'archivo' => $nombre_xml]));
}

if (!file_exists($cert_file)) {
    die(json_encode(['ok' => false, 'error' => 'Certificado no encontrado', 'archivo' => 'firma.p12']));
}

if (!is_dir($ruta_firmados) || !is_writable($ruta_firmados)) {
    die(json_encode(['ok' => false, 'error' => 'Carpeta firmados no accesible']));
}

// === Detectar Java automáticamente ===
$java_paths = [
    '/opt/jdk8u422-b05/bin/java',
    '/usr/lib/jvm/java-21-openjdk-amd64/bin/java',
    '/usr/lib/jvm/java-25-openjdk-amd64/bin/java',
    '/usr/lib/jvm/java-11-openjdk-amd64/bin/java',
    '/usr/lib/jvm/zulu-7-amd64/bin/java',
    '/usr/lib/jvm/zulu-8-amd64/bin/java',
    '/usr/lib/jvm/jdk8/bin/java',
    '/usr/lib/jvm/jdk-8/bin/java',
    '/usr/lib/jvm/java-8-oracle/bin/java',
    '/usr/lib/jvm/default-java/bin/java',
];
$java_bin = null;
foreach ($java_paths as $path) {
    if (file_exists($path) && is_executable($path)) {
        $java_bin = $path;
        break;
    }
}
if (!$java_bin) {
    $which_output = [];
    exec('which java 2>/dev/null', $which_output, $which_code);
    $which = trim(implode('', $which_output));
    if ($which && file_exists($which) && is_executable($which)) {
        $java_bin = $which;
    }
}
if (!$java_bin) {
    $java_bin = 'java';
}

// Flags JVM para compatibilidad con Java 9+
$jvm_flags = '';
$java_version_output = [];
exec(escapeshellcmd($java_bin) . ' -version 2>&1', $java_version_output, $java_version_code);
$java_version_str = implode("\n", $java_version_output);
preg_match('/(\d+)\.(\d+)\./', $java_version_str, $matches);
if (!empty($matches)) {
    $major = (int)$matches[1];
    if ($major >= 9) {
        $jvm_flags = '--add-exports java.xml/com.sun.org.apache.xerces.internal.dom=ALL-UNNAMED --add-opens java.xml/com.sun.org.apache.xerces.internal.dom=ALL-UNNAMED --add-opens java.base/java.lang=ALL-UNNAMED';
    }
}

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
file_put_contents("$ruta_errores/log_firma_guia_$guia_id.txt", print_r($log, true));

// Validar resultado
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

// Actualizar BD (guias_remision)
try {
    $pdo->prepare("
        UPDATE guias_remision 
        SET archivo_xml = ?, 
            xml_firmado = 1, 
            estado_xml = 'FIRMADO',
            fecha_actualizacion = NOW()
        WHERE id = ?
    ")->execute([$nombre_xml, $guia_id]);
} catch (Exception $e) {
    error_log("Error al actualizar guias_remision BD: " . $e->getMessage());
}

// Éxito
echo json_encode([
    'ok' => true,
    'mensaje' => 'XML Guía de Remisión firmado correctamente',
    'archivo' => $nombre_xml,
    'guia_id' => $guia_id
]);
