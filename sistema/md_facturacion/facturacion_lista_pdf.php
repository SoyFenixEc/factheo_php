<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

// Incluir TCPDF
require_once('../../vendor/TCPDF/tcpdf.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener parámetros
$filtro_empresa = isset($_GET['filtro_empresa']) ? (int)$_GET['filtro_empresa'] : 0;
$fecha_inicio = isset($_GET['fecha_inicio']) && !empty($_GET['fecha_inicio']) ? $_GET['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_GET['fecha_fin']) && !empty($_GET['fecha_fin']) ? $_GET['fecha_fin'] : date('Y-m-t');
$filtro_estado = isset($_GET['filtro_estado']) && $_GET['filtro_estado'] != 'all' ? $_GET['filtro_estado'] : null;
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $fecha_temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $fecha_temp;
}

// Extender TCPDF
class MYPDF extends TCPDF {
    private $filtro_empresa;
    private $empresa_nombre;
    private $fecha_inicio;
    private $fecha_fin;
    private $filtro_estado;
    private $search;
    private $ocultar_header = false;
    
    public function setData($filtro_empresa, $empresa_nombre, $fecha_inicio, $fecha_fin, $filtro_estado, $search) {
        $this->filtro_empresa = $filtro_empresa;
        $this->empresa_nombre = $empresa_nombre;
        $this->fecha_inicio = $fecha_inicio;
        $this->fecha_fin = $fecha_fin;
        $this->filtro_estado = $filtro_estado;
        $this->search = $search;
    }
    
    public function ocultarHeader($ocultar = true) {
        $this->ocultar_header = $ocultar;
    }
    
    public function Header() {
        // No mostrar header si está oculto o si es la primera página
        if ($this->ocultar_header) {
            return;
        }
        
        if ($this->getPage() > 1) {
            $this->SetY(10);
            $this->SetFont('helvetica', 'B', 9);
            $this->Cell(0, 4, 'SISTEMA DE FACTURACIÓN ELECTRÓNICA - FACTHEO.COM', 0, 1, 'C');
            $this->SetFont('helvetica', '', 7);
            $this->Cell(0, 4, 'REPORTE DE FACTURAS', 0, 1, 'C');
            if ($this->filtro_empresa > 0) {
                $this->Cell(0, 4, 'Empresa: ' . $this->empresa_nombre, 0, 1, 'C');
            }
            $this->Cell(0, 4, 'Período: ' . date('d/m/Y', strtotime($this->fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($this->fecha_fin)), 0, 1, 'C');
            $this->Ln(2);
            $this->SetLineWidth(0.2);
            $this->Line(8, $this->GetY(), $this->getPageWidth() - 8, $this->GetY());
            $this->Ln(3);
        }
    }
    
    public function Footer() {
        $this->SetY(-12);
        $this->SetFont('helvetica', 'I', 6);
        $this->Cell(0, 5, 'Página ' . $this->getAliasNumPage() . ' de ' . $this->getAliasNbPages() . ' - ' . date('d/m/Y H:i:s'), 0, false, 'C', 0, '', 0, false, 'T', 'M');
    }
    
    public function generarTablaEmpresa($empresa_nombre, $facturas, $totales) {
        // Asegurar espacio antes de la tabla
        if ($this->GetY() > 250) {
            $this->AddPage();
        }
        
        $this->SetFont('helvetica', 'B', 9);
        $this->SetTextColor(78, 115, 223);
        $this->Cell(0, 5, 'Empresa: ' . $empresa_nombre, 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
        
        // Anchos de columna en milímetros
        $w = array(
            'factura' => 18,   // # Factura
            'cliente' => 40,   // Cliente
            'ruc'     => 20,   // RUC/CI
            'fecha_emi' => 16, // Fecha Emis.
            'fecha_aut' => 16, // Fecha Autor.
            'num_aut'  => 70,  // Número Autorización
            'subtotal' => 18,  // Subtotal
            'iva'      => 18,  // IVA
            'total'    => 18,  // Total
            'forma_pago' => 18,// Forma Pago
            'estado'   => 18   // Estado
        );
        
        // Configurar fuente para la tabla
        $this->SetFont('helvetica', '', 6);
        
        // Encabezado de la tabla
        $this->SetFillColor(78, 115, 223);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('helvetica', 'B', 6);
        
        $this->Cell($w['factura'], 5, '# Factura', 1, 0, 'C', 1);
        $this->Cell($w['cliente'], 5, 'Cliente', 1, 0, 'C', 1);
        $this->Cell($w['ruc'], 5, 'RUC/CI', 1, 0, 'C', 1);
        $this->Cell($w['fecha_emi'], 5, 'Fecha Emis.', 1, 0, 'C', 1);
        $this->Cell($w['fecha_aut'], 5, 'Fecha Autor.', 1, 0, 'C', 1);
        $this->Cell($w['num_aut'], 5, 'N° Autorización', 1, 0, 'C', 1);
        $this->Cell($w['subtotal'], 5, 'Subtotal', 1, 0, 'C', 1);
        $this->Cell($w['iva'], 5, 'IVA', 1, 0, 'C', 1);
        $this->Cell($w['total'], 5, 'Total', 1, 0, 'C', 1);
        $this->Cell($w['forma_pago'], 5, 'Forma Pago', 1, 0, 'C', 1);
        $this->Cell($w['estado'], 5, 'Estado', 1, 1, 'C', 1);
        
        // Datos de la tabla
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('helvetica', '', 5.5);
        $this->SetFillColor(255, 255, 255);
        $fill = false;
        
        foreach ($facturas as $f) {
            $numero_factura = $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . str_pad($f['secuencial'], 9, '0', STR_PAD_LEFT);
            $fecha_autorizacion = $f['fecha_autorizacion'] ? date('d/m/Y', strtotime($f['fecha_autorizacion'])) : '---';
            $numero_autorizacion = $f['numero_autorizacion'] ?? '---';
            
            // Truncar nombre del cliente
            $cliente_nombre = mb_substr($f['cliente_nombre'], 0, 60);
            if (mb_strlen($f['cliente_nombre']) > 60) {
                $cliente_nombre .= '...';
            }
            
            // Truncar número de autorización si es muy largo
            $num_aut_display = $numero_autorizacion;
            if (strlen($numero_autorizacion) > 50) {
                $num_aut_display = substr($numero_autorizacion, 0, 50) . '...';
            }
            
            $this->Cell($w['factura'], 4, $numero_factura, 1, 0, 'C', $fill);
            $this->Cell($w['cliente'], 4, htmlspecialchars($cliente_nombre), 1, 0, 'L', $fill);
            $this->Cell($w['ruc'], 4, $f['cliente_identificacion'], 1, 0, 'C', $fill);
            $this->Cell($w['fecha_emi'], 4, date('d/m/Y', strtotime($f['fecha_emision'])), 1, 0, 'C', $fill);
            $this->Cell($w['fecha_aut'], 4, $fecha_autorizacion, 1, 0, 'C', $fill);
            $this->Cell($w['num_aut'], 4, $num_aut_display, 1, 0, 'C', $fill);
            $this->Cell($w['subtotal'], 4, '$ ' . number_format($f['subtotal1'], 2), 1, 0, 'R', $fill);
            $this->Cell($w['iva'], 4, '$ ' . number_format($f['valor_iva'], 2), 1, 0, 'R', $fill);
            $this->Cell($w['total'], 4, '$ ' . number_format($f['total'], 2), 1, 0, 'R', $fill);
            $this->Cell($w['forma_pago'], 4, $f['forma_pago'] ?? '---', 1, 0, 'C', $fill);
            $this->Cell($w['estado'], 4, $f['estado_xml'], 1, 1, 'C', $fill);
            
            $fill = !$fill;
            
            // Verificar si necesitamos nueva página después de cada 20 filas
            if ($this->GetY() > 260) {
                $this->AddPage();
                // Reimprimir encabezado de tabla en la nueva página
                $this->SetFillColor(78, 115, 223);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('helvetica', 'B', 6);
                
                $this->Cell($w['factura'], 5, '# Factura', 1, 0, 'C', 1);
                $this->Cell($w['cliente'], 5, 'Cliente', 1, 0, 'C', 1);
                $this->Cell($w['ruc'], 5, 'RUC/CI', 1, 0, 'C', 1);
                $this->Cell($w['fecha_emi'], 5, 'Fecha Emis.', 1, 0, 'C', 1);
                $this->Cell($w['fecha_aut'], 5, 'Fecha Autor.', 1, 0, 'C', 1);
                $this->Cell($w['num_aut'], 5, 'N° Autorización', 1, 0, 'C', 1);
                $this->Cell($w['subtotal'], 5, 'Subtotal', 1, 0, 'C', 1);
                $this->Cell($w['iva'], 5, 'IVA', 1, 0, 'C', 1);
                $this->Cell($w['total'], 5, 'Total', 1, 0, 'C', 1);
                $this->Cell($w['forma_pago'], 5, 'Forma Pago', 1, 0, 'C', 1);
                $this->Cell($w['estado'], 5, 'Estado', 1, 1, 'C', 1);
                $this->SetTextColor(0, 0, 0);
                $this->SetFont('helvetica', '', 5.5);
            }
        }
        
        // Totales de la empresa
        $this->SetFont('helvetica', 'B', 6);
        $this->SetFillColor(248, 249, 252);
        
        $this->Cell($w['factura'] + $w['cliente'] + $w['ruc'] + $w['fecha_emi'] + $w['fecha_aut'] + $w['num_aut'], 5, 'TOTALES:', 1, 0, 'R', 1);
        $this->Cell($w['subtotal'], 5, '$ ' . number_format($totales['subtotal'], 2), 1, 0, 'R', 1);
        $this->Cell($w['iva'], 5, '$ ' . number_format($totales['iva'], 2), 1, 0, 'R', 1);
        $this->Cell($w['total'], 5, '$ ' . number_format($totales['total'], 2), 1, 0, 'R', 1);
        $this->Cell($w['forma_pago'] + $w['estado'], 5, $totales['cantidad'] . ' facturas', 1, 1, 'C', 1);
        
        $this->Ln(5);
    }
}

try {
    // Construir la consulta SQL con número de autorización
    $sql = "
        SELECT 
            f.id, 
            f.fecha_emision, 
            f.total, 
            f.estado_xml, 
            f.establecimiento, 
            f.punto_emision, 
            f.secuencial,
            f.fecha_autorizacion,
            f.subtotal1,
            f.valor_iva,
            f.numero_autorizacion,
            c.razon_social AS cliente_nombre,
            c.identificacion AS cliente_identificacion,
            e.id AS empresa_id,
            e.nombre_comercial AS empresa_nombre,
            fp.nombre AS forma_pago
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id
        JOIN empresa e ON f.empresa_id = e.id
        JOIN formas_pago fp ON f.forma_pago_id = fp.id
        WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id
    ";
    
    $params = [':usuario_id' => $usuario_id];
    
    // Aplicar filtro de empresa
    if ($filtro_empresa > 0) {
        $sql .= " AND f.empresa_id = :empresa_id";
        $params[':empresa_id'] = $filtro_empresa;
    }
    
    // Aplicar filtro de fechas
    $sql .= " AND DATE(f.fecha_emision) BETWEEN :fecha_inicio AND :fecha_fin";
    $params[':fecha_inicio'] = $fecha_inicio;
    $params[':fecha_fin'] = $fecha_fin;
    
    // Aplicar filtro de estado
    if ($filtro_estado) {
        $sql .= " AND f.estado_xml = :estado";
        $params[':estado'] = $filtro_estado;
    }
    
    // Aplicar búsqueda
    if (!empty($search)) {
        $sql .= " AND (f.id LIKE :search OR c.razon_social LIKE :search OR e.nombre_comercial LIKE :search OR c.identificacion LIKE :search OR f.numero_autorizacion LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    // ORDEN: De más antigua a más reciente (ASC)
    $sql .= " ORDER BY e.nombre_comercial, f.fecha_emision ASC, f.id ASC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $facturas = $stmt->fetchAll();
    
    // Agrupar por empresa
    $datos_agrupados = [];
    foreach ($facturas as $factura) {
        $empresa = $factura['empresa_nombre'];
        if (!isset($datos_agrupados[$empresa])) {
            $datos_agrupados[$empresa] = [
                'facturas' => [],
                'totales' => ['subtotal' => 0, 'iva' => 0, 'total' => 0, 'cantidad' => 0]
            ];
        }
        $datos_agrupados[$empresa]['facturas'][] = $factura;
        $datos_agrupados[$empresa]['totales']['subtotal'] += $factura['subtotal1'];
        $datos_agrupados[$empresa]['totales']['iva'] += $factura['valor_iva'];
        $datos_agrupados[$empresa]['totales']['total'] += $factura['total'];
        $datos_agrupados[$empresa]['totales']['cantidad']++;
    }
    
    // Calcular totales generales
    $total_general_subtotal = 0;
    $total_general_iva = 0;
    $total_general_total = 0;
    $total_general_cantidad = 0;
    
    foreach ($datos_agrupados as $data) {
        $total_general_subtotal += $data['totales']['subtotal'];
        $total_general_iva += $data['totales']['iva'];
        $total_general_total += $data['totales']['total'];
        $total_general_cantidad += $data['totales']['cantidad'];
    }
    
    // Obtener nombre de la empresa si está filtrada
    $empresa_nombre_filtro = '';
    if ($filtro_empresa > 0) {
        $sql_empresa = "SELECT nombre_comercial FROM empresa WHERE id = :empresa_id";
        $stmt_empresa = $pdo->prepare($sql_empresa);
        $stmt_empresa->bindParam(':empresa_id', $filtro_empresa, PDO::PARAM_INT);
        $stmt_empresa->execute();
        $empresa_data = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
        $empresa_nombre_filtro = $empresa_data ? $empresa_data['nombre_comercial'] : '';
    }
    
} catch (PDOException $e) {
    error_log("Error en facturacion_lista_pdf.php: " . $e->getMessage());
    die("Error al generar el reporte: " . $e->getMessage());
}

// Crear nuevo PDF
$pdf = new MYPDF('L', 'mm', 'A4', true, 'UTF-8', false);
$pdf->setData($filtro_empresa, $empresa_nombre_filtro, $fecha_inicio, $fecha_fin, $filtro_estado, $search);

// Configurar documento
$pdf->SetCreator('Sistema de Facturación Electrónica - FACTHEO.COM');
$pdf->SetAuthor('FACTHEO.COM');
$pdf->SetTitle('Reporte de Facturas - FACTHEO.COM');
$pdf->SetSubject('Listado de Facturas');
$pdf->SetKeywords('facturas, reporte, pdf, factheo');

// Configurar márgenes
$pdf->SetMargins(8, 8, 8);
$pdf->SetHeaderMargin(5);
$pdf->SetFooterMargin(8);
$pdf->SetAutoPageBreak(TRUE, 18);

// Agregar primera página
$pdf->AddPage();

// Título principal en primera página (sin usar el Header automático)
$pdf->SetY(12);
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 6, 'SISTEMA DE FACTURACIÓN ELECTRÓNICA', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 5, 'FACTHEO.COM', 0, 1, 'C');
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'REPORTE DE FACTURAS', 0, 1, 'C');

$pdf->SetFont('helvetica', '', 9);
$pdf->Cell(0, 5, 'Período: ' . date('d/m/Y', strtotime($fecha_inicio)) . ' - ' . date('d/m/Y', strtotime($fecha_fin)), 0, 1, 'C');
$pdf->Cell(0, 5, 'Fecha de generación: ' . date('d/m/Y H:i:s'), 0, 1, 'C');

// Mostrar filtros aplicados
$pdf->Ln(3);
if ($filtro_empresa > 0 && $empresa_nombre_filtro) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 4, 'Empresa: ' . $empresa_nombre_filtro, 0, 1, 'L');
}
if ($filtro_estado) {
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 4, 'Estado: ' . $filtro_estado, 0, 1, 'L');
}
if (!empty($search)) {
    $pdf->Cell(0, 4, 'Búsqueda: ' . $search, 0, 1, 'L');
}
$pdf->Ln(3);

// Línea separadora
$pdf->SetLineWidth(0.3);
$pdf->Line(8, $pdf->GetY(), $pdf->getPageWidth() - 8, $pdf->GetY());
$pdf->Ln(5);

if (empty($datos_agrupados)) {
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 10, 'No se encontraron facturas con los filtros seleccionados.', 0, 1, 'C');
} else {
    // Generar tabla por cada empresa
    foreach ($datos_agrupados as $empresa_nombre => $data) {
        // Verificar si hay espacio suficiente
        if ($pdf->GetY() > 260) {
            $pdf->AddPage();
        }
        
        // Generar tabla para esta empresa
        $pdf->generarTablaEmpresa($empresa_nombre, $data['facturas'], $data['totales']);
    }
    
    // Resumen General (solo si hay más de una empresa y no hay filtro específico)
    if ($filtro_empresa == 0 && count($datos_agrupados) > 1) {
        // Ocultar header para esta página
        $pdf->ocultarHeader(true);
        $pdf->AddPage();
        
        // Título del resumen
        $pdf->SetY(15);
        $pdf->SetFont('helvetica', 'B', 11);
        $pdf->Cell(0, 6, 'RESUMEN GENERAL', 0, 1, 'C');
        $pdf->Ln(4);
        
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(78, 115, 223);
        $pdf->SetTextColor(255, 255, 255);
        
        $w_resumen = array(
            'empresa' => 120,
            'facturas' => 35,
            'subtotal' => 40,
            'iva' => 40,
            'total' => 40
        );
        
        $pdf->Cell($w_resumen['empresa'], 7, 'Empresa', 1, 0, 'C', 1);
        $pdf->Cell($w_resumen['facturas'], 7, 'Facturas', 1, 0, 'C', 1);
        $pdf->Cell($w_resumen['subtotal'], 7, 'Subtotal', 1, 0, 'C', 1);
        $pdf->Cell($w_resumen['iva'], 7, 'IVA', 1, 0, 'C', 1);
        $pdf->Cell($w_resumen['total'], 7, 'Total', 1, 1, 'C', 1);
        
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        
        foreach ($datos_agrupados as $empresa_nombre => $data) {
            $pdf->Cell($w_resumen['empresa'], 6, htmlspecialchars($empresa_nombre), 1, 0, 'L', $fill);
            $pdf->Cell($w_resumen['facturas'], 6, number_format($data['totales']['cantidad']), 1, 0, 'R', $fill);
            $pdf->Cell($w_resumen['subtotal'], 6, '$ ' . number_format($data['totales']['subtotal'], 2), 1, 0, 'R', $fill);
            $pdf->Cell($w_resumen['iva'], 6, '$ ' . number_format($data['totales']['iva'], 2), 1, 0, 'R', $fill);
            $pdf->Cell($w_resumen['total'], 6, '$ ' . number_format($data['totales']['total'], 2), 1, 1, 'R', $fill);
            $fill = !$fill;
        }
        
        // Total general
        $pdf->SetFont('helvetica', 'B', 8);
        $pdf->SetFillColor(248, 249, 252);
        $pdf->Cell($w_resumen['empresa'], 7, 'TOTAL GENERAL', 1, 0, 'R', 1);
        $pdf->Cell($w_resumen['facturas'], 7, number_format($total_general_cantidad), 1, 0, 'R', 1);
        $pdf->Cell($w_resumen['subtotal'], 7, '$ ' . number_format($total_general_subtotal, 2), 1, 0, 'R', 1);
        $pdf->Cell($w_resumen['iva'], 7, '$ ' . number_format($total_general_iva, 2), 1, 0, 'R', 1);
        $pdf->Cell($w_resumen['total'], 7, '$ ' . number_format($total_general_total, 2), 1, 1, 'R', 1);
        
        // Reactivar header para páginas siguientes (si las hubiera)
        $pdf->ocultarHeader(false);
    }
}

// Salida del PDF
$pdf->Output('facturas_' . date('Ymd_His') . '.pdf', 'D');
exit();
?>