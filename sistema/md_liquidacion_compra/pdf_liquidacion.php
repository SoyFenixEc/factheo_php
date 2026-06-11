<?php
// RIDE PDF para Liquidación de Compra
chdir(dirname(__FILE__));
require('../md_config/conexion.php');

$id_liqui = $_GET['id'] ?? 0;
if (!$id_liqui) { die('ID no proporcionado.'); }

try {
    $stmt = $pdo->prepare("
        SELECT f.clave_acceso, f.numero_autorizacion, f.fecha_autorizacion,
               f.iva, e.logo, e.razon_social, e.nombre_comercial, e.ruc,
               e.direccion, e.contribuyente_especial, e.obligado_contabilidad
        FROM facturas f
        JOIN empresa e ON f.empresa_id = e.id
        WHERE f.id = ? AND f.estado_xml = 'AUTORIZADO' AND f.tipo_comprobante_id = '03'
    ");
    $stmt->execute([$id_liqui]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) { die('Liquidación no encontrada o no autorizada.'); }
} catch (Exception $e) { die('Error BD: ' . $e->getMessage()); }

$ruta_xml = "../md_facturacion/autorizacion/comprobantes/autorizados/fac_{$factura['numero_autorizacion']}.xml";
if (!file_exists($ruta_xml)) { die('XML autorizado no encontrado.'); }

$xml_content = file_get_contents($ruta_xml);
if (!$xml_content) { die('No se pudo leer XML.'); }

$xml = new SimpleXMLElement($xml_content);

require_once('../md_facturacion/autorizacion/librerias/TCPDF/tcpdf.php');

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('Factheo');
$pdf->SetTitle('Liquidación de Compra');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

// Logo
$ruta_logo = "../md_empresa/logos/" . $factura['logo'];
if (file_exists($ruta_logo)) {
    $pdf->Image($ruta_logo, 40, 10, 20, 20, '', '', 'C');
}

// Cabecera
$pdf->Line(85, 10, 200, 10);
$pdf->Line(85, 10, 85, 100);
$pdf->Line(85, 100, 200, 100);
$pdf->Line(10, 40, 83, 40);
$pdf->Line(10, 40, 10, 100);
$pdf->Line(10, 100, 83, 100);
$pdf->Line(83, 100, 83, 40);

$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(73, 15, $xml->infoTributaria->razonSocial, 0, 'C', 0, 0, 10, 41, true, 0, false, true, 15, 'M');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(15, 0, "Dir. Matriz: ", 0, '', 0, 0, 10, 56, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(58, 10, $xml->infoTributaria->dirMatriz, 0, '', 0, 0, 25, 56, true, 0, false, true, 10, 'T');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(15, 0, "Dir. Establecimiento: ", 0, '', 0, 0, 10, 68, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(58, 10, $xml->infoLiquidacionCompra->dirEstablecimiento ?? $xml->infoTributaria->dirMatriz, 0, '', 0, 0, 25, 68, true, 0, false, true, 10, 'T');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "OBLIGADO CONTABILIDAD: ", 0, '', 0, 0, 10, 97, true, 0, false, true, 0, 'T');
$pdf->MultiCell(50, 0, "CONTRIBUYENTE ESPECIAL No.: ", 0, '', 0, 0, 10, 93, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, $xml->infoTributaria->contribuyenteEspecial ?? 'N/A', 0, '', 0, 0, 61, 93, true, 0, false, true, 0, 'T');
$pdf->MultiCell(21, 0, $xml->infoLiquidacionCompra->obligadoContabilidad, 0, '', 0, 0, 61, 97, true, 0, false, true, 0, 'T');

// Datos del comprobante
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(65, 0, "RUC: ", 0, '', 0, 0, 86, 12, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "NRO AUTORIZACIÓN: ", 0, '', 0, 0, 86, 25, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "FECHA Y HORA AUTORIZACIÓN: ", 0, '', 0, 0, 86, 36, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "AMBIENTE: ", 0, '', 0, 0, 86, 50, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "EMISIÓN: ", 0, '', 0, 0, 86, 55, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "CLAVE DE ACCESO: ", 0, '', 0, 0, 86, 70, true, 0, false, true, 0, 'T');
$pdf->SetFont('', 'B', 14);
$pdf->MultiCell(65, 0, "LIQUIDACIÓN No.: ", 0, '', 0, 0, 86, 17, true, 0, false, true, 0, 'T');

$pdf->SetFont('', '', 9);
$pdf->MultiCell(47, 0, $xml->infoTributaria->ruc, 0, '', 0, 0, 151, 12, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 6, $xml->infoTributaria->estab . '-' . $xml->infoTributaria->ptoEmi . '-' . $xml->infoTributaria->secuencial, 0, '', 0, 0, 151, 17, true, 0, false, true, 6, 'M');
$pdf->MultiCell(113, 0, $factura['numero_autorizacion'], 0, 'C', 0, 0, 86, 30, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, date('d/m/Y H:i:s', strtotime($factura['fecha_autorizacion'])), 0, '', 0, 0, 151, 36, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, $xml->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN', 0, '', 0, 0, 151, 50, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true, 0, false, true, 0, 'T');
$pdf->write1DBarcode($factura['numero_autorizacion'], 'C39E', 88, 75, 110, 15, 0.4, array(), 'N');
$pdf->MultiCell(113, 0, $factura['numero_autorizacion'], 0, 'C', 0, 0, 86, 90, true, 0, false, true, 0, 'T');

// Datos del proveedor
$pdf->Line(10, 101, 200, 101);
$pdf->Line(10, 101, 10, 120);
$pdf->Line(200, 101, 200, 120);
$pdf->Line(10, 120, 200, 120);

$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(57, 0, "Razón Social/Nombres y Apellidos: ", 0, '', 0, 0, 12, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(25, 0, "Identificación: ", 0, '', 0, 0, 138, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(33, 0, "Fecha de Emisión: ", 0, '', 0, 0, 12, 114, true, 0, false, true, 0, 'T');

$pdf->SetFont('', '', 9);
$pdf->MultiCell(70, 10, $xml->infoLiquidacionCompra->razonSocialProveedor, 0, '', 0, 0, 67, 103, true, 0, false, true, 10, 'T');
$pdf->MultiCell(35, 0, $xml->infoLiquidacionCompra->identificacionProveedor, 0, '', 0, 0, 163, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(35, 0, $xml->infoLiquidacionCompra->fechaEmision, 0, '', 0, 0, 45, 114, true, 0, false, true, 0, 'T');

// Detalles
$pdf->SetY(122);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(22, 7, 'Cod. Principal', 1, 0, 'C');
$pdf->Cell(22, 7, 'Cod. Auxiliar', 1, 0, 'C');
$pdf->Cell(17, 7, 'Cantidad', 1, 0, 'C');
$pdf->Cell(70, 7, 'Descripción', 1, 0, 'C');
$pdf->Cell(20, 7, 'Pre. Unitario', 1, 0, 'C');
$pdf->Cell(19, 7, 'Desc.', 1, 0, 'C');
$pdf->Cell(20, 7, 'Total', 1, 0, 'C');
$pdf->Ln();

$pdf->SetFont('', '', 8);
foreach ($xml->detalles->detalle as $detalle) {
    $cod = (string)$detalle->codigoPrincipal;
    $cant = number_format((float)$detalle->cantidad, 2);
    $desc = (string)$detalle->descripcion;
    $precio = number_format((float)$detalle->precioUnitario, 2);
    $desc_amt = number_format((float)$detalle->descuento, 2);
    $total_det = number_format((float)$detalle->precioTotalSinImpuesto, 2);

    $altura = max(6, $pdf->getStringHeight(70, $desc, false, true, '', 1));
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    if ($y + $altura > 270) { $pdf->AddPage(); $y = $pdf->GetY(); }

    $pdf->MultiCell(22, $altura, $cod, 1, 'C', 0, 0, $x, $y);
    $pdf->MultiCell(22, $altura, '', 1, 'C', 0, 0, $x+22, $y);
    $pdf->MultiCell(17, $altura, $cant, 1, 'C', 0, 0, $x+44, $y);
    $pdf->MultiCell(70, $altura, $desc, 1, 'L', 0, 0, $x+61, $y);
    $pdf->MultiCell(20, $altura, $precio, 1, 'R', 0, 0, $x+131, $y);
    $pdf->MultiCell(19, $altura, $desc_amt, 1, 'R', 0, 0, $x+151, $y);
    $pdf->MultiCell(20, $altura, $total_det, 1, 'R', 0, 0, $x+170, $y);
    $pdf->SetY($y + $altura);
}

// Totales
$pdf->Ln(1);
$pdf->SetFont('', 'B', 10);
$pdf->Cell(131, 7, 'Información Adicional:', 'LTR', 0, 'C');
$pdf->SetFont('', '', 8);
$pdf->Cell(39, 7, 'SubTotal '. $factura['iva'].'%', 1, 0, 'C');
$pdf->Cell(20, 7, $xml->infoLiquidacionCompra->totalSinImpuestos, 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal 0%', 1, 0, 'C');
$pdf->Cell(20, 7, '0.00', 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal (Sin Impuesto)', 1, 0, 'C');
$pdf->Cell(20, 7, $xml->infoLiquidacionCompra->totalSinImpuestos, 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'IVA '.$factura['iva'].'%', 1, 0, 'C');
$valorIva = 0;
foreach ($xml->infoLiquidacionCompra->totalConImpuestos->totalImpuesto as $imp) {
    if ((string)$imp->codigo == '2') { $valorIva = (string)$imp->valor; break; }
}
$pdf->Cell(20, 7, $valorIva ?: '0.00', 1, 0, 'R');
$pdf->Ln();
$pdf->Cell(131, 7, '', 'LBR', 0, 'L');
$pdf->Cell(39, 7, 'VALOR TOTAL', 1, 0, 'C');
$pdf->Cell(20, 7, $xml->infoLiquidacionCompra->totalConImpuestos->totalImpuesto[0]->baseImponible ? 
    number_format((float)$xml->infoLiquidacionCompra->totalConImpuestos->totalImpuesto[0]->baseImponible + (float)$valorIva, 2, '.', '') : 
    '0.00', 1, 0, 'R');

$pdf->Output('liquidacion_' . $factura['numero_autorizacion'] . '.pdf', 'I');
