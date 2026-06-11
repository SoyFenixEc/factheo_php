<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT f.*, c.razon_social AS cli_rs, c.identificacion AS cli_id, c.direccion AS cli_dir,
                               e.nombre_comercial AS emp_nc, e.ruc, e.razon_social AS emp_rs,
                               fp.nombre AS fp_nombre
                        FROM facturas f
                        JOIN clientes c ON f.cliente_id = c.id
                        JOIN empresa e ON f.empresa_id = e.id
                        JOIN formas_pago fp ON f.forma_pago_id = fp.id
                        WHERE f.id = ? AND f.tipo_comprobante_id = '05'");
$stmt->execute([$id]);
$nc = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$nc) die("<div class='alert alert-danger'>Nota de Débito no encontrada.</div>");

$stmt_d = $pdo->prepare("SELECT df.*, COALESCE(p.nombre,'PRODUCTO') AS nombre, COALESCE(p.codigo,'') AS codigo FROM detalle_factura df LEFT JOIN productos p ON df.producto_id = p.id WHERE df.factura_id = ?");
$stmt_d->execute([$id]);
$detalles = $stmt_d->fetchAll();

$num = str_pad($nc['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($nc['secuencial'],9,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?><title>Nota de Débito #<?= $num ?></title></head>
<body>
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar"><?php require('../entorno/menu.php'); ?></ul>
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
                        <h1 class="h3 mb-0 text-gray-800">Nota de Débito #<?= $num ?></h1>
                        <a href="nota_debito_lista.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
<a href="nota_debito_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a> <a href="nota_debito_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                    </div>

                    <?php if ($nc['estado_xml'] == 'AUTORIZADO'): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> AUTORIZADA - Nro: <?= $nc["numero_autorizacion"] ?? "Pendiente" ?> <a href="nota_debito_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger float-right" target="_blank"><i class="fas fa-file-pdf"></i> Descargar PDF</a></div>
                    <?php endif; ?>

                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Información</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Empresa:</strong> <?= htmlspecialchars($nc['emp_nc']) ?></p>
                                    <p><strong>RUC:</strong> <?= $nc['ruc'] ?></p>
                                    <p><strong>Cliente:</strong> <?= htmlspecialchars($nc['cli_rs']) ?></p>
                                    <p><strong>Identificación:</strong> <?= $nc['cli_id'] ?></p>
                                    <p><strong>Dirección:</strong> <?= htmlspecialchars($nc['cli_dir'] ?? '') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha:</strong> <?= date('d/m/Y H:i', strtotime($nc['fecha_emision'])) ?></p>
                                    <p><strong>Factura Modificada:</strong> <?= $nc['numDocModificado'] ?></p>
                                    <p><strong>Motivo:</strong> <?= htmlspecialchars($nc['motivo'] ?? '') ?></p>
                                    <p><strong>Estado:</strong> <span class="badge badge-<?= $nc['estado_xml']=='AUTORIZADO'?'success':'info' ?>"><?= $nc['estado_xml'] ?></span></p>
                                    <p><strong>Forma de Pago:</strong> <?= $nc['fp_nombre'] ?></p>
                                </div>
                            </div>
                            <?php if ($nc['clave_acceso']): ?>
                            <p><strong>Clave de Acceso:</strong><br><code><?= $nc['clave_acceso'] ?></code></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Productos</h6></div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr><th>Código</th><th>Producto</th><th>Cantidad</th><th>Precio</th><th>Subtotal</th><th>IVA</th><th>Total</th></tr>
                                </thead>
                                <tbody>
                                    <?php $s=0;$v=0;$t=0; foreach ($detalles as $d): 
                                        $s+=$d['subtotal'];$v+=$d['iva'];$t+=$d['total']; ?>
                                        <tr>
                                            <td><?= $d['codigo'] ?></td>
                                            <td><?= htmlspecialchars($d['nombre']) ?></td>
                                            <td><?= $d['cantidad'] ?></td>
                                            <td class="text-right">$ <?= number_format($d['precio_unitario'],2) ?></td>
                                            <td class="text-right">$ <?= number_format($d['subtotal'],2) ?></td>
                                            <td class="text-right">$ <?= number_format($d['iva'],2) ?></td>
                                            <td class="text-right">$ <?= number_format($d['total'],2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="font-weight-bold"><td colspan="4" class="text-right">TOTALES:</td>
                                        <td class="text-right">$ <?= number_format($s,2) ?></td>
                                        <td class="text-right">$ <?= number_format($v,2) ?></td>
                                        <td class="text-right">$ <?= number_format($t,2) ?></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>

                    <?php if($nc['estado_xml'] != 'AUTORIZADO'): ?>
                    <div class="text-center mb-4">
                        <a href="nota_debito_completa.php?id=<?= $nc['id'] ?>" class="btn btn-success btn-lg"><i class="fas fa-check"></i> AUTORIZAR</a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>
</body>
</html>
