<?php
// verificar_clave.php
require('../md_config/conexion.php');

$factura_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT clave_acceso, ambiente_id FROM facturas WHERE id = ?");
$stmt->execute([$factura_id]);
$factura = $stmt->fetch();

if ($factura) {
    echo "Clave de acceso: " . $factura['clave_acceso'] . "<br>";
    echo "Longitud: " . strlen($factura['clave_acceso']) . "<br>";
    echo "Ambiente en BD: " . $factura['ambiente_id'] . "<br>";
    
    // Descomponer la clave
    $partes = [
        'fecha' => substr($factura['clave_acceso'], 0, 8),
        'tipo' => substr($factura['clave_acceso'], 8, 2),
        'ruc' => substr($factura['clave_acceso'], 10, 13),
        'ambiente' => substr($factura['clave_acceso'], 23, 1),
        'estab' => substr($factura['clave_acceso'], 24, 3),
        'pto_emi' => substr($factura['clave_acceso'], 27, 3),
        'secuencial' => substr($factura['clave_acceso'], 30, 9),
        'codigo_numerico' => substr($factura['clave_acceso'], 39, 8),
        'tipo_emision' => substr($factura['clave_acceso'], 47, 1),
        'digito_verificador' => substr($factura['clave_acceso'], 48, 1)
    ];
    
    echo "<pre>Partes de la clave: ";
    print_r($partes);
    echo "</pre>";
} else {
    echo "Factura no encontrada";
}