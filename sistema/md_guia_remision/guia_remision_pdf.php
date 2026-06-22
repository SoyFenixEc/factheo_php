<?php
/**
 * guia_remision_pdf.php
 * Genera el RIDE (Representación Impresa) de la Guía de Remisión usando TCPDF
 */
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID inválido");

$base = '/var/www/app.factheo.com/sistema';
require "$base/md_config/conexion.php";

$stmt = $pdo->prepare("SELECT g.*, e.logo, e.razon_social, e.nombre_comercial, e.ruc, e.direccion, e.contribuyente_especial, e.obligado_contabilidad 
                        FROM guias_remision g 
                        JOIN empresa e ON g.empresa_id = e.id 
                        WHERE g.id = ? AND g.estado_xml = 'AUTORIZADO'");
$stmt->execute([$id]);
$guia = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$guia) die("No encontrada o no autorizada");

$ruta_xml = "$base/md_facturacion/autorizacion/comprobantes/autorizados/gui_{$guia['numero_autorizacion']}.xml";
if (!file_exists($ruta_xml)) {
    // Intentar con nombre alternativo
    $ruta_xml = "$base/md_facturacion/autorizacion/comprobantes/autorizados/{$guia['archivo_xml']}";
    if (!file_exists($ruta_xml)) {
        die("XML autorizado no encontrado.");
    }
}
$xml = simplexml_load_file($ruta_xml);

require_once "$base/md_facturacion/autorizacion/librerias/TCPDF/tcpdf.php";
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Factheo');
$pdf->SetAuthor($guia['razon_social']);
$pdf->SetTitle('Guía de Remisión');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

$num = str_pad($guia['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($guia['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($guia['secuencial'],9,'0',STR_PAD_LEFT);

// ===== LOGO =====
$ruta_logo = "$base/md_empresa/logos/" . $guia['logo'];
if (file_exists($ruta_logo)) $pdf->Image($ruta_logo, 12, 10, 22, 0, '', '', 'C', false, 300);

// ===== LEFT HEADER BOX =====
$pdf->Line(85, 10, 200, 10); $pdf->Line(85, 10, 85, 100); $pdf->Line(85, 100, 200, 100); $pdf->Line(200, 10, 200, 100);
$pdf->Line(10, 40, 83, 40); $pdf->Line(10, 40, 10, 100); $pdf->Line(10, 100, 83, 100); $pdf->Line(83, 100, 83, 40);

// Razón Social
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(73, 15, (string)$xml->infoTributaria->razonSocial, 0, 'C', 0, 0, 10, 41, true);

$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(25, 0, "Dir. Matriz:", 0, '', 0, 0, 10, 60, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(50, 0, (string)$xml->infoTributaria->dirMatriz, 0, '', 0, 0, 35, 60, true);

// Contribuyente Especial
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "CONTRIBUYENTE ESPECIAL: ", 0, '', 0, 0, 10, 80, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, (string)($guia['contribuyente_especial'] ?? 'N/A'), 0, '', 0, 0, 57, 80, true);

// Obligado Contabilidad
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "OBLIGADO CONTABILIDAD: ", 0, '', 0, 0, 10, 84, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, (string)$guia['obligado_contabilidad'] ?? 'NO', 0, '', 0, 0, 57, 84, true);

// Dirección partida
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "Dir. Partida: ", 0, '', 0, 0, 10, 92, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(50, 0, htmlspecialchars(trim($guia['dir_partida'] ?? ''), ENT_NOQUOTES, 'UTF-8'), 0, '', 0, 0, 35, 92, true);

// ===== RIGHT HEADER =====
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(65, 0, "RUC:", 0, '', 0, 0, 86, 12, true);
$pdf->MultiCell(65, 0, "No. AUTORIZACIÓN:", 0, '', 0, 0, 86, 25, true);
$pdf->MultiCell(65, 0, "FECHA AUTORIZACIÓN:", 0, '', 0, 0, 86, 36, true);
$pdf->MultiCell(65, 0, "AMBIENTE:", 0, '', 0, 0, 86, 50, true);
$pdf->MultiCell(65, 0, "EMISIÓN:", 0, '', 0, 0, 86, 55, true);
$pdf->MultiCell(65, 0, "CLAVE ACCESO:", 0, '', 0, 0, 86, 70, true);
$pdf->SetFont('', 'B', 12);
$pdf->MultiCell(65, 0, "GUÍA DE REMISIÓN No.:", 0, '', 0, 0, 86, 17, true);

$pdf->SetFont('', '', 9);
$pdf->MultiCell(47, 0, (string)$xml->infoTributaria->ruc, 0, '', 0, 0, 151, 12, true);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(47, 6, $num, 0, '', 0, 0, 151, 17, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(113, 4, $guia['numero_autorizacion'], 0, 'C', 0, 0, 86, 30, true);
$pdf->MultiCell(47, 0, date('d/m/Y H:i:s', strtotime($guia['fecha_autorizacion'])), 0, '', 0, 0, 151, 36, true);
$ambiente = (string)$xml->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN';
$pdf->MultiCell(47, 0, $ambiente, 0, '', 0, 0, 151, 50, true);
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true);
$pdf->write1DBarcode($guia['numero_autorizacion'], 'C39E', 88, 75, 110, 15, 0.4);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(113, 4, $guia['numero_autorizacion'], 0, 'C', 0, 0, 86, 92, true);

// ===== TRANSPORTISTA =====
$pdf->Line(10, 101, 200, 101); $pdf->Line(10, 101, 10, 120); $pdf->Line(200, 101, 200, 120); $pdf->Line(10, 120, 200, 120);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(50, 0, "Transportista:", 0, '', 0, 0, 12, 103, true);
$pdf->MultiCell(25, 0, "RUC:", 0, '', 0, 0, 138, 103, true);
$pdf->MultiCell(33, 0, "Placa:", 0, '', 0, 0, 12, 114, true);
$pdf->SetFont('', '', 9);
$pdf->MultiCell(70, 10, (string)$xml->infoGuiaRemision->razonSocialTransportista, 0, '', 0, 0, 62, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoGuiaRemision->rucTransportista, 0, '', 0, 0, 163, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoGuiaRemision->placa, 0, '', 0, 0, 45, 114, true);
$pdf->SetFont('', 'B', 8);
$pdf->MultiCell(35, 0, "Fecha Inicio: ", 0, '', 0, 0, 100, 114, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(60, 0, (string)$xml->infoGuiaRemision->fechaIniTransporte, 0, '', 0, 0, 150, 114, true);

// ===== DESTINATARIO =====
$pdf->SetY(122);
$pdf->SetFont('', 'B', 9);
$pdf->Cell(190, 7, 'DATOS DEL DESTINATARIO', 1, 1, 'C');

$pdf->SetFont('', 'B', 8);
$pdf->Cell(40, 7, 'Razón Social:', 'L', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, (string)$xml->destinatarios->destinatario->razonSocialDestinatario, 0, 0);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(30, 7, 'Identificación:', 'L', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, (string)$xml->destinatarios->destinatario->identificacionDestinatario, 'R', 1);

$pdf->SetFont('', 'B', 8);
$pdf->Cell(40, 7, 'Dirección:', 'LB', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, (string)$xml->destinatarios->destinatario->dirDestinatario, 'B', 0);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(30, 7, 'Motivo:', 'LB', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, (string)$xml->destinatarios->destinatario->motivoTraslado, 'RB', 1);

// Ruta y Establecimiento destino
$pdf->SetFont('', 'B', 8);
$pdf->Cell(40, 7, 'Ruta:', 'L', 0);
$pdf->SetFont('', '', 8);
$ruta_str = isset($xml->destinatarios->destinatario->ruta) ? (string)$xml->destinatarios->destinatario->ruta : '-';
$pdf->Cell(60, 7, $ruta_str, 0, 0);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(30, 7, 'Cod. Estab. Destino:', 'L', 0);
$pdf->SetFont('', '', 8);
$cod_estab = isset($xml->destinatarios->destinatario->codEstabDestino) ? (string)$xml->destinatarios->destinatario->codEstabDestino : '-';
$pdf->Cell(60, 7, $cod_estab, 'R', 1);

// Documento sustento
$doc_sust = isset($xml->destinatarios->destinatario->numDocSustento) ? (string)$xml->destinatarios->destinatario->numDocSustento : '-';
$fecha_doc = isset($xml->destinatarios->destinatario->fechaEmisionDocSustento) ? (string)$xml->destinatarios->destinatario->fechaEmisionDocSustento : '-';
$pdf->SetFont('', 'B', 8);
$pdf->Cell(40, 7, 'Doc. Sustento:', 'LB', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, $doc_sust, 'B', 0);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(30, 7, 'Fecha Emisión Doc.:', 'LB', 0);
$pdf->SetFont('', '', 8);
$pdf->Cell(60, 7, $fecha_doc, 'RB', 1);

// ===== DETALLES TABLE =====
$pdf->Ln(2);
$w = [22, 22, 17, 80, 49]; // widths
$h = 7;
$pdf->SetFont('', 'B', 8);
$headers = ['Cod.Interno', 'Cod.Adic.', 'Cantidad', 'Descripción', 'Doc. Sustento'];
foreach ($headers as $i => $hdr) {
    $pdf->Cell($w[$i], $h, $hdr, 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('', '', 8);
foreach ($xml->destinatarios->destinatario->detalles->detalle as $det) {
    $codInt = (string)$det->codigoInterno;
    $codAd = isset($det->codigoAdicional) ? (string)$det->codigoAdicional : '';
    $desc = (string)$det->descripcion;
    $cant = number_format((float)$det->cantidad, 4);

    $row = [$codInt, $codAd, $cant, $desc, ''];
    $lh = max(6, $pdf->getStringHeight($w[3], $desc));
    if ($pdf->GetY() + $lh > 260) { $pdf->AddPage(); $y = $pdf->GetY(); }
    $x = $pdf->GetX(); $y = $pdf->GetY();
    foreach ($row as $i => $val) {
        $align = ($i == 2) ? 'R' : ($i == 4 ? 'C' : 'L');
        $pdf->MultiCell($w[$i], $lh, $val, 1, $align, 0, 0, $x, $y);
        $x += $w[$i];
    }
    $pdf->SetY($y + $lh);
}

// ===== INFO ADICIONAL (comentarios) =====
$pdf->Ln(3);
$comentarios = htmlspecialchars($guia['comentarios'] ?? '');
if (!empty($comentarios)) {
    $pdf->SetFont('', 'B', 8);
    $pdf->Cell(190, 6, 'Información Adicional', 1, 1, 'C');
    $pdf->SetFont('', '', 8);
    $pdf->MultiCell(190, 6, $comentarios, 1, 'L');
}

$pdf->Output("GR_{$num}.pdf", 'I');
