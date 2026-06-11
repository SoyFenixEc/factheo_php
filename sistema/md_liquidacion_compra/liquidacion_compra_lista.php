<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

$msg = $_GET['msg'] ?? null;
$liqui_id = $_GET['id'] ?? null;
$error = $_GET['error'] ?? null;

// Filtros
$filtro_estado = $_POST['filtro_estado'] ?? $_GET['filtro_estado'] ?? 'all';
$filtro_empresa = isset($_POST['filtro_empresa']) ? (int)$_POST['filtro_empresa'] : (isset($_GET['filtro_empresa']) ? (int)$_GET['filtro_empresa'] : 0);
$fecha_inicio = $_POST['fecha_inicio'] ?? $_GET['fecha_inicio'] ?? date('Y-m-01');
$fecha_fin = $_POST['fecha_fin'] ?? $_GET['fecha_fin'] ?? date('Y-m-t');
$search = $_POST['search'] ?? $_GET['search'] ?? '';

if ($fecha_inicio > $fecha_fin) {
    $temp = $fecha_inicio; $fecha_inicio = $fecha_fin; $fecha_fin = $temp;
}

// Empresas del usuario
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :uid AND activa = 1 ORDER BY nombre_comercial";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->execute([':uid' => $usuario_id]);
$empresas = $stmt_empresas->fetchAll();

// Consulta liquidaciones (tipo_comprobante_id = '03')
try {
    $sql = "
        SELECT 
            f.id, f.fecha_emision, f.total, f.estado_xml, f.clave_acceso,
            f.establecimiento, f.punto_emision, f.secuencial,
            f.fecha_autorizacion, f.subtotal1, f.valor_iva,
            f.numero_autorizacion, f.observacion_sri,
            pv.nombre AS proveedor_nombre,
            pv.ruc AS proveedor_ruc,
            e.nombre_comercial AS empresa_nombre,
            e.ruc AS empresa_ruc,
            e.activa AS empresa_activa,
            fp.nombre AS forma_pago
        FROM facturas f
        JOIN proveedores pv ON f.proveedor_id = pv.id
        JOIN empresa e ON f.empresa_id = e.id
        JOIN formas_pago fp ON f.forma_pago_id = fp.id
        WHERE e.usuario_id = :uid AND f.tipo_comprobante_id = '03'
    ";
    $params = [':uid' => $usuario_id];

    if ($filtro_empresa > 0) {
        $sql .= " AND f.empresa_id = :eid";
        $params[':eid'] = $filtro_empresa;
    }
    $sql .= " AND DATE(f.fecha_emision) BETWEEN :fi AND :ff";
    $params[':fi'] = $fecha_inicio;
    $params[':ff'] = $fecha_fin;
    if ($filtro_estado !== 'all') {
        $sql .= " AND f.estado_xml = :est";
        $params[':est'] = $filtro_estado;
    }
    if (!empty($search)) {
        $sql .= " AND (pv.nombre LIKE :s OR e.nombre_comercial LIKE :s OR pv.ruc LIKE :s)";
        $params[':s'] = "%$search%";
    }
    $sql .= " ORDER BY f.id DESC";

    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) $stmt->bindValue($k, $v);
    $stmt->execute();
    $liquidaciones = $stmt->fetchAll();

    $total_subtotal = array_sum(array_column($liquidaciones, 'subtotal1'));
    $total_iva = array_sum(array_column($liquidaciones, 'valor_iva'));
    $total_general = array_sum(array_column($liquidaciones, 'total'));
} catch (PDOException $e) {
    error_log("Error liquidacion_compra_lista: " . $e->getMessage());
    $liquidaciones = [];
    $total_subtotal = $total_iva = $total_general = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?>
    <title>Liquidaciones de Compra</title>
    <style>
        .badge-estado { font-size: 0.75rem; padding: 5px 10px; border-radius: 20px; }
        .filter-container { background: #f8f9fc; border-radius: 12px; padding: 20px; margin-bottom: 20px; border: 1px solid #e3e6f0; }
        .filter-row { display: flex; flex-wrap: wrap; gap: 15px; align-items: flex-end; }
        .filter-group { flex: 1; min-width: 150px; }
        .filter-group label { margin-bottom: 5px; font-weight: 600; font-size: 0.85rem; color: #5a5c69; }
        .totals-row { background-color: #f8f9fc; font-weight: bold; border-top: 2px solid #4e73df; }
        .totals-row td { background-color: #f8f9fc; font-weight: bold; }
        .clave-acceso { font-family: monospace; font-size: 0.8rem; word-break: break-all; }
        @media (max-width: 768px) { .filter-group { min-width: 100%; } }
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
                        <h1 class="h3 mb-0 text-gray-800">Liquidaciones de Compra</h1>
                        <a href="liquidacion_compra_nueva.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Liquidación
                        </a>
                    </div>

                    <?php if ($msg == 'success'): ?>
                        <div class="alert alert-success alert-dismissible fade show"><i class="fas fa-check-circle"></i> Liquidación #<?= $liqui_id ?> generada correctamente.<button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>
                    <?php if ($msg == 'error' && $error): ?>
                        <div class="alert alert-danger alert-dismissible fade show"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?><button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <!-- Filtros -->
                    <div class="filter-container">
                        <form method="POST">
                            <div class="filter-row">
                                <div class="filter-group">
                                    <label>Empresa</label>
                                    <select name="filtro_empresa" class="form-control">
                                        <option value="0">Todas</option>
                                        <?php foreach ($empresas as $e): ?>
                                            <option value="<?= $e['id'] ?>" <?= $filtro_empresa == $e['id'] ? 'selected' : '' ?>><?= htmlspecialchars($e['nombre_comercial']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Desde</label>
                                    <input type="date" name="fecha_inicio" class="form-control" value="<?= $fecha_inicio ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Hasta</label>
                                    <input type="date" name="fecha_fin" class="form-control" value="<?= $fecha_fin ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Estado</label>
                                    <select name="filtro_estado" class="form-control">
                                        <option value="all" <?= $filtro_estado == 'all' ? 'selected' : '' ?>>Todos</option>
                                        <option value="GENERADO" <?= $filtro_estado == 'GENERADO' ? 'selected' : '' ?>>Generado</option>
                                        <option value="FIRMADO" <?= $filtro_estado == 'FIRMADO' ? 'selected' : '' ?>>Firmado</option>
                                        <option value="RECIBIDA" <?= $filtro_estado == 'RECIBIDA' ? 'selected' : '' ?>>Recibida</option>
                                        <option value="DEVUELTA" <?= $filtro_estado == 'DEVUELTA' ? 'selected' : '' ?>>Devuelta</option>
                                        <option value="AUTORIZADO" <?= $filtro_estado == 'AUTORIZADO' ? 'selected' : '' ?>>Autorizado</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Buscar</label>
                                    <input type="text" name="search" class="form-control" placeholder="Proveedor, empresa..." value="<?= htmlspecialchars($search) ?>">
                                </div>
                                <div class="filter-actions" style="display:flex;gap:10px;">
                                    <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrar</button>
                                    <a href="liquidacion_compra_lista.php" class="btn btn-secondary"><i class="fas fa-eraser"></i> Limpiar</a>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Tabla -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-shopping-cart"></i> Lista de Liquidaciones</h6>
                            <span class="badge badge-primary">Total: <?= count($liquidaciones) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Empresa</th>
                                            <th>Proveedor</th>
                                            <th>RUC</th>
                                            <th>Fecha Emisión</th>
                                            <th>Subtotal</th>
                                            <th>IVA</th>
                                            <th>Total</th>
                                            <th>Estado</th>
                                            <th class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($liquidaciones) > 0): ?>
                                            <?php foreach ($liquidaciones as $f): 
                                                $num = $f['establecimiento'] . '-' . $f['punto_emision'] . '-' . str_pad($f['secuencial'], 9, '0', STR_PAD_LEFT);
                                            ?>
                                                <tr>
                                                    <td><strong><?= $num ?></strong></td>
                                                    <td><?= htmlspecialchars($f['empresa_nombre']) ?></td>
                                                    <td><?= htmlspecialchars($f['proveedor_nombre']) ?></td>
                                                    <td><?= $f['proveedor_ruc'] ?></td>
                                                    <td><?= date('d/m/Y', strtotime($f['fecha_emision'])) ?></td>
                                                    <td class="text-right">$ <?= number_format($f['subtotal1'], 2) ?></td>
                                                    <td class="text-right">$ <?= number_format($f['valor_iva'], 2) ?></td>
                                                    <td class="text-right"><strong>$ <?= number_format($f['total'], 2) ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?= 
                                                            $f['estado_xml'] == 'GENERADO' ? 'info' :
                                                            ($f['estado_xml'] == 'FIRMADO' ? 'warning' :
                                                            ($f['estado_xml'] == 'RECIBIDA' ? 'secondary' :
                                                            ($f['estado_xml'] == 'DEVUELTA' ? 'dark' :
                                                            ($f['estado_xml'] == 'AUTORIZADO' ? 'success' : 'danger'))))
                                                        ?>"><?= $f['estado_xml'] ?></span>
                                                    </td>
                                                    <td class="text-center">
                                                        <div class="btn-group" role="group">
                                                            <a href="liquidacion_compra_ver.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-primary" title="Ver"><i class="fas fa-eye"></i></a>
                                                            <?php if($f['estado_xml'] != 'AUTORIZADO' && $f['empresa_activa']): ?>
                                                                <a href="liquidacion_compra_completa.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-success" title="Autorizar"><i class="fas fa-check"></i></a>
                                                            <?php endif; ?>
                                                            <a href="pdf_liquidacion.php?id=<?= $f['id'] ?>" target="_blank" class="btn btn-sm btn-danger" title="PDF"><i class="fas fa-file-pdf"></i></a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="totals-row">
                                                <td colspan="5" class="text-right"><strong>TOTALES:</strong></td>
                                                <td class="text-right"><strong>$ <?= number_format($total_subtotal, 2) ?></strong></td>
                                                <td class="text-right"><strong>$ <?= number_format($total_iva, 2) ?></strong></td>
                                                <td class="text-right"><strong>$ <?= number_format($total_general, 2) ?></strong></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="10" class="text-center py-4"><i class="fas fa-shopping-cart fa-3x text-gray-300 mb-3"></i><p>No hay liquidaciones de compra</p></td></tr>
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
    <script>$(document).ready(function(){ $('.alert').delay(5000).fadeOut(500); });</script>
</body>
</html>
