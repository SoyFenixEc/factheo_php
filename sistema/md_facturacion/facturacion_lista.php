<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener mensajes
$msg = $_GET['msg'] ?? null;
$factura_id = $_GET['factura_id'] ?? null;
$error = $_GET['error'] ?? null;

// Obtener filtros
$filtro_estado = $_POST['filtro_estado'] ?? $_GET['filtro_estado'] ?? 'all';
$filtro_empresa = isset($_POST['filtro_empresa']) ? (int)$_POST['filtro_empresa'] : (isset($_GET['filtro_empresa']) ? (int)$_GET['filtro_empresa'] : 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? $_GET['fecha_fin'] ?? date('Y-m-t');
$search = $_POST['search'] ?? $_GET['search'] ?? '';

// Validar fechas
if ($fecha_inicio > $fecha_fin) {
    $fecha_temp = $fecha_inicio;
    $fecha_inicio = $fecha_fin;
    $fecha_fin = $fecha_temp;
}

// Obtener empresas del usuario para el select
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :usuario_id AND activa = 1 ORDER BY nombre_comercial";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll(PDO::FETCH_ASSOC);

try {
    // Construir la consulta SQL base
    $sql = "
        SELECT 
            f.id, 
            f.fecha_emision, 
            f.total, 
            f.estado_xml, 
            f.clave_acceso, 
            f.establecimiento, 
            f.punto_emision, 
            f.secuencial,
            f.fecha_autorizacion,
            f.subtotal1,
            f.valor_iva,
            c.razon_social AS cliente_nombre,
            c.identificacion AS cliente_identificacion,
            e.nombre_comercial AS empresa_nombre,
            e.ruc AS empresa_ruc,
            e.activa AS empresa_activa,
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
    if ($filtro_estado !== 'all') {
        $sql .= " AND f.estado_xml = :estado";
        $params[':estado'] = $filtro_estado;
    }
    
    // Aplicar búsqueda
    if (!empty($search)) {
        $sql .= " AND (f.id LIKE :search OR c.razon_social LIKE :search OR e.nombre_comercial LIKE :search OR c.identificacion LIKE :search)";
        $params[':search'] = "%$search%";
    }
    
    $sql .= " ORDER BY f.id DESC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $facturas = $stmt->fetchAll();
    
    // Calcular totales
    $total_subtotal = array_sum(array_column($facturas, 'subtotal1'));
    $total_iva = array_sum(array_column($facturas, 'valor_iva'));
    $total_general = array_sum(array_column($facturas, 'total'));
    
} catch (PDOException $e) {
    error_log("Error en facturacion_lista.php: " . $e->getMessage());
    $facturas = [];
    $total_subtotal = 0;
    $total_iva = 0;
    $total_general = 0;
}

// Función para mantener filtros en URLs
function mantenerFiltros($filtro_empresa, $fecha_inicio, $fecha_fin, $filtro_estado, $search) {
    $params = [];
    if ($filtro_empresa > 0) $params[] = "filtro_empresa=$filtro_empresa";
    if ($fecha_inicio) $params[] = "fecha_inicio=$fecha_inicio";
    if ($fecha_fin) $params[] = "fecha_fin=$fecha_fin";
    if ($filtro_estado != 'all') $params[] = "filtro_estado=$filtro_estado";
    if ($search) $params[] = "search=" . urlencode($search);
    return !empty($params) ? '?' . implode('&', $params) : '?';
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php'); 
    ?>
    <title>Gestión de Facturas</title>
    <style>
        .card-factura {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-factura:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-factura-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-factura-body {
            padding: 20px;
        }
        .factura-info {
            margin-bottom: 15px;
        }
        .factura-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .factura-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #4e73df;
            font-size: 1.1rem;
        }
        .factura-info-content {
            flex: 1;
        }
        .badge-estado {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .action-buttons .btn {
            margin-right: 8px;
            margin-bottom: 8px;
            border-radius: 6px;
            font-weight: 500;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .view-toggle-btn {
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        .view-toggle-btn.active {
            background: #4e73df;
            border-color: #4e73df;
            color: white;
        }
        .view-toggle-btn:hover:not(.active) {
            background: #f8f9fa;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: #dddfeb;
            opacity: 0.7;
        }
        .filter-container {
            background: #f8f9fc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 1px solid #e3e6f0;
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            margin-bottom: 5px;
            font-weight: 600;
            font-size: 0.85rem;
            color: #5a5c69;
        }
        .filter-group select, 
        .filter-group input {
            width: 100%;
        }
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        .totals-row {
            background-color: #f8f9fc;
            font-weight: bold;
            border-top: 2px solid #4e73df;
        }
        .totals-row td {
            background-color: #f8f9fc;
            font-weight: bold;
        }
        .table-footer-total {
            background-color: #e3e6f0;
            font-weight: bold;
        }
        .clave-acceso {
            font-family: monospace;
            font-size: 0.8rem;
            background-color: #f8f9fa;
            padding: 5px;
            border-radius: 4px;
            word-break: break-all;
        }
        .clave-toggle {
            color: #4e73df;
            cursor: pointer;
            font-size: 0.8rem;
            margin-left: 5px;
        }
        .empresa-inactiva-badge {
            background-color: #e74a3b !important;
            color: white !important;
        }
        .active-filters {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 10px 15px;
            margin-bottom: 20px;
            border-radius: 6px;
        }
        @media (max-width: 768px) {
            .filter-group {
                min-width: 100%;
            }
            .filter-actions {
                width: 100%;
            }
            .filter-actions button {
                flex: 1;
            }
            .view-toggle {
                flex-direction: column;
                gap: 8px;
            }
            .view-toggle-btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Facturas</h1>
                        <div>
                            <button onclick="exportarPDF()" class="btn btn-danger shadow-sm mr-2">
                                <i class="fas fa-file-pdf"></i> Exportar PDF
                            </button>
                            <a href="facturacion_nueva.php" class="btn btn-primary shadow-sm">
                                <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Factura
                            </a>
                        </div>
                    </div>

                    <!-- Alertas -->
                    <?php if ($msg == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i>
                            <strong>¡Éxito!</strong> Factura #<?= $factura_id ?> generada correctamente.
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>
                    <?php if ($msg == 'error' && $error): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            <button type="button" class="close" data-dismiss="alert">&times;</button>
                        </div>
                    <?php endif; ?>

                    <!-- Filtros Mejorados -->
                    <div class="filter-container">
                        <form method="POST" id="filterForm">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label><i class="fas fa-building"></i> Empresa</label>
                                    <select name="filtro_empresa" class="form-control">
                                        <option value="0">Todas las empresas</option>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?= $empresa['id'] ?>" <?= ($filtro_empresa == $empresa['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($empresa['nombre_comercial']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label><i class="fas fa-calendar-alt"></i> Desde</label>
                                    <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <label><i class="fas fa-calendar-alt"></i> Hasta</label>
                                    <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                                </div>
                                
                                <div class="filter-group">
                                    <label><i class="fas fa-tag"></i> Estado</label>
                                    <select name="filtro_estado" class="form-control">
                                        <option value="all" <?= $filtro_estado == 'all' ? 'selected' : '' ?>>Todos los estados</option>
                                        <option value="GENERADO" <?= $filtro_estado == 'GENERADO' ? 'selected' : '' ?>>Generado</option>
                                        <option value="FIRMADO" <?= $filtro_estado == 'FIRMADO' ? 'selected' : '' ?>>Firmado</option>
                                        <option value="RECIBIDA" <?= $filtro_estado == 'RECIBIDA' ? 'selected' : '' ?>>Recibida</option>
                                        <option value="DEVUELTA" <?= $filtro_estado == 'DEVUELTA' ? 'selected' : '' ?>>Devuelta</option>
                                        <option value="AUTORIZADO" <?= $filtro_estado == 'AUTORIZADO' ? 'selected' : '' ?>>Autorizado</option>
                                    </select>
                                </div>
                                
                                <div class="filter-group">
                                    <label><i class="fas fa-search"></i> Buscar</label>
                                    <input type="text" name="search" class="form-control" placeholder="Factura, cliente, empresa..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                
                                <div class="filter-actions">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter"></i> Filtrar
                                    </button>
                                    <a href="facturacion_lista.php" class="btn btn-secondary">
                                        <i class="fas fa-eraser"></i> Limpiar
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Indicador de filtros activos -->
                    <?php if ($filtro_empresa > 0 || $fecha_inicio != date('Y-m-01') || $fecha_fin != date('Y-m-t') || $filtro_estado != 'all' || !empty($search)): ?>
                        <div class="active-filters">
                            <i class="fas fa-info-circle"></i>
                            <strong>Filtros aplicados:</strong>
                            <?php if ($filtro_empresa > 0): 
                                $empresa_nombre = '';
                                foreach ($empresas as $e) {
                                    if ($e['id'] == $filtro_empresa) {
                                        $empresa_nombre = $e['nombre_comercial'];
                                        break;
                                    }
                                }
                            ?>
                                <span class="badge badge-info">Empresa: <?= htmlspecialchars($empresa_nombre) ?></span>
                            <?php endif; ?>
                            <?php if ($fecha_inicio != date('Y-m-01') || $fecha_fin != date('Y-m-t')): ?>
                                <span class="badge badge-info">Período: <?= date('d/m/Y', strtotime($fecha_inicio)) ?> - <?= date('d/m/Y', strtotime($fecha_fin)) ?></span>
                            <?php endif; ?>
                            <?php if ($filtro_estado != 'all'): ?>
                                <span class="badge badge-info">Estado: <?= $filtro_estado ?></span>
                            <?php endif; ?>
                            <?php if (!empty($search)): ?>
                                <span class="badge badge-info">Búsqueda: <?= htmlspecialchars($search) ?></span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row mb-4">
                        <div class="col-md-12">
                            <div class="view-toggle">
                                <button class="view-toggle-btn active" id="cardViewBtn">
                                    <i class="fas fa-th-large"></i> Vista Tarjetas
                                </button>
                                <button class="view-toggle-btn" id="tableViewBtn">
                                    <i class="fas fa-table"></i> Vista Tabla
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Tabla (Principal) -->
                    <div id="tableView" style="display: block;">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">
                                    <i class="fas fa-file-invoice"></i> Lista de Facturas
                                </h6>
                                <span class="badge badge-primary">Total: <?= count($facturas) ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80"># Factura</th>
                                                <th>Empresa</th>
                                                <th>Cliente</th>
                                                <th>RUC/CI</th>
                                                <th>Fecha Emisión</th>
                                                <th>Fecha Autoriz.</th>
                                                <th>Subtotal</th>
                                                <th>IVA</th>
                                                <th>Total</th>
                                                <th>Forma Pago</th>
                                                <th>Estado</th>
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($facturas) > 0): ?>
                                                <?php foreach ($facturas as $f): 
                                                    $numero_factura = $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . str_pad($f['secuencial'], 9, '0', STR_PAD_LEFT);
                                                    $fecha_autorizacion = $f['fecha_autorizacion'] ? date('d/m/Y', strtotime($f['fecha_autorizacion'])) : '---';
                                                ?>
                                                    <tr>
                                                        <td><strong><?= $numero_factura ?></strong></td>
                                                        <td>
                                                            <?= htmlspecialchars($f['empresa_nombre']) ?>
                                                            <?php if (!$f['empresa_activa']): ?>
                                                                <br><small class="text-danger"><strong>(INACTIVA)</strong></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td><?= htmlspecialchars($f['cliente_nombre']) ?></td>
                                                        <td><?= $f['cliente_identificacion'] ?></td>
                                                        <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                                                        <td><?= $fecha_autorizacion ?></td>
                                                        <td class="text-right">$ <?= number_format($f['subtotal1'], 2) ?></td>
                                                        <td class="text-right">$ <?= number_format($f['valor_iva'], 2) ?></td>
                                                        <td class="text-right"><strong>$ <?= number_format($f['total'], 2) ?></strong></td>
                                                        <td><?= $f['forma_pago'] ?></td>
                                                        <td>
                                                            <span class="badge badge-<?= 
                                                                $f['estado_xml'] == 'GENERADO' ? 'info' :
                                                                ($f['estado_xml'] == 'FIRMADO' ? 'warning' :
                                                                ($f['estado_xml'] == 'RECIBIDA' ? 'secondary' :
                                                                ($f['estado_xml'] == 'DEVUELTA' ? 'dark' :
                                                                ($f['estado_xml'] == 'AUTORIZADO' ? 'success' : 'danger'))))
                                                            ?>">
                                                                <?= $f['estado_xml'] ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-center">
                                                            <div class="btn-group" role="group">
                                                                <a href="facturacion_ver.php?id=<?= $f['id'] ?>" 
                                                                   class="btn btn-sm btn-primary" title="Ver Factura">
                                                                    <i class="fas fa-eye"></i>
                                                                </a>
                                                                <?php if($f['estado_xml'] != 'AUTORIZADO' && $f['empresa_activa']): ?>
                                                                    <a href="facturacion_completa.php?id=<?= $f['id'] ?>" 
                                                                       class="btn btn-sm btn-success" title="Autorizar">
                                                                        <i class="fas fa-check"></i>
                                                                    </a>
                                                                <?php elseif($f['estado_xml'] != 'AUTORIZADO' && !$f['empresa_activa']): ?>
                                                                    <button class="btn btn-sm btn-warning" disabled title="Empresa inactiva">
                                                                        <i class="fas fa-ban"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                                <div class="btn-group btn-group-sm" role="group">
                                                                    <button type="button" class="btn btn-sm btn-danger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                                        <i class="fas fa-file-pdf"></i>
                                                                    </button>
                                                                    <div class="dropdown-menu dropdown-menu-right">
                                                                        <a class="dropdown-item" href="autorizacion/procesos/pdf.php?id=<?= $f['id'] ?>" target="_blank">
                                                                            <i class="fas fa-file-pdf text-danger"></i> PDF
                                                                        </a>
                                                                        <a class="dropdown-item" href="autorizacion/procesos/pdf_ticket.php?id=<?= $f['id'] ?>" target="_blank">
                                                                            <i class="fas fa-receipt text-dark"></i> Ticket
                                                                        </a>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                                
                                                <!-- FILA DE TOTALES -->
                                                <tr class="totals-row">
                                                    <td colspan="6" class="text-right"><strong>TOTALES:</strong></td>
                                                    <td class="text-right"><strong>$ <?= number_format($total_subtotal, 2) ?></strong></td>
                                                    <td class="text-right"><strong>$ <?= number_format($total_iva, 2) ?></strong></td>
                                                    <td class="text-right"><strong>$ <?= number_format($total_general, 2) ?></strong></td>
                                                    <td colspan="3"></td>
                                                </tr>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="12" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-file-invoice-dollar fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay facturas con los filtros seleccionados</p>
                                                            <a href="facturacion_lista.php" class="btn btn-primary mt-3">
                                                                <i class="fas fa-eraser"></i> Limpiar filtros
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vista de Tarjetas (Oculta inicialmente) -->
                    <div id="cardView" style="display: none;">
                        <div class="row">
                            <?php if (count($facturas) > 0): ?>
                                <?php foreach ($facturas as $f): 
                                    $numero_factura = $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . str_pad($f['secuencial'], 9, '0', STR_PAD_LEFT);
                                ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-factura">
                                        <div class="card-factura-header">
                                            <h5 class="mb-0">Factura #<?= $numero_factura ?></h5>
                                            <span class="badge badge-estado badge-<?= 
                                                $f['estado_xml'] == 'GENERADO' ? 'info' :
                                                ($f['estado_xml'] == 'FIRMADO' ? 'warning' :
                                                ($f['estado_xml'] == 'RECIBIDA' ? 'secondary' :
                                                ($f['estado_xml'] == 'DEVUELTA' ? 'dark' :
                                                ($f['estado_xml'] == 'AUTORIZADO' ? 'success' : 'danger'))))
                                            ?>">
                                                <?= $f['estado_xml'] ?>
                                            </span>
                                        </div>
                                        <div class="card-factura-body">
                                            <div class="factura-info">
                                                <div class="factura-info-item">
                                                    <span class="factura-info-icon"><i class="fas fa-building"></i></span>
                                                    <div class="factura-info-content">
                                                        <strong>Empresa:</strong>
                                                        <p class="mb-0"><?= htmlspecialchars($f['empresa_nombre']) ?></p>
                                                    </div>
                                                </div>
                                                <div class="factura-info-item">
                                                    <span class="factura-info-icon"><i class="fas fa-user"></i></span>
                                                    <div class="factura-info-content">
                                                        <strong>Cliente:</strong>
                                                        <p class="mb-0"><?= htmlspecialchars($f['cliente_nombre']) ?></p>
                                                        <small><?= $f['cliente_identificacion'] ?></small>
                                                    </div>
                                                </div>
                                                <div class="factura-info-item">
                                                    <span class="factura-info-icon"><i class="fas fa-calendar"></i></span>
                                                    <div class="factura-info-content">
                                                        <strong>Fecha:</strong>
                                                        <p class="mb-0"><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></p>
                                                    </div>
                                                </div>
                                                <div class="factura-info-item">
                                                    <span class="factura-info-icon"><i class="fas fa-dollar-sign"></i></span>
                                                    <div class="factura-info-content">
                                                        <strong>Total:</strong>
                                                        <p class="mb-0 factura-total">$ <?= number_format($f['total'], 2) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="process-steps text-center mt-3">
                                                <a href="facturacion_ver.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-eye"></i> Ver
                                                </a>
                                                <?php if($f['estado_xml'] != 'AUTORIZADO' && $f['empresa_activa']): ?>
                                                    <a href="facturacion_completa.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i> Autorizar
                                                    </a>
                                                <?php endif; ?>
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" class="btn btn-sm btn-danger dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                        <i class="fas fa-file-pdf"></i>
                                                    </button>
                                                    <div class="dropdown-menu dropdown-menu-right">
                                                        <a class="dropdown-item" href="autorizacion/procesos/pdf.php?id=<?= $f['id'] ?>" target="_blank">
                                                            <i class="fas fa-file-pdf text-danger"></i> PDF
                                                        </a>
                                                        <a class="dropdown-item" href="autorizacion/procesos/pdf_ticket.php?id=<?= $f['id'] ?>" target="_blank">
                                                            <i class="fas fa-receipt text-dark"></i> Ticket
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="empty-state">
                                        <i class="fas fa-file-invoice-dollar"></i>
                                        <h4>No hay facturas</h4>
                                        <p>No se encontraron facturas con los filtros seleccionados.</p>
                                        <a href="facturacion_lista.php" class="btn btn-primary">Limpiar filtros</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>

    <script>
        $(document).ready(function() {
            // Cambiar entre vistas
            $('#cardViewBtn').click(function() {
                $('#cardView').show();
                $('#tableView').hide();
                $(this).addClass('active');
                $('#tableViewBtn').removeClass('active');
            });
            
            $('#tableViewBtn').click(function() {
                $('#cardView').hide();
                $('#tableView').show();
                $(this).addClass('active');
                $('#cardViewBtn').removeClass('active');
            });

            // Ocultar alertas después de 5 segundos
            $('.alert').delay(5000).fadeOut(500);
        });

        // Función para exportar a PDF con los filtros actuales
        function exportarPDF() {
            var filtroEmpresa = document.querySelector('select[name="filtro_empresa"]').value;
            var fechaInicio = document.querySelector('input[name="fecha_inicio"]').value;
            var fechaFin = document.querySelector('input[name="fecha_fin"]').value;
            var filtroEstado = document.querySelector('select[name="filtro_estado"]').value;
            var search = document.querySelector('input[name="search"]').value;
            
            // Construir URL con parámetros
            var url = 'facturacion_lista_pdf.php?';
            if (filtroEmpresa && filtroEmpresa != '0') {
                url += 'filtro_empresa=' + encodeURIComponent(filtroEmpresa) + '&';
            }
            if (fechaInicio) {
                url += 'fecha_inicio=' + encodeURIComponent(fechaInicio) + '&';
            }
            if (fechaFin) {
                url += 'fecha_fin=' + encodeURIComponent(fechaFin) + '&';
            }
            if (filtroEstado && filtroEstado != 'all') {
                url += 'filtro_estado=' + encodeURIComponent(filtroEstado) + '&';
            }
            if (search) {
                url += 'search=' + encodeURIComponent(search);
            }
            
            // Abrir en nueva pestaña
            window.open(url, '_blank');
        }
    </script>
</body>
</html>