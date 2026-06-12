<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];
$msg = $_GET['msg'] ?? null;
$nc_id = $_GET['id'] ?? null;
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
    $sql = "SELECT f.id, f.fecha_emision, f.total, f.estado_xml, f.clave_acceso,
                   f.establecimiento, f.punto_emision, f.secuencial, f.fecha_autorizacion,
                   f.subtotal1, f.valor_iva, f.motivo, f.codDocModificado, f.numDocModificado,
                   c.razon_social AS cliente_nombre, c.identificacion AS cliente_iden,
                   e.nombre_comercial AS empresa_nombre, e.ruc, e.activa AS emp_activa,
                   fp.nombre AS forma_pago
            FROM facturas f
            JOIN clientes c ON f.cliente_id = c.id
            JOIN empresa e ON f.empresa_id = e.id
            JOIN formas_pago fp ON f.forma_pago_id = fp.id
            WHERE e.usuario_id = ? AND f.tipo_comprobante_id = '04'";
    $params = [$usuario_id];

    if ($filtro_empresa > 0) { $sql .= " AND f.empresa_id = ?"; $params[] = $filtro_empresa; }
    $sql .= " AND DATE(f.fecha_emision) BETWEEN ? AND ?"; $params[] = $fecha_inicio; $params[] = $fecha_fin;
    if ($filtro_estado !== 'all') { $sql .= " AND f.estado_xml = ?"; $params[] = $filtro_estado; }
    if (!empty($search)) { $sql .= " AND (f.id LIKE ? OR c.razon_social LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
    $sql .= " ORDER BY f.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notas = $stmt->fetchAll();
    $total_sub = array_sum(array_column($notas, 'subtotal1'));
    $total_iva = array_sum(array_column($notas, 'valor_iva'));
    $total_gnl = array_sum(array_column($notas, 'total'));
} catch (Exception $e) {
    $notas = []; $total_sub = 0; $total_iva = 0; $total_gnl = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?>
    <title>Notas de Crédito</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Notas de Crédito</h1>
                        <a href="nota_credito_nueva.php" class="btn btn-primary shadow-sm"><i class="fas fa-plus fa-sm text-white-50"></i> Nueva Nota de Crédito</a>
                    </div>

                    <?php if ($msg == 'success'): ?>
                        <div class="alert alert-success alert-dismissible"><i class="fas fa-check-circle"></i> Nota de Crédito #<?= $nc_id ?> generada correctamente. <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>
                    <?php if ($msg == 'error' && $error): ?>
                        <div class="alert alert-danger alert-dismissible"><i class="fas fa-exclamation-circle"></i> <strong>Error:</strong> <?= htmlspecialchars($error) ?> <button type="button" class="close" data-dismiss="alert">&times;</button></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary"><i class="fas fa-file-invoice"></i> Lista de Notas de Crédito</h6>
                            <span class="badge badge-primary">Total: <?= count($notas) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead class="thead-light">
                                        <tr>
                                            <th># NC</th><th>Empresa</th><th>Cliente</th><th>RUC/CI</th><th>Fecha</th><th>Motivo</th>
                                            <th>Factura Modif.</th><th>Subtotal</th><th>IVA</th><th>Total</th><th>Estado</th><th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (count($notas) > 0): ?>
                                            <?php foreach ($notas as $nc): 
                                                $num = str_pad($nc['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['secuencial'],9,'0',STR_PAD_LEFT);
                                            ?>
                                                <tr>
                                                    <td><strong><?= $num ?></strong></td>
                                                    <td><?= htmlspecialchars($nc['empresa_nombre']) ?></td>
                                                    <td><?= htmlspecialchars($nc['cliente_nombre']) ?></td>
                                                    <td><?= $nc['cliente_iden'] ?></td>
                                                    <td><?= date('d/m/Y', strtotime($nc['fecha_emision'])) ?></td>
                                                    <td><?= htmlspecialchars($nc['motivo'] ?? '') ?></td>
                                                    <td><?= $nc['numDocModificado'] ?></td>
                                                    <td class="text-right">$ <?= number_format($nc['subtotal1'],2) ?></td>
                                                    <td class="text-right">$ <?= number_format($nc['valor_iva'],2) ?></td>
                                                    <td class="text-right"><strong>$ <?= number_format($nc['total'],2) ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?= $nc['estado_xml']=='GENERADO'?'info':($nc['estado_xml']=='FIRMADO'?'warning':($nc['estado_xml']=='RECIBIDA'?'secondary':($nc['estado_xml']=='DEVUELTA'?'dark':($nc['estado_xml']=='AUTORIZADO'?'success':'danger')))) ?>">
                                                            <?= $nc['estado_xml'] ?>
                                                        </span>
                                                    </td>
                                                    <td class="text-center">
                                                        <a href="nota_credito_ver.php?id=<?= $nc['id'] ?>" class="btn btn-sm btn-primary" title="Ver"><i class="fas fa-eye"></i></a>
                                                        <?php if($nc['estado_xml'] != 'AUTORIZADO' && $nc['emp_activa']): ?>
                                                            <a href="nota_credito_completa.php?id=<?= $nc['id'] ?>" class="btn btn-sm btn-success" title="Autorizar"><i class="fas fa-check"></i></a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="font-weight-bold bg-light">
                                                <td colspan="7" class="text-right">TOTALES:</td>
                                                <td class="text-right">$ <?= number_format($total_sub,2) ?></td>
                                                <td class="text-right">$ <?= number_format($total_iva,2) ?></td>
                                                <td class="text-right">$ <?= number_format($total_gnl,2) ?></td>
                                                <td colspan="2"></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr><td colspan="12" class="text-center py-4"><i class="fas fa-file-invoice-dollar fa-3x text-gray-300 mb-3 d-block"></i>No hay Notas de Crédito.</td></tr>
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
