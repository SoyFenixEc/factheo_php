<?php
// ================================================
// PDF TICKET - Versión para impresoras térmicas (mPOS / Epson)
// ================================================

chdir(dirname(__FILE__));
require('../../../md_config/conexion.php');

$id_factura = $_GET['id'] ?? 0;
if (!$id_factura) die('ID de factura no proporcionado.');

// ================================================
// OBTENER DATOS DE LA FACTURA
// ================================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.clave_acceso, f.numero_autorizacion, f.fecha_autorizacion,
            f.archivo_xml, f.estado_xml, f.xml_firmado, f.xml_generado,
            f.autorizado_sri, f.total, f.iva, f.descuento, f.fecha_emision,
            f.secuencial,
            e.logo, e.razon_social, e.nombre_comercial, e.ruc,
            e.direccion, e.contribuyente_especial, e.obligado_contabilidad
        FROM facturas f
        JOIN empresa e ON f.empresa_id = e.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) die('Factura no encontrada.');
} catch (Exception $e) {
    die('Error BD: ' . $e->getMessage());
}

// ================================================
// BUSCAR XML
// ================================================
$ruta_xml = null;
if ($factura['autorizado_sri'] && $factura['numero_autorizacion']) {
    $ruta = "../comprobantes/autorizados/fac_{$factura['numero_autorizacion']}.xml";
    if (file_exists($ruta)) $ruta_xml = $ruta;
}
if (!$ruta_xml && $factura['xml_firmado'] && $factura['archivo_xml']) {
    $ruta = "../comprobantes/firmados/{$factura['archivo_xml']}";
    if (file_exists($ruta)) $ruta_xml = $ruta;
}
if (!$ruta_xml && $factura['xml_generado'] && $factura['archivo_xml']) {
    $ruta = "../comprobantes/generados/{$factura['archivo_xml']}";
    if (file_exists($ruta)) $ruta_xml = $ruta;
}
if (!$ruta_xml) {
    $archivos = glob("../comprobantes/generados/*{$factura['clave_acceso']}*.xml");
    if (!empty($archivos)) $ruta_xml = $archivos[0];
}
if (!$ruta_xml) die('No se encontró el XML.');

$xml = new SimpleXMLElement(file_get_contents($ruta_xml));

// ================================================
// CONFIGURAR TCPDF - TAMAÑO TICKET (80mm ancho)
// ================================================
require_once('../librerias/TCPDF/tcpdf.php');

// Definir tamaño personalizado: 80mm de ancho, alto dinámico
$ancho_mm = 80; // 80mm de papel térmico
$formato_ticket = array($ancho_mm, 200); // alto inicial, AutoPageBreak agrandará

$pdf = new TCPDF('P', 'mm', $formato_ticket, true, 'UTF-8', false);
$pdf->SetCreator('Factheo');
$pdf->SetAuthor('Factheo');
$pdf->SetTitle('Ticket');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(3, 3, 3);
$pdf->SetAutoPageBreak(true, 5);
$pdf->AddPage();

// Variables de layout
$left = 3;
$center = $ancho_mm / 2;
$content_w = $ancho_mm - 6; // 74mm útiles

// ================================================
// CABECERA - LOGO + RAZÓN SOCIAL
// ================================================
$pdf->SetFont('', 'B', 10);
$pdf->MultiCell($content_w, 0, strtoupper(trim((string)$xml->infoTributaria->razonSocial)), 0, 'C', 0, 1, $left);

if (!empty($xml->infoTributaria->nombreComercial)) {
    $pdf->SetFont('', '', 7);
    $pdf->MultiCell($content_w, 0, strtoupper(trim((string)$xml->infoTributaria->nombreComercial)), 0, 'C', 0, 1, $left);
}

$pdf->SetFont('', '', 7);
$pdf->MultiCell($content_w, 0, "RUC: " . $xml->infoTributaria->ruc, 0, 'C', 0, 1, $left);

if (!empty($xml->infoTributaria->dirMatriz)) {
    $pdf->MultiCell($content_w, 0, html_entity_decode(trim((string)$xml->infoTributaria->dirMatriz)), 0, 'C', 0, 1, $left);
}

// Línea separadora
$pdf->SetFont('', '', 6);
$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// DATOS DEL COMPROBANTE
// ================================================
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell($content_w, 0, "FACTURA No. " . $xml->infoTributaria->estab . "-" . $xml->infoTributaria->ptoEmi . "-" . $xml->infoTributaria->secuencial, 0, 'C', 0, 1, $left);

$pdf->SetFont('', '', 7);
$fecha = (string)$xml->infoFactura->fechaEmision;
$fecha_formateada = date('d/m/Y H:i', strtotime(str_replace('/', '-', $fecha)));
$pdf->MultiCell($content_w, 0, "Fecha: " . $fecha_formateada, 0, 'C', 0, 1, $left);

$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// DATOS DEL CLIENTE
// ================================================
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell($content_w, 0, "CLIENTE: " . strtoupper(trim((string)$xml->infoFactura->razonSocialComprador)), 0, 'L', 0, 1, $left);
$pdf->SetFont('', '', 7);
$pdf->MultiCell($content_w, 0, "RUC/CI: " . $xml->infoFactura->identificacionComprador, 0, 'L', 0, 1, $left);

if (!empty($xml->infoFactura->direccionComprador)) {
    $pdf->MultiCell($content_w, 0, "Direc: " . html_entity_decode(trim((string)$xml->infoFactura->direccionComprador)), 0, 'L', 0, 1, $left);
}

$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// DETALLES (Productos)
// ================================================
$pdf->SetFont('', 'B', 7);
// Cabecera de columnas
$col_cant = 10;  // Cantidad
$col_desc = $content_w - $col_cant - 18 - 18; // Descripción
$col_pu = 18;  // Precio Unitario
$col_total = 18; // Total

$pdf->Cell($col_cant, 0, 'Cant', 0, 0, 'L');
$pdf->Cell($col_desc, 0, 'Producto', 0, 0, 'L');
$pdf->Cell($col_pu, 0, 'P.Unit', 0, 0, 'R');
$pdf->Cell($col_total, 0, 'Total', 0, 1, 'R');

$pdf->SetFont('', '', 7);

foreach ($xml->detalles->detalle as $det) {
    $desc = html_entity_decode(trim((string)$det->descripcion));
    $cant = number_format((float)$det->cantidad, 2, '.', '');
    $pu = number_format((float)$det->precioUnitario, 2, '.', '');
    $total_item = number_format((float)$det->precioTotalSinImpuesto, 2, '.', '');

    $pdf->Cell($col_cant, 0, $cant, 0, 0, 'L');
    $pdf->Cell($col_desc, 0, substr($desc, 0, 22), 0, 0, 'L');
    $pdf->Cell($col_pu, 0, $pu, 0, 0, 'R');
    $pdf->Cell($col_total, 0, $total_item, 0, 1, 'R');
}

$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// TOTALES
// ================================================
$pdf->SetFont('', '', 7);

// Subtotal
foreach ($xml->infoFactura->totalConImpuestos->totalImpuesto as $imp) {
    $cod = (string)$imp->codigo;
    $codPorc = (string)$imp->codigoPorcentaje;
    $base = (string)$imp->baseImponible;
    $valor = (string)$imp->valor;

    if ($cod == '2') { // IVA
        $tarifa_nombre = match ($codPorc) {
            '0' => 'IVA 0%',
            '2' => 'IVA 12%',
            '3' => 'IVA 14%',
            '4' => 'IVA 15%',
            '5' => 'IVA 5%',
            '6' => 'No Objeto',
            '7' => 'Exento',
            default => "IVA $codPorc%"
        };
        $pdf->Cell($content_w - 18, 0, "Subtotal $tarifa_nombre:", 0, 0, 'R');
        $pdf->Cell(18, 0, number_format((float)$base, 2, '.', ''), 0, 1, 'R');
        $pdf->Cell($content_w - 18, 0, "IVA $tarifa_nombre:", 0, 0, 'R');
        $pdf->Cell(18, 0, number_format((float)$valor, 2, '.', ''), 0, 1, 'R');
    }
}

// Descuento
$descuento = (float)$xml->infoFactura->totalDescuento;
if ($descuento > 0) {
    $pdf->Cell($content_w - 18, 0, "Descuento:", 0, 0, 'R');
    $pdf->Cell(18, 0, number_format($descuento, 2, '.', ''), 0, 1, 'R');
}

// Propina
$propina = (float)$xml->infoFactura->propina;
if ($propina > 0) {
    $pdf->Cell($content_w - 18, 0, "Propina:", 0, 0, 'R');
    $pdf->Cell(18, 0, number_format($propina, 2, '.', ''), 0, 1, 'R');
}

// TOTAL
$pdf->SetFont('', 'B', 10);
$pdf->Cell($content_w - 18, 0, "TOTAL USD:", 0, 0, 'R');
$pdf->Cell(18, 0, number_format((float)$xml->infoFactura->importeTotal, 2, '.', ''), 0, 1, 'R');

$pdf->SetFont('', '', 7);
$pdf->Cell($content_w, 0, str_repeat('=', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// FORMA DE PAGO
// ================================================
foreach ($xml->infoFactura->pagos->pago as $pago) {
    $pdf->Cell($content_w - 18, 0, "Forma de Pago:", 0, 0, 'R');
    $pdf->Cell(18, 0, number_format((float)$pago->total, 2, '.', ''), 0, 1, 'R');
}

$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// AUTORIZACIÓN
// ================================================
$pdf->SetFont('', '', 6);
$num_aut = $factura['numero_autorizacion'] ?: $factura['clave_acceso'];
$pdf->MultiCell($content_w, 0, "No. Autorizacion:", 0, 'L', 0, 1, $left);
$pdf->SetFont('', 'B', 6);
$pdf->MultiCell($content_w, 0, $num_aut, 0, 'L', 0, 1, $left);

$pdf->SetFont('', '', 6);
if ($factura['fecha_autorizacion']) {
    $pdf->MultiCell($content_w, 0, "Fecha Autorizacion: " . date('d/m/Y H:i:s', strtotime($factura['fecha_autorizacion'])), 0, 'L', 0, 1, $left);
}

// Clave de acceso
$pdf->MultiCell($content_w, 0, "Clave de Acceso:", 0, 'L', 0, 1, $left);
$pdf->SetFont('', 'B', 6);
$pdf->MultiCell($content_w, 0, chunk_word($factura['clave_acceso'], 13, ' '), 0, 'L', 0, 1, $left);

$pdf->SetFont('', '', 6);
$pdf->Cell($content_w, 0, str_repeat('-', intval($content_w / 1.5)), 0, 1, 'C');

// ================================================
// CÓDIGO DE BARRAS (opcional)
// ================================================
$style = array(
    'position' => '',
    'align' => 'C',
    'stretch' => false,
    'fitwidth' => true,
    'cellfitalign' => '',
    'border' => false,
    'padding' => 1,
    'fgcolor' => array(0,0,0),
    'bgcolor' => false,
    'text' => true,
    'font' => 'helvetica',
    'fontsize' => 6,
    'stretchtext' => 4
);
try {
    $pdf->write1DBarcode($num_aut, 'C128', $left, '', $content_w, 10, 0.4, $style, '');
} catch (Exception $e) {
    // Si falla el código de barras, continuar
}
$pdf->Ln(1);

// ================================================
// PIE - LEYENDAS
// ================================================
$pdf->SetFont('', '', 6);
$pdf->MultiCell($content_w, 0, "Documento Tributario Electronico", 0, 'C', 0, 1, $left);

if ((string)$xml->infoTributaria->ambiente == '1') {
    $pdf->SetFont('', 'B', 8);
    $pdf->MultiCell($content_w, 0, "*** AMBIENTE DE PRUEBAS ***", 0, 'C', 0, 1, $left);
    $pdf->SetFont('', '', 6);
    $pdf->MultiCell($content_w, 0, "SIN VALIDEZ TRIBUTARIA", 0, 'C', 0, 1, $left);
}

// RIMPE / regímenes
if (isset($infoAdicional)) {
    foreach ($xml->infoAdicional->campoAdicional as $campo) {
        $nombre = trim((string)$campo['nombre']);
        if ($nombre == 'REGIMEN') {
            $pdf->MultiCell($content_w, 0, strtoupper(trim((string)$campo)), 0, 'C', 0, 1, $left);
        }
    }
}

$pdf->SetFont('', '', 5);
$pdf->MultiCell($content_w, 0, "Gracias por su preferencia", 0, 'C', 0, 1, $left);

// ================================================
// OUTPUT
// ================================================
$pdf->Output("Ticket_$id_factura.pdf", 'I');

// ================================================
// FUNCIÓN AUXILIAR
// ================================================
function chunk_word($str, $len, $glue) {
    return trim(chunk_split($str, $len, $glue));
}
