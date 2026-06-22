<?php
$id = (int)($_GET['id'] ?? 0);
if (!$id) die("ID inválido");

$base = __DIR__ . '/..';
require "$base/md_config/conexion.php";

$stmt = $pdo->prepare("SELECT cr.*, e.logo, e.razon_social, e.nombre_comercial, e.ruc, e.direccion, e.contribuyente_especial, e.obligado_contabilidad 
                        FROM comprobantes_retencion cr 
                        JOIN empresa e ON cr.empresa_id = e.id 
                        WHERE cr.id = ? AND cr.estado_xml = 'AUTORIZADO'");
$stmt->execute([$id]);
$cr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cr) die("No encontrado o no autorizado");

// Obtener detalles
$stmt_det = $pdo->prepare("SELECT * FROM detalle_retencion WHERE comprobante_retencion_id = ? ORDER BY id");
$stmt_det->execute([$id]);
$detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

// Cargar XML autorizado
$ruta_xml = "$base/md_facturacion/autorizacion/comprobantes/autorizados/fac_{$cr['numero_autorizacion']}.xml";
if (!file_exists($ruta_xml)) {
    // Intentar otros patrones
    $ruta_xml = "$base/md_facturacion/autorizacion/comprobantes/autorizados/{$cr['archivo_xml']}";
    if (!file_exists($ruta_xml)) {
        // Buscar cualquier XML autorizado de este comprobante
        $dir_aut = "$base/md_facturacion/autorizacion/comprobantes/autorizados/";
        $files = glob($dir_aut . "*RETENCION*{$cr['clave_acceso']}*.xml");
        if (!empty($files)) {
            $ruta_xml = $files[0];
        } else {
            die("XML autorizado no encontrado");
        }
    }
}

$xml = simplexml_load_file($ruta_xml);

require_once "$base/md_facturacion/autorizacion/librerias/TCPDF/tcpdf.php";
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Factheo');
$pdf->SetAuthor($cr['razon_social']);
$pdf->SetTitle('Comprobante de Retención');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

$num = str_pad($cr['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['secuencial'],9,'0',STR_PAD_LEFT);

// LOGO
$ruta_logo = "$base/md_empresa/logos/" . $cr['logo'];
if (file_exists($ruta_logo)) $pdf->Image($ruta_logo, 12, 10, 22, 0, '', '', 'C', false, 300);

// LEFT HEADER - Company info
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
$pdf->MultiCell(21, 0, (string)($xml->infoCompRetencion->contribuyenteEspecial ?? 'N/A'), 0, '', 0, 0, 57, 93, true);
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(55, 0, "OBLIGADO CONTABILIDAD: ", 0, '', 0, 0, 10, 97, true);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, (string)$xml->infoCompRetencion->obligadoContabilidad, 0, '', 0, 0, 57, 97, true);

// RIGHT HEADER
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(65, 0, "RUC:", 0, '', 0, 0, 86, 12, true);
$pdf->MultiCell(65, 0, "No. AUTORIZACIÓN:", 0, '', 0, 0, 86, 25, true);
$pdf->MultiCell(65, 0, "FECHA AUTORIZACIÓN:", 0, '', 0, 0, 86, 36, true);
$pdf->MultiCell(65, 0, "AMBIENTE:", 0, '', 0, 0, 86, 50, true);
$pdf->MultiCell(65, 0, "EMISIÓN:", 0, '', 0, 0, 86, 55, true);
$pdf->MultiCell(65, 0, "CLAVE ACCESO:", 0, '', 0, 0, 86, 70, true);
$pdf->SetFont('', 'B', 14);
$pdf->MultiCell(65, 0, "COMP. RETENCIÓN No.:", 0, '', 0, 0, 86, 17, true);

$pdf->SetFont('', '', 9);
$pdf->MultiCell(47, 0, (string)$xml->infoTributaria->ruc, 0, '', 0, 0, 151, 12, true);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(47, 6, $num, 0, '', 0, 0, 151, 17, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(113, 4, $cr['numero_autorizacion'], 0, 'C', 0, 0, 86, 30, true);
$pdf->MultiCell(47, 0, date('d/m/Y H:i:s', strtotime($cr['fecha_autorizacion'])), 0, '', 0, 0, 151, 36, true);
$ambiente_str = (string)$xml->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN';
$pdf->MultiCell(47, 0, $ambiente_str, 0, '', 0, 0, 151, 50, true);
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true);
$pdf->write1DBarcode($cr['numero_autorizacion'], 'C39E', 88, 75, 110, 15, 0.4);
$pdf->SetFont('', '', 7);
$pdf->MultiCell(113, 4, $cr['numero_autorizacion'], 0, 'C', 0, 0, 86, 92, true);

// CLIENTE / SUJETO RETENIDO
$pdf->Line(10, 101, 200, 101); $pdf->Line(10, 101, 10, 120); $pdf->Line(200, 101, 200, 120); $pdf->Line(10, 120, 200, 120);
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(50, 0, "Razón Social:", 0, '', 0, 0, 12, 103, true);
$pdf->MultiCell(25, 0, "Identificación:", 0, '', 0, 0, 138, 103, true);
$pdf->MultiCell(33, 0, "Fecha Emisión:", 0, '', 0, 0, 12, 114, true);
$pdf->SetFont('', '', 9);
$pdf->MultiCell(70, 10, (string)$xml->infoCompRetencion->razonSocialSujetoRetenido, 0, '', 0, 0, 62, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoCompRetencion->identificacionSujetoRetenido, 0, '', 0, 0, 163, 103, true);
$pdf->MultiCell(35, 0, (string)$xml->infoCompRetencion->fechaEmision, 0, '', 0, 0, 45, 114, true);
$pdf->SetFont('', 'B', 8);
$pdf->MultiCell(35, 0, "Período Fiscal: ", 0, '', 0, 0, 100, 114, true);
$pdf->SetFont('', '', 8);
$pdf->MultiCell(60, 0, (string)$xml->infoCompRetencion->periodoFiscal, 0, '', 0, 0, 150, 114, true);

// DOCUMENTOS SUSTENTO Y RETENCIONES
$pdf->SetY(122);
$pdf->SetFont('', 'B', 8);
$pdf->Cell(190, 6, 'DOCUMENTOS SUSTENTO Y RETENCIONES', 1, 1, 'C');

$totalRenta = 0; $totalIVA = 0; $totalISD = 0;
foreach ($xml->docsSustento->docSustento as $ds) {
    $numDoc = (string)$ds->numDocumento;
    $codDoc = (string)$ds->codDocNR;
    $monto = (float)$ds->montoTotal;
    $fecDoc = (string)$ds->fechaEmisionDocSustento;
    $numAut = (string)$ds->numAutDocumento;

    // Información del documento
    $pdf->SetFont('', 'B', 7);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell(15, 5, 'Doc:', 0, 0, 'L', true);
    $pdf->SetFont('', '', 7);
    $pdf->Cell(60, 5, $numDoc, 0, 0, 'L', true);
    $pdf->SetFont('', 'B', 7);
    $pdf->Cell(15, 5, 'Tipo:', 0, 0, 'L', true);
    $pdf->SetFont('', '', 7);
    $pdf->Cell(20, 5, $codDoc, 0, 0, 'L', true);
    $pdf->SetFont('', 'B', 7);
    $pdf->Cell(15, 5, 'Fecha:', 0, 0, 'L', true);
    $pdf->SetFont('', '', 7);
    $pdf->Cell(20, 5, $fecDoc, 0, 0, 'L', true);
    $pdf->SetFont('', 'B', 7);
    $pdf->Cell(15, 5, 'Monto:', 0, 0, 'L', true);
    $pdf->SetFont('', '', 7);
    $pdf->Cell(30, 5, '$'.number_format($monto,2), 0, 1, 'R', true);

    // Tabla retenciones del documento
    $pdf->SetFont('', 'B', 7);
    $pdf->SetFillColor(255, 243, 205);
    $pdf->Cell(30, 5, 'Impuesto', 1, 0, 'C', true);
    $pdf->Cell(25, 5, 'Código Ret.', 1, 0, 'C', true);
    $pdf->Cell(40, 5, 'Base Imponible', 1, 0, 'C', true);
    $pdf->Cell(20, 5, '% Retener', 1, 0, 'C', true);
    $pdf->Cell(30, 5, 'Valor Retenido', 1, 1, 'C', true);

    $pdf->SetFont('', '', 7);
    if (isset($ds->retenciones)) {
        foreach ($ds->retenciones->retencion as $ret) {
            $cod = (string)$ret->codigo;
            $codRet = (string)$ret->codigoRetencion;
            $baseRet = number_format((float)$ret->baseImponible, 2);
            $porcRet = number_format((float)$ret->porcentajeRetener, 2);
            $valRet = (float)$ret->valorRetenido;

            $impNombre = $cod == '1' ? 'Renta' : ($cod == '2' ? 'IVA' : 'ISD');
            if ($cod == '1') $totalRenta += $valRet;
            elseif ($cod == '2') $totalIVA += $valRet;
            elseif ($cod == '6') $totalISD += $valRet;

            $lblCod = $cod . ' - ' . $impNombre;
            $pdf->Cell(30, 5, $lblCod, 1, 0, 'C');
            $pdf->Cell(25, 5, $codRet, 1, 0, 'C');
            $pdf->Cell(40, 5, '$' . $baseRet, 1, 0, 'R');
            $pdf->Cell(20, 5, $porcRet . '%', 1, 0, 'C');
            $pdf->Cell(30, 5, '$' . number_format($valRet,2), 1, 1, 'R');
        }
    } else {
        $pdf->Cell(145, 5, 'Sin retenciones registradas', 1, 1, 'C');
    }

    $pdf->Ln(2);
}

// TOTALES
$totalGen = $totalRenta + $totalIVA + $totalISD;
$pdf->Ln(5);
$pdf->SetFont('', 'B', 9);
$pdf->Cell(130, 7, 'RESUMEN DE RETENCIONES', 1, 0, 'C');
$pdf->SetFont('', '', 8);
$pdf->Cell(30, 7, 'Total Renta', 1, 0, 'C');
$pdf->Cell(30, 7, '$'.number_format($totalRenta,2), 1, 1, 'R');
$pdf->Cell(130, 7, '', 0, 0, 'L');
$pdf->Cell(30, 7, 'Total IVA', 1, 0, 'C');
$pdf->Cell(30, 7, '$'.number_format($totalIVA,2), 1, 1, 'R');
$pdf->Cell(130, 7, '', 0, 0, 'L');
$pdf->Cell(30, 7, 'Total ISD', 1, 0, 'C');
$pdf->Cell(30, 7, '$'.number_format($totalISD,2), 1, 1, 'R');
$pdf->SetFont('', 'B', 10);
$pdf->Cell(130, 8, 'TOTAL RETENIDO', 1, 0, 'C');
$pdf->Cell(60, 8, '$'.number_format($totalGen,2), 1, 1, 'R');

// NÚMERO DE AUTORIZACIÓN AL PIE
$pdf->Ln(5);
$pdf->SetFont('', '', 7);
$pdf->Cell(190, 4, 'Número de Autorización: ' . $cr['numero_autorizacion'], 0, 1, 'C');
$pdf->Cell(190, 4, 'Fecha de Autorización: ' . date('d/m/Y H:i:s', strtotime($cr['fecha_autorizacion'])), 0, 1, 'C');

$pdf->Output("CR_{$num}.pdf", 'I');
