<?php
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID inválido");

require_once __DIR__ . '/../md_config/constants.php';
$base = RUTA_SISTEMA;
require "$base/md_config/conexion.php";

$stmt = $pdo->prepare("SELECT f.*, e.logo, e.razon_social, e.nombre_comercial, e.ruc, e.direccion, e.contribuyente_especial, e.obligado_contabilidad FROM facturas f JOIN empresa e ON f.empresa_id = e.id WHERE f.id = ? AND f.tipo_comprobante_id = '05' AND f.estado_xml = 'AUTORIZADO'");
$stmt->execute([$id]);
$nc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$nc) die("No encontrada o no autorizada");

$ruta_xml = "$base/md_facturacion/autorizacion/comprobantes/autorizados/fac_{$nc['numero_autorizacion']}.xml";
if (!file_exists($ruta_xml)) die("XML no encontrado");
$xml = simplexml_load_file($ruta_xml);

require_once "$base/md_facturacion/autorizacion/librerias/TCPDF/tcpdf.php";
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Factheo');
$pdf->SetAuthor($nc['razon_social']);
$pdf->SetTitle('Nota de Débito');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

$num = str_pad($nc['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['secuencial'],9,'0',STR_PAD_LEFT);

// LOGO
$ruta_logo = "$base/md_empresa/logos/" . $nc['logo'];
if (file_exists($ruta_logo)) $pdf->Image($ruta_logo, 12, 10, 22, 0, '', '', 'C', false, 300);

// LEFT HEADER
$pdf->Line(85, 10, 200, 10); $pdf->Line(85, 10, 85, 100); $pdf->Line(85, 100, 200, 100); $pdf->Line(200, 10, 200, 100);
$pdf->Line(10, 40, 83, 40); $pdf->Line(10, 40, 10, 100); $pdf->Line(10, 100, 83, 100); $pdf->Line(83, 100, 83, 40);
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(73, 15, (string)$xml->infoTributaria->razonSocial, 0, 'C', 0, 0, 10, 41, true);
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(25, 0, "Dir. Matriz:", 0, '', 0, 0, 10, 60, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(50, 0, (string)$xml->infoTributaria->dirMatriz, 0, '', 0, 0, 35, 60, true);
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "CONTRIBUYENTE ESPECIAL: ", 0, '', 0, 0, 10, 93, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, (string)($xml->infoNotaDebito->contribuyenteEspecial ?? 'N/A'), 0, '', 0, 0, 57, 93, true);
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "OBLIGADO CONTABILIDAD: ", 0, '', 0, 0, 10, 97, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, (string)$xml->infoNotaDebito->obligadoContabilidad, 0, '', 0, 0, 57, 97, true);

// RIGHT HEADER
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(65, 0, "RUC:", 0, '', 0, 0, 86, 12, true);
$pdf->MultiCell(65, 0, "No. AUTORIZACIÓN:", 0, '', 0, 0, 86, 25, true);
$pdf->MultiCell(65, 0, "FECHA AUTORIZACIÓN:", 0, '', 0, 0, 86, 36, true);
$pdf->MultiCell(65, 0, "AMBIENTE:", 0, '', 0, 0, 86, 50, true);
$pdf->MultiCell(65, 0, "EMISIÓN:", 0, '', 0, 0, 86, 55, true);
$pdf->MultiCell(65, 0, "CLAVE ACCESO:", 0, '', 0, 0, 86, 70, true);
$pdf->SetFont('', 'B', 14);
$pdf->MultiCell(65, 0, "NOTA CRÉDITO No.:", 0, '', 0, 0, 86, 17, true);

$pdf->SetFont('', '', 9);
$pdf->MultiCell(47, 0, (string)$xml->infoTributaria->ruc, 0, '', 0, 0, 151, 12, true);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(47, 6, $num, 0, '', 0, 0, 151, 17, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(113, 4, $nc['numero_autorizacion'], 0, 'C', 0, 0, 86, 30, true);
$pdf->MultiCell(47, 0, date('d/m/Y H:i:s', strtotime($nc['fecha_autorizacion'])), 0, '', 0, 0, 151, 36, true);
$ambiente = (string)$xml->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN';
$pdf->MultiCell(47, 0, $ambiente, 0, '', 0, 0, 151, 50, true);
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true);
$pdf->write1DBarcode($nc['numero_autorizacion'], 'C39E', 88, 75, 110, 15, 0.4);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(113, 4, $nc['numero_autorizacion'], 0, 'C', 0, 0, 86, 92, true);

// CLIENTE
$pdf->Line(10, 101, 200, 101); $pdf->Line(10, 101, 10, 120); $pdf->Line(200, 101, 200, 120); $pdf->Line(10, 120, 200, 120);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(50, 0, "Razón Social:", 0, '', 0, 0, 12, 103, true);
$pdf->MultiCell(25, 0, "Identificación:", 0, '', 0, 0, 138, 103, true);
$pdf->MultiCell(33, 0, "Fecha Emisión:", 0, '', 0, 0, 12, 114, true);
$pdf->SetFont('', '', 9);
$pdf->MultiCell(70, 10, (string)$xml->infoNotaDebito->razonSocialComprador, 0, '', 0, 0, 62, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoNotaDebito->identificacionComprador, 0, '', 0, 0, 163, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoNotaDebito->fechaEmision, 0, '', 0, 0, 45, 114, true);
$pdf->SetFont('', 'B', 8);
$pdf->MultiCell(35, 0, "Factura Modificada: ", 0, '', 0, 0, 100, 114, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(60, 0, (string)$xml->infoNotaDebito->numDocModificado, 0, '', 0, 0, 150, 114, true);

// DETALLES TABLE
$pdf->SetY(122);
$w = [22, 22, 17, 70, 20, 19, 20]; // widths
$h = 7;
$pdf->SetFont('', 'B', 8);
$headers = ['Cod.Interno', 'Cod.Adic.', 'Cantidad', 'Descripción', 'P.Unitario', 'Desc.', 'Total'];
foreach ($headers as $i=>$hdr) {
    $pdf->Cell($w[$i], $h, $hdr, 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('', '', 8);
foreach ($xml->detalles->detalle as $det) {
    $row = [
        (string)$det->codigoInterno,
        '',
        number_format((float)$det->cantidad, 2),
        (string)$det->descripcion,
        number_format((float)$det->precioUnitario, 2),
        number_format((float)$det->descuento, 2),
        number_format((float)$det->precioTotalSinImpuesto, 2)
    ];
    $lh = max(6, $pdf->getStringHeight(70, $row[3]));
    if ($pdf->GetY() + $lh > 260) { $pdf->AddPage(); $y = $pdf->GetY(); }
    $x = $pdf->GetX(); $y = $pdf->GetY();
    foreach ($row as $i=>$val) {
        $align = ($i >= 2) ? 'R' : 'C';
        $pdf->MultiCell($w[$i], $lh, $val, 1, $align, 0, 0, $x, $y);
        $x += $w[$i];
    }
    $pdf->SetY($y + $lh);
}

// TOTALES
$pdf->Ln(2);
$iva_val = 0;
foreach ($xml->infoNotaDebito->totalConImpuestos->totalImpuesto as $ti) {
    if ((string)$ti->codigo == '2') $iva_val = (string)$ti->valor;
}
$pdf->SetFont('', 'B', 10);
$pdf->Cell(131, 7, 'Motivo:', 'LTR', 0, 'C');
$pdf->SetFont('', '', 8);
$pdf->Cell(39, 7, 'Subtotal', 1, 0, 'C');
$pdf->Cell(20, 7, (string)$xml->infoNotaDebito->totalSinImpuestos, 1, 0, 'R');
$pdf->Ln();
$pdf->SetFont('', '', 8);
$pdf->Cell(131, 7, '     ' . (string)$xml->infoNotaDebito->motivo, 'LR', 0, 'L');
$pdf->Cell(39, 7, 'IVA ' . $nc['iva'] . '%', 1, 0, 'C');
$pdf->Cell(20, 7, number_format($iva_val, 2), 1, 0, 'R');
$pdf->Ln();
$pdf->SetFont('', 'B', 9);
$pdf->Cell(131, 7, '', 'LBR', 0, 'L');
$pdf->Cell(39, 7, 'VALOR MODIFICACIÓN', 1, 0, 'C');
$pdf->Cell(20, 7, (string)$xml->infoNotaDebito->valorModificacion, 1, 0, 'R');

$pdf->Output("NC_{$num}.pdf", 'I');
