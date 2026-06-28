<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    header("Location: ../login.php");
    exit();
}

$usuario_id = $_SESSION['usuario_id'];

// Función para formatear fecha en español
function fechaEspanol($formato, $timestamp = null) {
    $timestamp = $timestamp ?? time();
    $dias = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
    $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    if ($formato === 'l, d F Y') {
        return ucfirst($dias[(int)date('w', $timestamp)]) . ', ' . date('d', $timestamp) . ' de ' . $meses[(int)date('n', $timestamp) - 1] . ' de ' . date('Y', $timestamp);
    }
    if ($formato === 'F') {
        return ucfirst($meses[(int)date('n', $timestamp) - 1]);
    }
    return date($formato, $timestamp);
}

// Obtener empresas del usuario actual
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :usuario_id AND activa = 1 ORDER BY nombre_comercial";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);

// Obtener filtros
$empresa_filtro = isset($_POST['empresa_id']) && !empty($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : null;
$fecha_inicio = isset($_POST['fecha_inicio']) && !empty($_POST['fecha_inicio']) ? $_POST['fecha_inicio'] : date('Y-m-01');
$fecha_fin = isset($_POST['fecha_fin']) && !empty($_POST['fecha_fin']) ? $_POST['fecha_fin'] : date('Y-m-t');

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $fecha_temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $fecha_temp;
}

// Inicializar variables
$stats = [];
$ventas_cantidad = [];
$ventas_monto = [];
$labels = [];
$ventas_anual_cantidad = [];
$ventas_anual_monto = [];
$labels_anual = [];
$productos_bajo_stock = [];

// === OBTENCIÓN DE DATOS CON FILTROS ===
try {
    $where_empresa = $empresa_filtro ? ' AND e.id = :empresa_id' : '';
    $where_fechas = ' AND f.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin';
    $where_fechas_guias = ' AND g.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin';
    $where_empresa_guias = $empresa_filtro ? ' AND g.empresa_id = :empresa_id' : '';
    $where_fechas_retenciones = ' AND cr.fecha_emision BETWEEN :fecha_inicio AND :fecha_fin';
    $where_empresa_retenciones = $empresa_filtro ? ' AND cr.empresa_id = :empresa_id' : '';
    
    // Estadísticas principales
    $sql_stats = "
        SELECT 
            -- Total Facturas (con filtro de fechas)
            (SELECT COUNT(*) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas) as total_facturas,
            
            -- Facturas del Mes Actual
            (SELECT COUNT(*) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa AND f.fecha_emision LIKE :mes_actual) as facturas_mes,
            
            -- Ingresos Totales (AUTORIZADAS con filtro de fechas)
            COALESCE((SELECT SUM(total) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas AND f.estado_xml = 'AUTORIZADO'), 0) as ingresos_total,
            
            -- Ingresos del Mes Actual
            COALESCE((SELECT SUM(total) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE e.usuario_id = :usuario_id $where_empresa AND f.estado_xml = 'AUTORIZADO' AND f.fecha_emision LIKE :mes_actual), 0) as ingresos_mes,
            
            -- Contadores generales
            (SELECT COUNT(*) FROM clientes WHERE usuario_id = :usuario_id) as total_clientes,
            (SELECT COUNT(*) FROM productos WHERE usuario_id = :usuario_id) as total_productos,
            (SELECT COUNT(*) FROM bodegas WHERE usuario_id = :usuario_id) as total_bodegas,
            
            -- Nuevas estadísticas
            COALESCE((SELECT AVG(total) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas AND f.estado_xml = 'AUTORIZADO'), 0) as promedio_venta,
            
            (SELECT COUNT(DISTINCT cliente_id) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas) as clientes_activos,
            
            -- Nuevos contadores por tipo de comprobante
            (SELECT COUNT(*) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '03' AND e.usuario_id = :usuario_id $where_empresa $where_fechas) as total_liquidaciones,
            
            (SELECT COUNT(*) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '04' AND e.usuario_id = :usuario_id $where_empresa $where_fechas) as total_notas_credito,
            
            (SELECT COUNT(*) FROM facturas f JOIN empresa e ON f.empresa_id = e.id 
             WHERE f.tipo_comprobante_id = '05' AND e.usuario_id = :usuario_id $where_empresa $where_fechas) as total_notas_debito,
            
            -- Guías de Remisión (tabla independiente)
            (SELECT COUNT(*) FROM guias_remision g JOIN empresa e ON g.empresa_id = e.id 
             WHERE e.usuario_id = :usuario_id $where_fechas_guias $where_empresa_guias) as total_guias,
            
            -- Comprobantes de Retención (tabla independiente)
            (SELECT COUNT(*) FROM comprobantes_retencion cr JOIN empresa e ON cr.empresa_id = e.id 
             WHERE e.usuario_id = :usuario_id $where_fechas_retenciones $where_empresa_retenciones) as total_retenciones
    ";
    
    $stmt_stats = $pdo->prepare($sql_stats);
    $mes_actual = date('Y-m') . '%';
    $stmt_stats->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_stats->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_stats->bindParam(':fecha_fin', $fecha_fin);
    if ($empresa_filtro) {
        $stmt_stats->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
    }
    $stmt_stats->bindValue(':mes_actual', $mes_actual);
    $stmt_stats->execute();
    $row = $stmt_stats->fetch(PDO::FETCH_ASSOC);

    $stats = [
        'total_facturas' => (int)$row['total_facturas'],
        'facturas_mes'   => (int)$row['facturas_mes'],
        'ingresos_total' => (float)$row['ingresos_total'],
        'ingresos_mes'   => (float)$row['ingresos_mes'],
        'total_clientes' => (int)$row['total_clientes'],
        'total_productos'=> (int)$row['total_productos'],
        'total_bodegas'  => (int)$row['total_bodegas'],
        'promedio_venta' => (float)$row['promedio_venta'],
        'clientes_activos' => (int)$row['clientes_activos'],
        'total_liquidaciones' => (int)$row['total_liquidaciones'],
        'total_notas_credito' => (int)$row['total_notas_credito'],
        'total_notas_debito' => (int)$row['total_notas_debito'],
        'total_guias' => (int)$row['total_guias'],
        'total_retenciones' => (int)$row['total_retenciones']
    ];

    // Estado de Facturas
    $sql_estados = "
        SELECT estado_xml, COUNT(*) as total 
        FROM facturas f 
        JOIN empresa e ON f.empresa_id = e.id 
        WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas
        GROUP BY estado_xml 
        ORDER BY FIELD(estado_xml, 'AUTORIZADO', 'RECIBIDA', 'FIRMADO', 'GENERADO', 'DEVUELTA')
    ";
    $stmt_estados = $pdo->prepare($sql_estados);
    $stmt_estados->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_estados->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_estados->bindParam(':fecha_fin', $fecha_fin);
    if ($empresa_filtro) {
        $stmt_estados->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
    }
    $stmt_estados->execute();
    $stats['estados_facturas'] = $stmt_estados->fetchAll(PDO::FETCH_ASSOC);

    // Últimas Facturas
    $sql_ultimas = "
        SELECT f.id, f.total, f.fecha_emision, f.estado_xml, c.razon_social AS cliente_nombre
        FROM facturas f
        JOIN clientes c ON f.cliente_id = c.id
        JOIN empresa e ON f.empresa_id = e.id
        WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas
        ORDER BY f.id DESC LIMIT 5
    ";
    $stmt_ultimas = $pdo->prepare($sql_ultimas);
    $stmt_ultimas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_ultimas->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_ultimas->bindParam(':fecha_fin', $fecha_fin);
    if ($empresa_filtro) {
        $stmt_ultimas->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
    }
    $stmt_ultimas->execute();
    $stats['ultimas_facturas'] = $stmt_ultimas->fetchAll(PDO::FETCH_ASSOC);

    // Productos Más Vendidos
    $sql_productos = "
        SELECT p.nombre, SUM(df.cantidad) as total_vendido, SUM(df.cantidad * df.precio_unitario) as monto_total
        FROM detalle_factura df
        JOIN productos p ON df.producto_id = p.id
        JOIN facturas f ON df.factura_id = f.id
        JOIN empresa e ON f.empresa_id = e.id
        WHERE f.tipo_comprobante_id = '01' AND e.usuario_id = :usuario_id $where_empresa $where_fechas
        GROUP BY p.id, p.nombre
        ORDER BY total_vendido DESC LIMIT 10
    ";
    $stmt_productos = $pdo->prepare($sql_productos);
    $stmt_productos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_productos->bindParam(':fecha_inicio', $fecha_inicio);
    $stmt_productos->bindParam(':fecha_fin', $fecha_fin);
    if ($empresa_filtro) {
        $stmt_productos->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
    }
    $stmt_productos->execute();
    $stats['productos_mas_vendidos'] = $stmt_productos->fetchAll(PDO::FETCH_ASSOC);

    // Datos para gráfico de ventas mensuales (últimos 6 meses)
    for ($i = 5; $i >= 0; $i--) {
        $mes_num = date('m', strtotime("-$i months"));
        $anio = date('Y', strtotime("-$i months"));
        $labels[] = date('M', strtotime("-$i months")) . '/' . substr($anio, -2);

        $sql_grafico = "
            SELECT 
                COUNT(*) as cantidad, 
                COALESCE(SUM(total), 0) as monto
            FROM facturas f
            JOIN empresa e ON f.empresa_id = e.id
            WHERE e.usuario_id = :usuario_id $where_empresa
              AND f.estado_xml = 'AUTORIZADO'
              AND MONTH(f.fecha_emision) = :mes
              AND YEAR(f.fecha_emision) = :anio
        ";
        $stmt_grafico = $pdo->prepare($sql_grafico);
        $stmt_grafico->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        if ($empresa_filtro) {
            $stmt_grafico->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
        }
        $stmt_grafico->bindParam(':mes', $mes_num, PDO::PARAM_INT);
        $stmt_grafico->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmt_grafico->execute();
        $data = $stmt_grafico->fetch(PDO::FETCH_ASSOC);

        $ventas_cantidad[] = (int)$data['cantidad'];
        $ventas_monto[] = round($data['monto'], 2);
    }

    // Datos para gráfico de ventas anual (12 meses)
    for ($i = 11; $i >= 0; $i--) {
        $mes_num = date('m', strtotime("-$i months"));
        $anio = date('Y', strtotime("-$i months"));
        $labels_anual[] = date('M', strtotime("-$i months"));

        $sql_grafico_anual = "
            SELECT 
                COUNT(*) as cantidad, 
                COALESCE(SUM(total), 0) as monto
            FROM facturas f
            JOIN empresa e ON f.empresa_id = e.id
            WHERE e.usuario_id = :usuario_id $where_empresa
              AND f.estado_xml = 'AUTORIZADO'
              AND MONTH(f.fecha_emision) = :mes
              AND YEAR(f.fecha_emision) = :anio
        ";
        $stmt_grafico_anual = $pdo->prepare($sql_grafico_anual);
        $stmt_grafico_anual->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
        if ($empresa_filtro) {
            $stmt_grafico_anual->bindParam(':empresa_id', $empresa_filtro, PDO::PARAM_INT);
        }
        $stmt_grafico_anual->bindParam(':mes', $mes_num, PDO::PARAM_INT);
        $stmt_grafico_anual->bindParam(':anio', $anio, PDO::PARAM_INT);
        $stmt_grafico_anual->execute();
        $data_anual = $stmt_grafico_anual->fetch(PDO::FETCH_ASSOC);

        $ventas_anual_cantidad[] = (int)$data_anual['cantidad'];
        $ventas_anual_monto[] = round($data_anual['monto'], 2);
    }

    // PRODUCTOS CON MENOS STOCK - CONSULTA CORREGIDA
    // Primero verificar si existe la columna stock_minimo
    $sql_check_column = "SHOW COLUMNS FROM productos LIKE 'stock_minimo'";
    $stmt_check = $pdo->query($sql_check_column);
    $column_exists = $stmt_check->fetch(PDO::FETCH_ASSOC);
    
    if ($column_exists) {
        // Si existe la columna stock_minimo
        $sql_stock = "
            SELECT p.nombre, p.stock, p.stock_minimo 
            FROM productos p 
            WHERE p.usuario_id = :usuario_id 
            AND p.stock >= 0 
            ORDER BY p.stock ASC 
            LIMIT 5
        ";
    } else {
        // Si no existe la columna, usar consulta simple
        $sql_stock = "
            SELECT p.nombre, p.stock, 10 as stock_minimo 
            FROM productos p 
            WHERE p.usuario_id = :usuario_id 
            AND p.stock >= 0 
            ORDER BY p.stock ASC 
            LIMIT 5
        ";
    }
    
    $stmt_stock = $pdo->prepare($sql_stock);
    $stmt_stock->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_stock->execute();
    $productos_bajo_stock = $stmt_stock->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error en dashboard.php: " . $e->getMessage());
    // En caso de error, inicializar stats vacíos
    $stats = [
        'total_facturas' => 0,
        'facturas_mes' => 0,
        'ingresos_total' => 0,
        'ingresos_mes' => 0,
        'total_clientes' => 0,
        'total_productos' => 0,
        'total_bodegas' => 0,
        'promedio_venta' => 0,
        'clientes_activos' => 0,
        'total_liquidaciones' => 0,
        'total_notas_credito' => 0,
        'total_notas_debito' => 0,
        'total_guias' => 0,
        'total_retenciones' => 0,
        'estados_facturas' => [],
        'ultimas_facturas' => [],
        'productos_mas_vendidos' => []
    ];
    $productos_bajo_stock = [];
    $ventas_cantidad = [0,0,0,0,0,0];
    $ventas_monto = [0,0,0,0,0,0];
    $ventas_anual_cantidad = [0,0,0,0,0,0,0,0,0,0,0,0];
    $ventas_anual_monto = [0,0,0,0,0,0,0,0,0,0,0,0];
    $labels = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun'];
    $labels_anual = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <?php require('../entorno/title.php'); ?>
    <?php require('../entorno/link.php'); ?>
    <title>Dashboard - Sistema de Facturación</title>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            margin-bottom: 16px;
            border: none;
            overflow: hidden;
            height: 100%;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-header-custom {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 8px 14px;
            position: relative;
        }
        .card-header-custom h6 {
            font-size: 0.75rem;
        }
        .stat-number {
            font-size: 1.3rem;
            font-weight: 700;
            color: #5a5c69;
            line-height: 1.2;
        }
        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.2;
        }
        .progress { height: 8px; margin-bottom: 10px; }
        .quick-action {
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 15px;
            border-radius: 10px;
            background-color: #f8f9fc;
            color: #5a5c69;
            transition: all 0.3s;
        }
        .quick-action:hover {
            background-color: #e3e6f0;
            transform: translateY(-2px);
            text-decoration: none;
            color: #5a5c69;
        }
        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            margin: 0 auto 10px;
            font-size: 1.2rem;
        }
        .dashboard-card {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
            height: auto;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .chart-container {
            height: 300px;
            position: relative;
        }
        .row-equal > [class*='col-'] {
            display: flex;
            flex-direction: column;
        }
        @media (max-width: 768px) {
            .row-equal > [class*='col-'] {
                margin-bottom: 15px;
            }
        }
        .filter-container {
            margin-bottom: 1.5rem;
            padding: 1rem;
            background-color: #f8f9fc;
            border-radius: 12px;
            border: 1px solid #e3e6f0;
        }
        .form-group {
            margin-bottom: 0.5rem;
        }
        .table th {
            border-top: none;
            font-weight: 600;
        }
        .quick-actions-row {
            margin-bottom: 20px;
        }
        .quick-actions-container {
            background: linear-gradient(135deg, #f8f9fc 0%, #e3e6f0 100%);
            border-radius: 12px;
            padding: 15px;
            border: 1px solid #e3e6f0;
        }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <?php require('../entorno/menu.php'); ?>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
                    <?php require('../entorno/nav_buscador_pc.php'); ?>
                    <ul class="navbar-nav ml-auto">
                        <?php require('../entorno/nav_buscador_cell.php'); ?>
                        <?php require('../entorno/notificacion_alerta.php'); ?>
                        <?php require('../entorno/notificacion_mensajes.php'); ?>
                        <div class="topbar-divider d-none d-sm-block"></div>
                        <?php require('../entorno/nav_user_dropdown.php'); ?>
                    </ul>
                </nav>

                <div id="dynamic-content" class="container-fluid">
                    <!-- Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Dashboard</h1>
                        <div class="d-none d-sm-inline-block">
                            <span class="text-muted"><?php echo fechaEspanol('l, d F Y'); ?></span>
                        </div>
                    </div>

                    <!-- Filtros Mejorados -->
                    <div class="filter-container">
                        <form method="POST" class="form-inline align-items-end">
                            <div class="form-group mr-3">
                                <label for="empresa_id" class="mr-2"><strong>Empresa:</strong></label>
                                <select name="empresa_id" id="empresa_id" class="form-control">
                                    <option value="">Todas las Empresas</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?php echo $empresa['id']; ?>" <?php echo ($empresa_filtro == $empresa['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($empresa['nombre_comercial']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group mr-3">
                                <label for="fecha_inicio" class="mr-2"><strong>Desde:</strong></label>
                                <input type="date" name="fecha_inicio" id="fecha_inicio" class="form-control" value="<?php echo $fecha_inicio; ?>">
                            </div>
                            
                            <div class="form-group mr-3">
                                <label for="fecha_fin" class="mr-2"><strong>Hasta:</strong></label>
                                <input type="date" name="fecha_fin" id="fecha_fin" class="form-control" value="<?php echo $fecha_fin; ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary mr-2">Filtrar</button>
                            <button type="button" onclick="resetFiltros()" class="btn btn-secondary">Limpiar</button>
                        </form>
                    </div>

                    <!-- Nombre de la empresa seleccionada -->
                    <?php if ($empresa_filtro): ?>
                        <?php 
                        $empresa_seleccionada = array_filter($empresas, fn($e) => $e['id'] == $empresa_filtro);
                        $empresa_seleccionada = reset($empresa_seleccionada);
                        ?>
                        <div class="alert alert-info alert-dismissible fade show" role="alert">
                            <i class="fas fa-building"></i>
                            <strong>Empresa Filtrada:</strong> <?php echo htmlspecialchars($empresa_seleccionada['nombre_comercial']); ?>
                            | <strong>Período:</strong> <?php echo date('d/m/Y', strtotime($fecha_inicio)); ?> - <?php echo date('d/m/Y', strtotime($fecha_fin)); ?>
                            <button type="button" class="close" onclick="location.href='dashboard.php';">
                                <span>&times;</span>
                            </button>
                        </div>
                    <?php endif; ?>

                    <!-- Acciones Rápidas - MOVIDA ARRIBA -->
                    <div class="quick-actions-row">
                        <div class="quick-actions-container">
                            <h5 class="mb-3"><i class="fas fa-bolt mr-2"></i>Acciones Rápidas</h5>
                            <div class="row">
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="../md_facturacion/facturacion_nueva.php" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-file-invoice-dollar"></i>
                                        </div>
                                        <div>Nueva Factura</div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="../md_clientes/cliente_nuevo.php" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-user-plus"></i>
                                        </div>
                                        <div>Nuevo Cliente</div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="../md_productos/producto_nuevo.php" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-box"></i>
                                        </div>
                                        <div>Nuevo Producto</div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="../md_bodegas/bodega_nuevo.php" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-warehouse"></i>
                                        </div>
                                        <div>Nueva Bodega</div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="../md_empresa/empresa_nueva.php" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-building"></i>
                                        </div>
                                        <div>Nueva Empresa</div>
                                    </a>
                                </div>
                                <div class="col-md-2 col-sm-4 col-6 mb-3">
                                    <a href="#" class="quick-action">
                                        <div class="quick-action-icon">
                                            <i class="fas fa-chart-bar"></i>
                                        </div>
                                        <div>Ver Reportes</div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Comprobantes -->
                    <div class="row row-equal">
                        <!-- Total Facturas -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Total Facturas</h6>
                                    <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_facturas']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>

                        <!-- Liquidaciones de Compra -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Liquidaciones</h6>
                                    <div class="stat-icon"><i class="fas fa-shopping-bag"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_liquidaciones']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>


                        <!-- Notas de Crédito -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Notas de Crédito</h6>
                                    <div class="stat-icon"><i class="fas fa-sticky-note"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_notas_credito']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>


                        <!-- Notas de Débito -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Notas de Débito</h6>
                                    <div class="stat-icon"><i class="fas fa-file-invoice"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_notas_debito']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>


                        <!-- Guías de Remisión -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Guías de Remisión</h6>
                                    <div class="stat-icon"><i class="fas fa-truck-loading"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_guias']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>


                        <!-- Retenciones -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Retenciones</h6>
                                    <div class="stat-icon"><i class="fas fa-hand-holding-usd"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_retenciones']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- Estadísticas -->
                    <div class="row row-equal">
                        <!-- Facturas del Mes -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Facturas Este Mes</h6>
                                    <div class="stat-icon"><i class="fas fa-calendar-alt"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['facturas_mes']); ?></div>
                                    <div class="text-muted">de <?php echo fechaEspanol('F'); ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Ingresos Totales -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Ingresos Totales</h6>
                                    <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number">$<?php echo number_format($stats['ingresos_total'], 2); ?></div>
                                    <div class="text-muted">Facturas autorizadas</div>
                                </div>
                            </div>
                        </div>

                        <!-- Ingresos del Mes -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Ingresos Este Mes</h6>
                                    <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number">$<?php echo number_format($stats['ingresos_mes'], 2); ?></div>
                                    <div class="text-muted"><?php echo fechaEspanol('F'); ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row row-equal">
                        <!-- Promedio de Venta -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Promedio por Venta</h6>
                                    <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number">$<?php echo number_format($stats['promedio_venta'], 2); ?></div>
                                    <div class="text-muted">Valor promedio</div>
                                </div>
                            </div>
                        </div>


                        <!-- Clientes Activos -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Clientes Activos</h6>
                                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['clientes_activos']); ?></div>
                                    <div class="text-muted">En período seleccionado</div>
                                </div>
                            </div>
                        </div>


                        <!-- Total Clientes -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Total Clientes</h6>
                                    <div class="stat-icon"><i class="fas fa-address-book"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_clientes']); ?></div>
                                    <div class="text-muted">Registrados en sistema</div>
                                </div>
                            </div>
                        </div>


                        <!-- Total Productos -->
                        <div class="col-xl-2 col-md-4 col-sm-6 mb-3">
                            <div class="stat-card">
                                <div class="card-header-custom d-flex justify-content-between align-items-center">
                                    <h6 class="m-0 font-weight-bold">Total Productos</h6>
                                    <div class="stat-icon"><i class="fas fa-boxes"></i></div>
                                </div>
                                <div class="card-body">
                                    <div class="stat-number"><?php echo number_format($stats['total_productos']); ?></div>
                                    <div class="text-muted">En inventario</div>
                                </div>
                            </div>
                        </div>


                    </div>                    <!-- Sección Corregida - Layout Mejorado -->
                    <div class="row">
                        <!-- Columna Principal (Izquierda) -->
                        <div class="col-lg-8">
                            <!-- Gráficos Mejorados -->
                            <div class="row">
                                <div class="col-lg-12">
                                    <div class="dashboard-card mb-4">
                                        <div class="card-header-custom">
                                            <h6 class="m-0 font-weight-bold">Ventas - Últimos 6 Meses</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="ventasChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="col-lg-12">
                                    <div class="dashboard-card mb-4">
                                        <div class="card-header-custom">
                                            <h6 class="m-0 font-weight-bold">Ventas - Últimos 12 Meses</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="chart-container">
                                                <canvas id="ventasAnualChart"></canvas>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Estado de Facturas -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header-custom">
                                    <h6 class="m-0 font-weight-bold">Estado de Facturas</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($stats['estados_facturas'])): ?>
                                        <?php foreach ($stats['estados_facturas'] as $estado): ?>
                                            <?php
                                            $badgeClass = '';
                                            switch ($estado['estado_xml']) {
                                                case 'AUTORIZADO': $badgeClass = 'success'; break;
                                                case 'RECIBIDA': $badgeClass = 'secondary'; break;
                                                case 'FIRMADO': $badgeClass = 'warning'; break;
                                                case 'GENERADO': $badgeClass = 'info'; break;
                                                case 'DEVUELTA': $badgeClass = 'danger'; break;
                                                default: $badgeClass = 'secondary';
                                            }
                                            ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between">
                                                    <span><?php echo $estado['estado_xml']; ?></span>
                                                    <strong><?php echo $estado['total']; ?></strong>
                                                </div>
                                                <div class="progress">
                                                    <div class="progress-bar bg-<?php echo $badgeClass; ?>" role="progressbar"
                                                         style="width: <?php echo ($estado['total'] / max(1, $stats['total_facturas'])) * 100; ?>%"></div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No hay facturas en el período seleccionado</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Últimas Facturas -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header-custom">
                                    <h6 class="m-0 font-weight-bold">Últimas Facturas</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($stats['ultimas_facturas'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th># Factura</th>
                                                        <th>Cliente</th>
                                                        <th>Estado</th>
                                                        <th>Total</th>
                                                        <th>Fecha</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($stats['ultimas_facturas'] as $factura): ?>
                                                        <tr>
                                                            <td><strong>#<?php echo $factura['id']; ?></strong></td>
                                                            <td><?php echo htmlspecialchars(mb_strimwidth($factura['cliente_nombre'], 0, 20, '...')); ?></td>
                                                            <td>
                                                                <span class="badge badge-<?php echo $factura['estado_xml'] == 'AUTORIZADO' ? 'success' : ($factura['estado_xml'] == 'FIRMADO' ? 'warning' : 'secondary'); ?>">
                                                                    <?php echo $factura['estado_xml']; ?>
                                                                </span>
                                                            </td>
                                                            <td><strong>$<?php echo number_format($factura['total'], 2); ?></strong></td>
                                                            <td><small class="text-muted"><?php echo date('d/m/Y', strtotime($factura['fecha_emision'])); ?></small></td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No hay facturas recientes</p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Productos Más Vendidos -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header-custom">
                                    <h6 class="m-0 font-weight-bold">Top 10 Productos Más Vendidos</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($stats['productos_mas_vendidos'])): ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Producto</th>
                                                        <th class="text-center">Cantidad</th>
                                                        <th class="text-right">Monto Total</th>
                                                        <th class="text-center">%</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $total_vendido = array_sum(array_column($stats['productos_mas_vendidos'], 'total_vendido'));
                                                    $total_monto = array_sum(array_column($stats['productos_mas_vendidos'], 'monto_total'));
                                                    ?>
                                                    <?php foreach ($stats['productos_mas_vendidos'] as $producto): ?>
                                                        <tr>
                                                            <td><?php echo htmlspecialchars(mb_strimwidth($producto['nombre'], 0, 25, '...')); ?></td>
                                                            <td class="text-center"><?php echo number_format($producto['total_vendido']); ?></td>
                                                            <td class="text-right">$<?php echo number_format($producto['monto_total'], 2); ?></td>
                                                            <td class="text-center">
                                                                <span class="badge badge-info">
                                                                    <?php echo number_format(($producto['total_vendido'] / max(1, $total_vendido)) * 100, 1); ?>%
                                                                </span>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No hay datos de ventas en el período seleccionado</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Columna Lateral (Derecha) -->
                        <div class="col-lg-4">
                            <!-- Productos con Menos Stock - MOVIDA ARRIBA -->
                            <div class="dashboard-card mb-4">
                                <div class="card-header-custom">
                                    <h6 class="m-0 font-weight-bold">Productos con Menos Stock</h6>
                                </div>
                                <div class="card-body">
                                    <?php if (!empty($productos_bajo_stock)): ?>
                                        <?php foreach ($productos_bajo_stock as $producto): ?>
                                            <?php
                                            // Calcular porcentaje y determinar color
                                            $stock_minimo = isset($producto['stock_minimo']) ? $producto['stock_minimo'] : 10;
                                            $porcentaje = ($producto['stock'] / max(1, $stock_minimo)) * 100;
                                            $clase_progress = ($porcentaje <= 20) ? 'danger' : (($porcentaje <= 50) ? 'warning' : 'info');
                                            $alerta_stock = ($producto['stock'] <= $stock_minimo);
                                            ?>
                                            <div class="mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-1">
                                                    <span class="small font-weight-bold"><?php echo htmlspecialchars(mb_strimwidth($producto['nombre'], 0, 25, '...')); ?></span>
                                                    <strong class="small <?php echo $alerta_stock ? 'text-danger' : ''; ?>"><?php echo $producto['stock']; ?> uds.</strong>
                                                </div>
                                                <div class="progress" style="height: 6px;">
                                                    <div class="progress-bar bg-<?php echo $clase_progress; ?>" 
                                                         role="progressbar" 
                                                         style="width: <?php echo min(100, $porcentaje); ?>%">
                                                    </div>
                                                </div>
                                                <div class="d-flex justify-content-between mt-1">
                                                    <small class="text-muted">Mín: <?php echo $stock_minimo; ?></small>
                                                    <small class="<?php echo $alerta_stock ? 'text-danger font-weight-bold' : 'text-success'; ?>">
                                                        <?php echo $alerta_stock ? '¡Stock bajo!' : 'OK'; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        <div class="text-center mt-3">
                                            <a href="../md_productos/producto_lista.php" class="btn btn-sm btn-outline-primary">Gestionar Inventario</a>
                                        </div>
                                    <?php else: ?>
                                        <p class="text-muted text-center">No hay productos con stock registrado</p>
                                        <div class="text-center">
                                            <a href="../md_productos/producto_nuevo.php" class="btn btn-sm btn-primary">Agregar Productos</a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Resumen del Sistema - MOVIDA ARRIBA -->
                            <div class="dashboard-card">
                                <div class="card-header-custom">
                                    <h6 class="m-0 font-weight-bold">Resumen del Sistema</h6>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span class="font-weight-bold">Empresas:</span>
                                        <span class="badge badge-primary badge-pill"><?php echo count($empresas); ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span class="font-weight-bold">Bodegas:</span>
                                        <span class="badge badge-info badge-pill"><?php echo $stats['total_bodegas']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span class="font-weight-bold">Clientes:</span>
                                        <span class="badge badge-success badge-pill"><?php echo $stats['total_clientes']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2 p-2 bg-light rounded">
                                        <span class="font-weight-bold">Productos:</span>
                                        <span class="badge badge-warning badge-pill"><?php echo $stats['total_productos']; ?></span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center p-2 bg-light rounded">
                                        <span class="font-weight-bold">Facturas (período):</span>
                                        <span class="badge badge-dark badge-pill"><?php echo $stats['total_facturas']; ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // Gráfico de Ventas - Últimos 6 Meses
            const ctx = document.getElementById('ventasChart').getContext('2d');
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode($labels); ?>,
                    datasets: [{
                        label: 'Cantidad de Facturas',
                        data: <?php echo json_encode($ventas_cantidad); ?>,
                        backgroundColor: 'rgba(78, 115, 223, 0.6)',
                        borderColor: 'rgba(78, 115, 223, 1)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    }, {
                        label: 'Monto Total ($)',
                        data: <?php echo json_encode($ventas_monto); ?>,
                        type: 'line',
                        fill: false,
                        borderColor: 'rgba(28, 200, 138, 1)',
                        tension: 0.1,
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Cantidad de Facturas' }
                        },
                        y1: {
                            position: 'right',
                            beginAtZero: true,
                            title: { display: true, text: 'Monto ($)' },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });

            // Gráfico de Ventas - Últimos 12 Meses
            const ctxAnual = document.getElementById('ventasAnualChart').getContext('2d');
            new Chart(ctxAnual, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($labels_anual); ?>,
                    datasets: [{
                        label: 'Cantidad de Facturas',
                        data: <?php echo json_encode($ventas_anual_cantidad); ?>,
                        borderColor: 'rgba(78, 115, 223, 1)',
                        backgroundColor: 'rgba(78, 115, 223, 0.1)',
                        tension: 0.3,
                        fill: true
                    }, {
                        label: 'Monto Total ($)',
                        data: <?php echo json_encode($ventas_anual_monto); ?>,
                        borderColor: 'rgba(220, 53, 69, 1)',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });

            // Función para limpiar filtros
            window.resetFiltros = function() {
                document.getElementById('empresa_id').value = '';
                document.getElementById('fecha_inicio').value = '';
                document.getElementById('fecha_fin').value = '';
                document.querySelector('form').submit();
            }

            // Ajustar altura de tarjetas
            function adjustCardHeights() {
                $('.row-equal').each(function() {
                    let highestCard = 0;
                    $(this).find('.stat-card, .dashboard-card').height('auto');
                    $(this).find('.stat-card, .dashboard-card').each(function() {
                        if ($(this).height() > highestCard) {
                            highestCard = $(this).height();
                        }
                    });
                    $(this).find('.stat-card, .dashboard-card').height(highestCard);
                });
            }

            adjustCardHeights();
            $(window).resize(adjustCardHeights);
        });
    </script>
</body>
</html>