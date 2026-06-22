<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];
$msg = $_GET['msg'] ?? null;
$cr_id = $_GET['id'] ?? null;
$error = $_GET['error'] ?? null;

$filtro_estado = $_POST['filtro_estado'] ?? $_GET['filtro_estado'] ?? 'all';
$filtro_empresa = isset($_POST['filtro_empresa']) ? (int)$_POST['filtro_empresa'] : (isset($_GET['filtro_empresa']) ? (int)$_GET['filtro_empresa'] : 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? $_GET['fecha_fin'] ?? date('Y-m-t');
$search = $_POST['search'] ?? $_GET['search'] ?? '';

if ($fecha_inicio > $fecha_fin) { $t = $fecha_inicio; $fecha_inicio = $fecha_fin; $fecha_fin = $t; }

$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = ? AND activa = 1 ORDER BY nombre_comercial";
$stmt = $pdo->prepare($sql_empresas);
$stmt->execute([$usuario_id]);
$empresas = $stmt->fetchAll(PDO::FETCH_ASSOC);

try {
    $sql = "SELECT cr.id, cr.fecha_emision, cr.total_retenido, cr.estado_xml, cr.clave_acceso,
                   cr.establecimiento, cr.punto_emision, cr.secuencial, cr.fecha_autorizacion,
                   cr.periodo_fiscal, cr.identificacion_sujeto_retenido,
                   cr.razon_social_sujeto_retenido AS sujeto_nombre,
                   e.nombre_comercial AS empresa_nombre, e.ruc, e.activa AS emp_activa
            FROM comprobantes_retencion cr
            JOIN empresa e ON cr.empresa_id = e.id
            WHERE e.usuario_id = ?";
    $params = [$usuario_id];

    if ($filtro_empresa > 0) { $sql .= " AND cr.empresa_id = ?"; $params[] = $filtro_empresa; }
    $sql .= " AND DATE(cr.fecha_emision) BETWEEN ? AND ?"; $params[] = $fecha_inicio; $params[] = $fecha_fin;
    if ($filtro_estado !== 'all') { $sql .= " AND cr.estado_xml = ?"; $params[] = $filtro_estado; }
    if (!empty($search)) { $sql .= " AND (cr.id LIKE ? OR cr.razon_social_sujeto_retenido LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY cr.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $retenciones = $stmt->fetchAll();
    $total_general = array_sum(array_column($retenciones, 'total_retenido'));
} catch (Exception $e) {
    $retenciones = []; $total_general = 0;
    error_log("Error en lista retenciones: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?>
    <title>Comprobantes de Retención</title>
</head>
<body>
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <?php require('../entorno/menu.php'); ?>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
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
                        <h1 class="h3 mb-0 text-gray-800">Comprobantes de Retención</h1>
                        <a href="comprobante_retencion_nueva.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Comprobante de Retención</a>
                    </div>

                    <?php if ($msg == 'success'): ?>
                        <div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle"></i> Comprobante de Retención #<?= $cr_id ?> generado correctamente. <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>
                    <?php if ($msg == 'error' && $error): ?>
                        <div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?> <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-filter"></i> Filtros</h6></div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Período:</label>
                                    <input type="date" class="form-control form-control-sm" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">a</label>
                                    <input type="date" class="form-control form-control-sm" name="fecha_fin" value="<?= $fecha_fin ?>">
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <select class="form-control form-control-sm" name="filtro_empresa">
                                        <option value="0">Todas las empresas</option>
                                        <?php foreach ($empresas as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= $filtro_empresa==$emp['id']?'selected':'' ?>><?= htmlspecialchars($emp['nombre_comercial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <select class="form-control form-control-sm" name="filtro_estado">
                                        <option value="all" <?= $filtro_estado=='all'?'selected':'' ?>>Todos los estados</option>
                                        <option value="PENDIENTE" <?= $filtro_estado=='PENDIENTE'?'selected':'' ?>>PENDIENTE</option>
                                        <option value="GENERADO" <?= $filtro_estado=='GENERADO'?'selected':'' ?>>GENERADO</option>
                                        <option value="FIRMADO" <?= $filtro_estado=='FIRMADO'?'selected':'' ?>>FIRMADO</option>
                                        <option value="RECIBIDA" <?= $filtro_estado=='RECIBIDA'?'selected':'' ?>>RECIBIDA</option>
                                        <option value="DEVUELTA" <?= $filtro_estado=='DEVUELTA'?'selected':'' ?>>DEVUELTA</option>
                                        <option value="AUTORIZADO" <?= $filtro_estado=='AUTORIZADO'?'selected':'' ?>>AUTORIZADO</option>
                                    </select>
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <input type="text" class="form-control form-control-sm" name="search" placeholder="Buscar..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <button type="submit" class="btn btn-sm btn-primary mb-2"><i class="fas fa-search"></i> Filtrar</button>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Lista de Comprobantes de Retención</h6>
                            <span class="badge badge-primary">Total: <?= count($retenciones) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Empresa</th>
                                            <th>Sujeto Retenido</th>
                                            <th>Identificación</th>
                                            <th>Fecha</th>
                                            <th>Período Fiscal</th>
                                            <th>Total Retenido</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($retenciones) > 0): ?>
                                            <?php foreach ($retenciones as $cr): 
                                                $num = str_pad($cr['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['secuencial'],9,'0',STR_PAD_LEFT);
                                            ?>
                                                <tr>
                                                    <td><strong><?= $num ?></strong></td>
                                                    <td><?= htmlspecialchars($cr['empresa_nombre']) ?></td>
                                                    <td><?= htmlspecialchars($cr['sujeto_nombre']) ?></td>
                                                    <td><?= $cr['identificacion_sujeto_retenido'] ?></td>
                                                    <td><?= date('d/m/Y', strtotime($cr['fecha_emision'])) ?></td>
                                                    <td><?= $cr['periodo_fiscal'] ?></td>
                                                    <td class="text-right"><strong>$ <?= number_format($cr['total_retenido'],2) ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?= $cr['estado_xml']=='AUTORIZADO'?'success':($cr['estado_xml']=='GENERADO'?'info':($cr['estado_xml']=='FIRMADO'?'warning':($cr['estado_xml']=='RECIBIDA'?'secondary':($cr['estado_xml']=='DEVUELTA'?'dark':'danger')))) ?>">
                                                            <?= $cr['estado_xml'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="comprobante_retencion_ver.php?id=<?= $cr['id'] ?>" class="btn btn-sm btn-primary" title="Ver"><i class="fas fa-eye"></i></a>
                                                        <?php if($cr['estado_xml'] != 'AUTORIZADO' && $cr['emp_activa']): ?>
                                                            <a href="comprobante_retencion_completa.php?id=<?= $cr['id'] ?>" class="btn btn-sm btn-success" title="Autorizar"><i class="fas fa-check"></i></a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="font-weight-bold bg-light">
                                                <td colspan="6" class="text-right">TOTAL:</td>
                                                <td class="text-right">$ <?= number_format($total_general,2) ?></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="9" class="text-center py-4"><i class="fas fa-file-invoice-dollar fa-3x text-gray-300 mb-3 d-block"></i>No hay Comprobantes de Retención.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>
</body>
</html>
