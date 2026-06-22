<?php
// ================================================
// 1. CONFIGURACIÓN Y CONEXIÓN
// ================================================
chdir(dirname(__FILE__));
require('../../../md_config/conexion.php');

$id_factura = $_GET['id'] ?? 0;
if (!$id_factura) {
    die('ID de factura no proporcionado.');
}

// ================================================
// 2. OBTENER DATOS DE LA FACTURA Y EMPRESA (incluye logo)
// ================================================
try {
    $stmt = $pdo->prepare("
        SELECT 
            f.clave_acceso, 
            f.numero_autorizacion, 
            f.fecha_autorizacion,
            f.archivo_xml,
            f.estado_xml,
            f.xml_firmado,
            f.xml_generado,
            f.autorizado_sri,
            e.logo,
            f.iva,
            e.razon_social,
            e.nombre_comercial,
            e.ruc,
            e.direccion,
            e.contribuyente_especial,
            e.obligado_contabilidad
        FROM facturas f
        JOIN empresa e ON f.empresa_id = e.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) {
        die('Factura no encontrada.');
    }
} catch (Exception $e) {
    die('Error al consultar la base de datos: ' . $e->getMessage());
}

// ================================================
// 3. BUSCAR EL XML (prioridad: autorizado > firmado > generado)
// ================================================
$ruta_xml = null;
$numero_referencia = '';

if ($factura['autorizado_sri'] && $factura['numero_autorizacion']) {
    $ruta = "../comprobantes/autorizados/fac_{$factura['numero_autorizacion']}.xml";
    if (file_exists($ruta)) {
        $ruta_xml = $ruta;
        $numero_referencia = $factura['numero_autorizacion'];
    }
}

if (!$ruta_xml && $factura['xml_firmado'] && $factura['archivo_xml']) {
    $ruta = "../comprobantes/firmados/{$factura['archivo_xml']}";
    if (file_exists($ruta)) {
        $ruta_xml = $ruta;
        $numero_referencia = $factura['clave_acceso'];
    }
}

if (!$ruta_xml && $factura['xml_generado'] && $factura['archivo_xml']) {
    $ruta = "../comprobantes/generados/{$factura['archivo_xml']}";
    if (file_exists($ruta)) {
        $ruta_xml = $ruta;
        $numero_referencia = $factura['clave_acceso'];
    }
}

// Si no hay XML de ningún tipo, intentar con la clave de acceso
if (!$ruta_xml) {
    // Buscar cualquier XML en generados que coincida
    $archivos = glob("../comprobantes/generados/*{$factura['clave_acceso']}*.xml");
    if (!empty($archivos)) {
        $ruta_xml = $archivos[0];
        $numero_referencia = $factura['clave_acceso'];
    }
}

if (!$ruta_xml) {
    die('No se encontró ningún archivo XML para esta factura.');
}

// ================================================
// 4. CARGAR EL XML DEL COMPROBANTE
// ================================================
$xml_content = file_get_contents($ruta_xml);
if (!$xml_content) {
    die('No se pudo leer el archivo XML.');
}

$xmlComprobante = new SimpleXMLElement($xml_content);

// ================================================
// 5. CARGAR TCPDF Y CONFIGURAR PDF
// ================================================
require_once('../librerias/TCPDF/tcpdf.php');

$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('TCPDF');
$pdf->SetAuthor('Sistema de Facturación');
$pdf->SetTitle('Factura');
$pdf->SetSubject('Comprobante de Factura');

$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 30);
$pdf->AddPage();

// ================================================
// 6. RUTA ABSOLUTA DEL LOGO Y VERIFICACIÓN
// ================================================
$ruta_logo = "../../../md_empresa/logos/" . $factura['logo'];

// Verificar si el logo existe
if (file_exists($ruta_logo)) {
    $pdf->Image($ruta_logo, 40, 10, 20, 20, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
} else {
    // Opcional: usar logo por defecto
    $ruta_logo_default = "../../../md_empresa/logos/logo_default.jpg";
    if (file_exists($ruta_logo_default)) {
        $pdf->Image($ruta_logo_default, 10, 10, 60, 30, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
    }
}

// ================================================
// 7. CABECERA: LÍNEAS Y DATOS EMISOR
// ================================================
$pdf->Line(85, 10, 200, 10);
$pdf->Line(85, 10, 85, 100);
$pdf->Line(85, 100, 200, 100);
$pdf->Line(200, 10, 200, 100);

$pdf->Line(10, 40, 83, 40);
$pdf->Line(10, 40, 10, 100);
$pdf->Line(10, 100, 83, 100);
$pdf->Line(83, 100, 83, 40);

$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(73, 15, $xmlComprobante->infoTributaria->razonSocial, 0, 'C', 0, 0, 10, 41, true, 0, false, true, 15, 'M');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(15, 0, "Dirección Matriz: ", 0, '', 0, 0, 10, 56, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(58, 10, $xmlComprobante->infoTributaria->dirMatriz, 0, '', 0, 3, 25, 56, true, 0, false, true, 10, 'T');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(15, 0, "Dirección Sucursal: ", 0, '', 0, 0, 10, 68, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(58, 10, $xmlComprobante->infoFactura->dirEstablecimiento, 0, '', 0, 3, 25, 68, true, 0, false, true, 10, 'T');
$pdf->SetFont('', 'B', 7);
$pdf->MultiCell(50, 0, "OBLIGADO A LLEVAR CONTABILIDAD: ", 0, '', 0, 0, 10, 97, true, 0, false, true, 0, 'T');
$pdf->MultiCell(50, 0, "CONTRIBUYENTE ESPECIAL No.: ", 0, '', 0, 0, 10, 93, true, 0, false, true, 0, 'T');
$pdf->SetFont('', '', 7);
$pdf->MultiCell(21, 0, $xmlComprobante->infoTributaria->contribuyenteEspecial ?? 'N/A', 0, '', 0, 0, 61, 93, true, 0, false, true, 0, 'T');
$pdf->MultiCell(21, 0, $xmlComprobante->infoFactura->obligadoContabilidad, 0, '', 0, 0, 61, 97, true, 0, false, true, 0, 'T');

// ================================================
// 8. DATOS DE LA FACTURA
// ================================================
$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(65, 0, "RUC: ", 0, '', 0, 0, 86, 12, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "NÚMERO DE AUTORIZACIÓN: ", 0, '', 0, 0, 86, 25, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "FECHA Y HORA DE AUTORIZACIÓN: ", 0, '', 0, 0, 86, 36, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "AMBIENTE: ", 0, '', 0, 0, 86, 50, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "EMISIÓN: ", 0, '', 0, 0, 86, 55, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "CLAVE DE ACCESO: ", 0, '', 0, 0, 86, 70, true, 0, false, true, 0, 'T');
$pdf->SetFont('', 'B', 14);
$pdf->MultiCell(65, 0, "FACTURA No.: ", 0, '', 0, 0, 86, 17, true, 0, false, true, 0, 'T');

$pdf->SetFont('', '', 9);
$pdf->MultiCell(47, 0, $xmlComprobante->infoTributaria->ruc, 0, '', 0, 0, 151, 12, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 6, $xmlComprobante->infoTributaria->estab . '-' . $xmlComprobante->infoTributaria->ptoEmi . '-' . $xmlComprobante->infoTributaria->secuencial, 0, '', 0, 0, 151, 17, true, 0, false, true, 6, 'M');
$num_aut = $factura['numero_autorizacion'] ?? 'PENDIENTE DE AUTORIZACIÓN';
$fecha_aut = $factura['fecha_autorizacion'] ? date('d/m/Y H:i:s', strtotime($factura['fecha_autorizacion'])) : ($factura['estado_xml'] ?? 'PENDIENTE');
$pdf->MultiCell(113, 0, $num_aut, 0, 'C', 0, 0, 86, 30, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, $fecha_aut, 0, '', 0, 0, 151, 36, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, $xmlComprobante->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN', 0, '', 0, 0, 151, 50, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true, 0, false, true, 0, 'T');
// Código de barras solo si está autorizado
$barcode_data = $factura['numero_autorizacion'] ?? $factura['clave_acceso'];
if ($factura['numero_autorizacion']) {
    $pdf->write1DBarcode($barcode_data, 'C39E', 88, 75, 110, 15, 0.4, array(), 'N');
}
$pdf->MultiCell(113, 0, $barcode_data, 0, 'C', 0, 0, 86, 90, true, 0, false, true, 0, 'T');

// ================================================
// 9. DATOS DEL CLIENTE
// ================================================
$pdf->Line(10, 101, 200, 101);
$pdf->Line(10, 101, 10, 120);
$pdf->Line(200, 101, 200, 120);
$pdf->Line(10, 120, 200, 120);

$pdf->SetFont('', 'B', 9);
$pdf->MultiCell(57, 0, "Razón Social/Nombres y Apellidos: ", 0, '', 0, 0, 12, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(25, 0, "Identificación: ", 0, '', 0, 0, 138, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(33, 0, "Fecha de Emisión: ", 0, '', 0, 0, 12, 114, true, 0, false, true, 0, 'T');

$pdf->SetFont('', '', 9);
$pdf->MultiCell(70, 10, $xmlComprobante->infoFactura->razonSocialComprador, 0, '', 0, 0, 67, 103, true, 0, false, true, 10, 'T');
$pdf->MultiCell(35, 0, $xmlComprobante->infoFactura->identificacionComprador, 0, '', 0, 0, 163, 103, true, 0, false, true, 0, 'T');
$pdf->MultiCell(35, 0, $xmlComprobante->infoFactura->fechaEmision, 0, '', 0, 0, 45, 114, true, 0, false, true, 0, 'T');

// ================================================
// 10. DETALLES DE LA FACTURA (CORREGIDO)
// ================================================
$pdf->SetY(122);
$pdf->SetFont('', 'B', 8);

// Encabezados de la tabla
$pdf->Cell(22, 7, 'Cod. Principal', 1, 0, 'C');
$pdf->Cell(22, 7, 'Cod. Auxiliar', 1, 0, 'C');
$pdf->Cell(17, 7, 'Cantidad', 1, 0, 'C');
$pdf->Cell(70, 7, 'Descripción', 1, 0, 'C');
$pdf->Cell(20, 7, 'Pre. Unitario', 1, 0, 'C');
$pdf->Cell(19, 7, 'Desc.', 1, 0, 'C');
$pdf->Cell(20, 7, 'Total', 1, 0, 'C');
$pdf->Ln();

$pdf->SetFont('', '', 8);
$contador = 0;
$y_position = $pdf->GetY();

foreach ($xmlComprobante->detalles->detalle as $detalle) {
    $codigoPrincipal = (string)$detalle->codigoPrincipal;
    $cantidad = number_format((float)$detalle->cantidad, 2);
    $descripcion = (string)$detalle->descripcion;
    $precioUnitario = number_format((float)$detalle->precioUnitario, 2);
    $descuento = number_format((float)$detalle->descuento, 2);
    $precioTotal = number_format((float)$detalle->precioTotalSinImpuesto, 2);
    
    // Calcular la altura necesaria para la descripción
    $descripcion_width = 70;
    $descripcion_height = $pdf->getStringHeight($descripcion_width, $descripcion, false, true, '', 1);
    $line_height = max(6, $descripcion_height); // Mínimo 6mm de altura
    
    // Verificar si necesitamos nueva página
    if ($pdf->GetY() + $line_height > 270) { // 270 es aproximadamente el límite inferior de A4
        $pdf->AddPage();
        $y_position = $pdf->GetY();
        
        // Redibujar encabezados en nueva página
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
        $y_position = $pdf->GetY();
    }
    
    $x_position = $pdf->GetX();
    $start_y = $pdf->GetY();
    
    // Codigo Principal
    $pdf->MultiCell(22, $line_height, $codigoPrincipal, 1, 'C', 0, 0, $x_position, $start_y);
    $x_position += 22;
    
    // Codigo Auxiliar (vacío)
    $pdf->MultiCell(22, $line_height, '', 1, 'C', 0, 0, $x_position, $start_y);
    $x_position += 22;
    
    // Cantidad
    $pdf->MultiCell(17, $line_height, $cantidad, 1, 'C', 0, 0, $x_position, $start_y);
    $x_position += 17;
    
    // Descripción (usa MultiCell para texto largo)
    $pdf->MultiCell(70, $line_height, $descripcion, 1, 'L', 0, 0, $x_position, $start_y);
    $x_position += 70;
    
    // Precio Unitario
    $pdf->MultiCell(20, $line_height, $precioUnitario, 1, 'R', 0, 0, $x_position, $start_y);
    $x_position += 20;
    
    // Descuento
    $pdf->MultiCell(19, $line_height, $descuento, 1, 'R', 0, 0, $x_position, $start_y);
    $x_position += 19;
    
    // Total
    $pdf->MultiCell(20, $line_height, $precioTotal, 1, 'R', 0, 0, $x_position, $start_y);
    
    // Mover a la siguiente línea
    $pdf->SetY($start_y + $line_height);
    $contador++;
}

// ================================================
// 11. TOTALES
// ================================================
$pdf->Ln(1);
$pdf->SetFont('', 'B', 10);
$pdf->Cell(131, 7, 'Información Adicional:', 'LTR', 0, 'C');
$pdf->SetFont('', '', 8);
$pdf->Cell(39, 7, 'SubTotal '. $factura['iva'].'%', 1, 0, 'C');
$pdf->Cell(20, 7, $xmlComprobante->infoFactura->totalSinImpuestos, 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '          Correo Electrónico:', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal 0%', 1, 0, 'C');
$pdf->Cell(20, 7, '0.00', 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '          Dirección:', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal (No Obj. IVA)', 1, 0, 'C');
$pdf->Cell(20, 7, '0.00', 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal (Excento IVA)', 1, 0, 'C');
$pdf->Cell(20, 7, '0.00', 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'SubTotal (Sin Impuesto)', 1, 0, 'C');
$pdf->Cell(20, 7, $xmlComprobante->infoFactura->totalSinImpuestos, 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'Descuentos', 1, 0, 'C');
$pdf->Cell(20, 7, $xmlComprobante->infoFactura->totalDescuento, 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '', 'LR', 0, 'L');
$pdf->Cell(39, 7, 'IVA '.$factura['iva'].'%', 1, 0, 'C');
$pdf->Cell(20, 7, $xmlComprobante->infoFactura->totalConImpuestos->totalImpuesto->valor, 1, 0, 'R');
$pdf->Ln();

$pdf->Cell(131, 7, '', 'LBR', 0, 'L');
$pdf->Cell(39, 7, 'VALOR TOTAL', 1, 0, 'C');
$pdf->Cell(20, 7, $xmlComprobante->infoFactura->importeTotal, 1, 0, 'R');

// ================================================
// 12. SALIDA DEL PDF
// ================================================
$nombre_pdf = 'factura_' . ($factura['numero_autorizacion'] ?? $factura['clave_acceso'] ?? $id_factura) . '.pdf';
$pdf->Output($nombre_pdf, 'I');