<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$stmt = $pdo->prepare("SELECT g.*, e.nombre_comercial AS emp_nc, e.ruc, e.razon_social AS emp_rs
                        FROM guias_remision g
                        JOIN empresa e ON g.empresa_id = e.id
                        WHERE g.id = ?");
$stmt->execute([$id]);
$g = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$g) die("<div class='alert alert-danger'>Guía de Remisión no encontrada.</div>");

$stmt_d = $pdo->prepare("SELECT d.*, COALESCE(p.nombre,'PRODUCTO') AS producto_nombre FROM detalle_guia_remision d LEFT JOIN productos p ON d.producto_id = p.id WHERE d.guia_remision_id = ?");
$stmt_d->execute([$id]);
$detalles = $stmt_d->fetchAll();

$num = str_pad($g['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($g['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($g['secuencial'],9,'0',STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?><title>Guía de Remisión #<?= $num ?></title></head>
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
                        <h1 class="h3 mb-0 text-gray-800">Guía de Remisión #<?= $num ?></h1>
                        <div>
                            <a href="guia_remision_lista.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                            <?php if($g['estado_xml'] == 'AUTORIZADO'): ?>
                                <a href="guia_remision_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ($g['estado_xml'] == 'AUTORIZADO'): ?>
                        <div class="alert alert-success"><i class="fas fa-check-circle"></i> AUTORIZADA <a href="guia_remision_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger float-right" target="_blank"><i class="fas fa-file-pdf"></i> Descargar PDF</a></div>
                    <?php endif; ?>
                    <?php if ($g['estado_xml'] == 'DEVUELTA'): ?>
                        <div class="alert alert-warning"><i class="fas fa-exclamation-triangle"></i> RECHAZADA: <?= htmlspecialchars($g['observacion_sri'] ?? 'Error no especificado') ?></div>
                    <?php endif; ?>

                    <!-- Información del Traslado -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Información del Traslado</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Empresa:</strong> <?= htmlspecialchars($g['emp_nc']) ?></p>
                                    <p><strong>RUC:</strong> <?= $g['ruc'] ?></p>
                                    <p><strong>Dirección de Partida:</strong> <?= htmlspecialchars($g['dir_partida'] ?? '') ?></p>
                                    <p><strong>Ruta:</strong> <?= htmlspecialchars($g['ruta'] ?? '') ?></p>
                                    <p><strong>Motivo de Traslado:</strong> <?= htmlspecialchars($g['motivo_traslado'] ?? '') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha Emisión:</strong> <?= date('d/m/Y H:i', strtotime($g['fecha_emision'])) ?></p>
                                    <p><strong>Estado:</strong> <span class="badge badge-<?= $g['estado_xml']=='AUTORIZADO'?'success':($g['estado_xml']=='DEVUELTA'?'warning':'info') ?>"><?= $g['estado_xml'] ?></span></p>
                                    <p><strong>Placa:</strong> <?= htmlspecialchars($g['placa'] ?? '') ?></p>
                                    <p><strong>Inicio Transporte:</strong> <?= date('d/m/Y H:i', strtotime($g['fecha_inicio_transporte'])) ?></p>
                                    <p><strong>Fin Transporte:</strong> <?= date('d/m/Y H:i', strtotime($g['fecha_fin_transporte'])) ?></p>
                                </div>
                            </div>
                            <?php if ($g['clave_acceso']): ?>
                            <p><strong>Clave de Acceso:</strong><br><code><?= $g['clave_acceso'] ?></code></p>
                            <?php endif; ?>
                            <?php if ($g['numero_autorizacion']): ?>
                            <p><strong>Número de Autorización:</strong><br><code><?= $g['numero_autorizacion'] ?></code></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Transportista -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Transportista</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Razón Social:</strong> <?= htmlspecialchars($g['razon_social_transportista'] ?? '') ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Tipo Identificación:</strong> <?= htmlspecialchars($g['tipo_identificacion_transportista'] ?? '') ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>RUC/Identificación:</strong> <?= htmlspecialchars($g['ruc_transportista'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Destinatario -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Destinatario</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Razón Social:</strong> <?= htmlspecialchars($g['razon_social_destinatario'] ?? '') ?></p>
                                    <p><strong>Identificación:</strong> <?= htmlspecialchars($g['identificacion_destinatario'] ?? '') ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Dirección:</strong> <?= htmlspecialchars($g['dir_destinatario'] ?? '') ?></p>
                                    <p><strong>Cod. Estab. Destino:</strong> <?= htmlspecialchars($g['cod_estab_destino'] ?? '') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Documento Sustento -->
                    <?php if ($g['num_doc_sustento']): ?>
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Documento Sustento</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <p><strong>Documento:</strong> <?= htmlspecialchars($g['num_doc_sustento']) ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Nro. Autorización:</strong> <?= htmlspecialchars($g['num_aut_doc_sustento'] ?? '') ?></p>
                                </div>
                                <div class="col-md-4">
                                    <p><strong>Fecha Emisión:</strong> <?= $g['fecha_emision_doc_sustento'] ? date('d/m/Y', strtotime($g['fecha_emision_doc_sustento'])) : '-' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Productos -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Productos Trasladados</h6></div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <thead class="thead-light">
                                    <tr><th>Código Interno</th><th>Código Adicional</th><th>Descripción</th><th>Cantidad</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($detalles as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['codigo_interno'] ?? $d['codigo_interno']) ?></td>
                                            <td><?= htmlspecialchars($d['codigo_adicional'] ?? '-') ?></td>
                                            <td><?= htmlspecialchars($d['descripcion'] ?: $d['producto_nombre']) ?></td>
                                            <td class="text-right"><?= number_format($d['cantidad'], 4) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <?php if($g['estado_xml'] != 'AUTORIZADO'): ?>
                    <div class="text-center mb-4">
                        <a href="guia_remision_completa.php?id=<?= $g['id'] ?>" class="btn btn-success btn-lg"><i class="fas fa-check"></i> AUTORIZAR</a>
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
