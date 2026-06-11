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
        WHERE f.id = ? AND f.estado_xml = 'AUTORIZADO'
    ");
    $stmt->execute([$id_factura]);
    $factura = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura) {
        die('Factura no encontrada o no autorizada.');
    }
} catch (Exception $e) {
    die('Error al consultar la base de datos: ' . $e->getMessage());
}

// ================================================
// 3. RUTA DEL XML AUTORIZADO
// ================================================
$ruta_xml = "../comprobantes/autorizados/fac_{$factura['numero_autorizacion']}.xml";

if (!file_exists($ruta_xml)) {
    die('Archivo XML autorizado no encontrado: ' . $ruta_xml);
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
    //$pdf->Image($ruta_logo, 10, 10, 60, 30, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
	$pdf->Image($ruta_logo, 40, 10, 20, 20, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
} else {
    // Opcional: usar logo por defecto
    $ruta_logo_default = "../../../md_empresa/logos/logo_default.jpg";
    if (file_exists($ruta_logo_default)) {
        $pdf->Image($ruta_logo_default, 10, 10, 60, 30, '', '', 'C', false, 300, '', false, false, 0, false, false, false);
    }
    // Si no hay logo, continúa sin imagen
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
$pdf->MultiCell(113, 0, $factura['numero_autorizacion'], 0, 'C', 0, 0, 86, 30, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, date('d/m/Y H:i:s', strtotime($factura['fecha_autorizacion'])), 0, '', 0, 0, 151, 36, true, 0, false, true, 0, 'T');
$pdf->MultiCell(47, 0, $xmlComprobante->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN', 0, '', 0, 0, 151, 50, true, 0, false, true, 0, 'T');
$pdf->MultiCell(65, 0, "NORMAL", 0, '', 0, 0, 151, 55, true, 0, false, true, 0, 'T');
$pdf->write1DBarcode($factura['numero_autorizacion'], 'C39E', 88, 75, 110, 15, 0.4, array(), 'N');
$pdf->MultiCell(113, 0, $factura['numero_autorizacion'], 0, 'C', 0, 0, 86, 90, true, 0, false, true, 0, 'T');

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
// 10. DETALLES DE LA FACTURA
// ================================================
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
$contador = 0;
foreach ($xmlComprobante->detalles->detalle as $detalle) {
    $pdf->Cell(22, 6, (string)$detalle->codigoPrincipal, 1, 0, 'C');
    $pdf->Cell(22, 6, '', 1, 0, 'C');
    $pdf->Cell(17, 6, number_format((float)$detalle->cantidad, 2), 1, 0, 'C');
    $pdf->Cell(70, 6, (string)$detalle->descripcion, 1, 0, 'L');
    $pdf->Cell(20, 6, number_format((float)$detalle->precioUnitario, 2), 1, 0, 'R');
    $pdf->Cell(19, 6, number_format((float)$detalle->descuento, 2), 1, 0, 'R');
    $pdf->Cell(20, 6, number_format((float)$detalle->precioTotalSinImpuesto, 2), 1, 0, 'R');
    $pdf->Ln();
    $contador++;
    if ($contador % 28 == 0) {
        $pdf->AddPage();
    }
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
$pdf->Output('factura_' . $factura['numero_autorizacion'] . '.pdf', 'I');