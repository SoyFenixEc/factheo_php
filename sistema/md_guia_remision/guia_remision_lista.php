<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];
$msg = $_GET['msg'] ?? null;
$guia_id = $_GET['id'] ?? null;
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
    $sql = "SELECT g.*, e.nombre_comercial AS empresa_nombre, e.ruc, e.activa AS emp_activa
            FROM guias_remision g
            JOIN empresa e ON g.empresa_id = e.id
            WHERE e.usuario_id = ?";
    $params = [$usuario_id];

    if ($filtro_empresa > 0) { $sql .= " AND g.empresa_id = ?"; $params[] = $filtro_empresa; }
    $sql .= " AND DATE(g.fecha_emision) BETWEEN ? AND ?"; $params[] = $fecha_inicio; $params[] = $fecha_fin;
    if ($filtro_estado !== 'all') { $sql .= " AND g.estado_xml = ?"; $params[] = $filtro_estado; }
    if (!empty($search)) { $sql .= " AND (g.id LIKE ? OR g.razon_social_destinatario LIKE ? OR g.razon_social_transportista LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY g.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $guias = $stmt->fetchAll();
} catch (Exception $e) {
    $guias = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?>
    <title>Guías de Remisión</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Guías de Remisión</h1>
                        <a href="guia_remision_nueva.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Nueva Guía</a>
                    </div>

                    <?php if ($msg == 'success'): ?>
                        <div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle"></i> Guía de Remisión #<?= $guia_id ?> generada correctamente. <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>
                    <?php if ($msg == 'error' && $error): ?>
                        <div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?> <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Filtros</h6></div>
                        <div class="card-body">
                            <form method="GET" class="form-inline">
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Desde:</label>
                                    <input type="date" class="form-control" name="fecha_inicio" value="<?= $fecha_inicio ?>">
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Hasta:</label>
                                    <input type="date" class="form-control" name="fecha_fin" value="<?= $fecha_fin ?>">
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Empresa:</label>
                                    <select class="form-control" name="filtro_empresa">
                                        <option value="0">Todas</option>
                                        <?php foreach ($empresas as $emp): ?>
                                            <option value="<?= $emp['id'] ?>" <?= $filtro_empresa == $emp['id'] ? 'selected' : '' ?>><?= htmlspecialchars($emp['nombre_comercial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Estado:</label>
                                    <select class="form-control" name="filtro_estado">
                                        <option value="all">Todos</option>
                                        <option value="PENDIENTE" <?= $filtro_estado == 'PENDIENTE' ? 'selected' : '' ?>>Pendiente</option>
                                        <option value="GENERADO" <?= $filtro_estado == 'GENERADO' ? 'selected' : '' ?>>Generado</option>
                                        <option value="FIRMADO" <?= $filtro_estado == 'FIRMADO' ? 'selected' : '' ?>>Firmado</option>
                                        <option value="RECIBIDA" <?= $filtro_estado == 'RECIBIDA' ? 'selected' : '' ?>>Recibida</option>
                                        <option value="AUTORIZADO" <?= $filtro_estado == 'AUTORIZADO' ? 'selected' : '' ?>>Autorizado</option>
                                        <option value="DEVUELTA" <?= $filtro_estado == 'DEVUELTA' ? 'selected' : '' ?>>Devuelta</option>
                                    </select>
                                </div>
                                <div class="form-group mr-2 mb-2">
                                    <label class="mr-2">Buscar:</label>
                                    <input type="text" class="form-control" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ID, destinatario...">
                                </div>
                                <button type="submit" class="btn btn-primary mb-2"><i class="fas fa-search"></i> Filtrar</button>
                                <a href="guia_remision_lista.php" class="btn btn-secondary mb-2 ml-1"><i class="fas fa-undo"></i></a>
                            </form>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-truck-loading"></i> Lista de Guías de Remisión</h6>
                            <span class="badge badge-primary">Total: <?= count($guias) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Empresa</th>
                                            <th>Destinatario</th>
                                            <th>Transportista</th>
                                            <th>Placa</th>
                                            <th>Fecha Emisión</th>
                                            <th>Motivo</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($guias) > 0): ?>
                                            <?php foreach ($guias as $g): 
                                                $num = str_pad($g['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($g['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($g['secuencial'],9,'0',STR_PAD_LEFT);
                                            ?>
                                                <tr>
                                                    <td><strong><?= $num ?></strong></td>
                                                    <td><?= htmlspecialchars($g['empresa_nombre']) ?></td>
                                                    <td><?= htmlspecialchars($g['razon_social_destinatario'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($g['razon_social_transportista'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($g['placa'] ?? '') ?></td>
                                                    <td><?= date('d/m/Y', strtotime($g['fecha_emision'])) ?></td>
                                                    <td><?= htmlspecialchars($g['motivo_traslado'] ?? '') ?></td>
                                                    <td>
                                                        <span class="badge badge-<?= $g['estado_xml']=='GENERADO'?'info':($g['estado_xml']=='FIRMADO'?'warning':($g['estado_xml']=='RECIBIDA'?'secondary':($g['estado_xml']=='DEVUELTA'?'dark':($g['estado_xml']=='AUTORIZADO'?'success':'danger')))) ?>">
                                                            <?= $g['estado_xml'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="guia_remision_ver.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-primary" title="Ver"><i class="fas fa-eye"></i></a>
                                                        <?php if($g['estado_xml'] != 'AUTORIZADO' && $g['emp_activa']): ?>
                                                            <a href="guia_remision_completa.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-success" title="Autorizar"><i class="fas fa-check"></i></a>
                                                        <?php endif; ?>
                                                        <?php if($g['estado_xml'] == 'AUTORIZADO'): ?>
                                                            <a href="guia_remision_pdf.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-danger" title="PDF" target="_blank"><i class="fas fa-file-pdf"></i></a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="9" class="text-center py-4"><i class="fas fa-truck-loading fa-3x text-gray-300 mb-3 d-block"></i>No hay Guías de Remisión.</td></tr>
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
