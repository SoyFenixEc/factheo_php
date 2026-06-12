<?php
// ================================================
// 1. CONFIGURACIÓN Y CONEXIÓN
// ================================================
chdir(dirname(__FILE__));
require('../../../md_config/conexion.php');

$id_factura = $_GET['id'] ?? 0;
$action = $_GET['action'] ?? 'view';

if (!$id_factura) {
    die('ID de factura no proporcionado.');
}

// ================================================
// 2. OBTENER DATOS DE LA FACTURA Y EMPRESA
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
// 5. SI ES DESCARGAR O IMPRIMIR, GENERAR PDF
// ================================================
if ($action === 'download' || $action === 'print') {
    require_once('../librerias/TCPDF/tcpdf.php');
    
    // ... (AQUÍ VA TODO TU CÓDIGO ORIGINAL DE TCPDF QUE ME ENVIASTE)
    // INCLUYENDO:
    // - Configuración de TCPDF
    // - Logo
    // - Cabecera
    // - Datos de factura
    // - Datos del cliente  
    // - Detalles
    // - Totales
    
    // EJEMPLO DE CÓMO DEBERÍA TERMINAR:
    if ($action === 'download') {
        $pdf->Output('factura_' . $factura['numero_autorizacion'] . '.pdf', 'D');
    } else {
        $pdf->Output('factura_imprimir_' . $factura['numero_autorizacion'] . '.pdf', 'D');
    }
    exit;
}

// ================================================
// 6. SI ES VER, MOSTRAR VISOR WEB (HTML)
// ================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Factura <?php echo $xmlComprobante->infoTributaria->estab . '-' . $xmlComprobante->infoTributaria->ptoEmi . '-' . $xmlComprobante->infoTributaria->secuencial; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Arial', sans-serif;
        }
        
        body {
            background: #f0f2f5;
            padding: 20px;
        }
        
        .container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #2c3e50, #4a6491);
            color: white;
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .company-info h1 {
            font-size: 1.4em;
            margin-bottom: 5px;
        }
        
        .invoice-info h2 {
            font-size: 1.3em;
            text-align: right;
        }
        
        .controls {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-download {
            background: #28a745;
            color: white;
        }
        
        .btn-print {
            background: #17a2b8;
            color: white;
        }
        
        .btn-expand {
            background: #6c757d;
            color: white;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        }
        
        .invoice-content {
            padding: 30px;
        }
        
        .section {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            background: #f8f9fa;
        }
        
        .section-title {
            color: #2c3e50;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
            margin-bottom: 15px;
            font-size: 1.2em;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 15px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        
        .info-label {
            font-weight: bold;
            color: #495057;
        }
        
        .info-value {
            color: #2c3e50;
            text-align: right;
        }
        
        .products-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .products-table th {
            background: #2c3e50;
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .products-table td {
            padding: 10px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .products-table tr:nth-child(even) {
            background: #f8f9fa;
        }
        
        .totals {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .total-item {
            display: flex;
            justify-content: space-between;
            padding: 10px;
            background: white;
            border-radius: 5px;
            border: 1px solid #dee2e6;
        }
        
        .total-label {
            font-weight: bold;
        }
        
        .total-value {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .full-total {
            grid-column: 1 / -1;
            background: #2c3e50 !important;
            color: white;
            font-size: 1.2em;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .invoice-info h2 {
                text-align: center;
                margin-top: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .totals {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="company-info">
                <h1><?php echo htmlspecialchars($factura['razon_social']); ?></h1>
                <p>RUC: <?php echo htmlspecialchars($factura['ruc']); ?></p>
            </div>
            <div class="invoice-info">
                <h2>FACTURA ELECTRÓNICA</h2>
                <p>No. <?php echo $xmlComprobante->infoTributaria->estab . '-' . $xmlComprobante->infoTributaria->ptoEmi . '-' . $xmlComprobante->infoTributaria->secuencial; ?></p>
            </div>
        </div>
        
        <div class="controls">
            <button class="btn btn-download" onclick="downloadPDF()">
                📥 Descargar PDF
            </button>
            <button class="btn btn-print" onclick="printPDF()">
                🖨️ Imprimir
            </button>
            <button class="btn btn-expand" onclick="window.open('<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id_factura . '&action=download'; ?>', '_blank')">
                ⛶ Abrir en nueva ventana
            </button>
        </div>
        
        <div class="invoice-content">
            <!-- Información de la Factura -->
            <div class="section">
                <h3 class="section-title">Información de la Factura</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Clave de Acceso:</span>
                        <span class="info-value"><?php echo htmlspecialchars($factura['clave_acceso']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Número de Autorización:</span>
                        <span class="info-value"><?php echo htmlspecialchars($factura['numero_autorizacion']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Autorización:</span>
                        <span class="info-value"><?php echo date('d/m/Y H:i:s', strtotime($factura['fecha_autorizacion'])); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Ambiente:</span>
                        <span class="info-value"><?php echo $xmlComprobante->infoTributaria->ambiente == '1' ? 'PRUEBAS' : 'PRODUCCIÓN'; ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Emisión:</span>
                        <span class="info-value">NORMAL</span>
                    </div>
                </div>
            </div>
            
            <!-- Información del Cliente -->
            <div class="section">
                <h3 class="section-title">Información del Cliente</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <span class="info-label">Razón Social:</span>
                        <span class="info-value"><?php echo htmlspecialchars($xmlComprobante->infoFactura->razonSocialComprador); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Identificación:</span>
                        <span class="info-value"><?php echo htmlspecialchars($xmlComprobante->infoFactura->identificacionComprador); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Fecha de Emisión:</span>
                        <span class="info-value"><?php echo htmlspecialchars($xmlComprobante->infoFactura->fechaEmision); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Detalles de Productos -->
            <div class="section">
                <h3 class="section-title">Detalles de la Factura</h3>
                <table class="products-table">
                    <thead>
                        <tr>
                            <th>Código Principal</th>
                            <th>Descripción</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Descuento</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($xmlComprobante->detalles->detalle as $detalle): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($detalle->codigoPrincipal); ?></td>
                            <td><?php echo htmlspecialchars($detalle->descripcion); ?></td>
                            <td><?php echo number_format((float)$detalle->cantidad, 2); ?></td>
                            <td>$<?php echo number_format((float)$detalle->precioUnitario, 2); ?></td>
                            <td>$<?php echo number_format((float)$detalle->descuento, 2); ?></td>
                            <td>$<?php echo number_format((float)$detalle->precioTotalSinImpuesto, 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Totales -->
            <div class="section">
                <h3 class="section-title">Totales</h3>
                <div class="totals">
                    <div class="total-item">
                        <span class="total-label">SubTotal <?php echo $factura['iva']; ?>%:</span>
                        <span class="total-value">$<?php echo number_format((float)$xmlComprobante->infoFactura->totalSinImpuestos, 2); ?></span>
                    </div>
                    <div class="total-item">
                        <span class="total-label">Descuento:</span>
                        <span class="total-value">$<?php echo number_format((float)$xmlComprobante->infoFactura->totalDescuento, 2); ?></span>
                    </div>
                    <div class="total-item">
                        <span class="total-label">IVA <?php echo $factura['iva']; ?>%:</span>
                        <span class="total-value">$<?php echo number_format((float)$xmlComprobante->infoFactura->totalConImpuestos->totalImpuesto->valor, 2); ?></span>
                    </div>
                    <div class="total-item full-total">
                        <span class="total-label">VALOR TOTAL:</span>
                        <span class="total-value">$<?php echo number_format((float)$xmlComprobante->infoFactura->importeTotal, 2); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            window.location.href = '<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id_factura . '&action=download'; ?>';
        }
        
        function printPDF() {
            window.location.href = '<?php echo $_SERVER['PHP_SELF'] . '?id=' . $id_factura . '&action=print'; ?>';
        }
    </script>
</body>
</html>