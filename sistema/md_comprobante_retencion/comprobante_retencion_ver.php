<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener comprobante de retención
$stmt = $pdo->prepare("SELECT cr.*, 
                               e.nombre_comercial AS emp_nc, e.ruc, e.razon_social AS emp_rs,
                               pv.nombre AS prov_nombre, pv.ruc AS prov_ruc
                        FROM comprobantes_retencion cr
                        JOIN empresa e ON cr.empresa_id = e.id
                        JOIN proveedores pv ON cr.proveedor_id = pv.id
                        WHERE cr.id = ?");
$stmt->execute([$id]);
$cr = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cr) die("<div class='alert alert-danger'>Comprobante de Retención no encontrado.</div>");

// Obtener detalles (documentos sustento y retenciones)
$stmt_d = $pdo->prepare("SELECT * FROM detalle_retencion WHERE comprobante_retencion_id = ? ORDER BY id");
$stmt_d->execute([$id]);
$detalles = $stmt_d->fetchAll();

$num = str_pad($cr['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($cr['secuencial'],9,'0',STR_PAD_LEFT);

// Identificar tipo de identificación
$tipoIdenMap = ['04'=>'RUC','05'=>'Cédula','06'=>'Pasaporte'];
$tipoIden = $tipoIdenMap[$cr['tipo_identificacion_sujeto_retenido']] ?? $cr['tipo_identificacion_sujeto_retenido'];

// Agrupar detalles por doc_sustento
$docs_agrupados = [];
foreach ($detalles as $det) {
    $key = $det['num_documento'];
    if (!isset($docs_agrupados[$key])) {
        $docs_agrupados[$key] = [
            'datos' => [
                'cod_sustento' => $det['cod_sustento'],
                'cod_doc_nr' => $det['cod_doc_nr'],
                'num_documento' => $det['num_documento'],
                'fecha_emision_doc_sustento' => $det['fecha_emision_doc_sustento'],
                'num_aut_documento' => $det['num_aut_documento'],
                'fecha_autorizacion_doc_sustento' => $det['fecha_autorizacion_doc_sustento'],
                'monto_total' => $det['monto_total'],
                'pago_loc_ext' => $det['pago_loc_ext'],
                'total_sin_impuestos' => $det['total_sin_impuestos'],
            ],
            'impuestos_doc' => [],
            'retenciones' => []
        ];
    }
    // Impuesto del documento
    $impKey = $det['cod_impuesto_doc_sustento'] . '_' . $det['codigo_porcentaje'];
    if (!isset($docs_agrupados[$key]['impuestos_doc'][$impKey])) {
        $docs_agrupados[$key]['impuestos_doc'][$impKey] = [
            'cod_impuesto_doc_sustento' => $det['cod_impuesto_doc_sustento'],
            'codigo_porcentaje' => $det['codigo_porcentaje'],
            'base_imponible' => $det['base_imponible'],
            'tarifa' => $det['tarifa'],
            'valor_impuesto' => $det['valor_impuesto']
        ];
    }
    // Retención
    if ($det['codigo_retencion'] > 0) {
        $docs_agrupados[$key]['retenciones'][] = $det;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head><?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?><title>Comprobante de Retención #<?= $num ?></title></head>
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
                        <h1 class="h3 mb-0 text-gray-800">Comprobante de Retención #<?= $num ?></h1>
                        <div>
                            <a href="comprobante_retencion_lista.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                            <a href="comprobante_retencion_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger" target="_blank"><i class="fas fa-file-pdf"></i> PDF</a>
                        </div>
                    </div>

                    <?php if ($cr['estado_xml'] == 'AUTORIZADO'): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> AUTORIZADA - Nro: <?= $cr["numero_autorizacion"] ?? "Pendiente" ?>
                            <a href="comprobante_retencion_pdf.php?id=<?= $id ?>" class="btn btn-sm btn-danger float-right" target="_blank"><i class="fas fa-file-pdf"></i> Descargar PDF</a>
                        </div>
                    <?php endif; ?>

                    <!-- Información Tributaria -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Información del Comprobante</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Empresa:</strong> <?= htmlspecialchars($cr['emp_nc']) ?></p>
                                    <p><strong>RUC:</strong> <?= $cr['ruc'] ?></p>
                                    <p><strong>Razón Social:</strong> <?= htmlspecialchars($cr['emp_rs']) ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Fecha de Emisión:</strong> <?= date('d/m/Y H:i', strtotime($cr['fecha_emision'])) ?></p>
                                    <p><strong>Período Fiscal:</strong> <?= $cr['periodo_fiscal'] ?></p>
                                    <p><strong>Estado:</strong> <span class="badge badge-<?= $cr['estado_xml']=='AUTORIZADO'?'success':'info' ?>"><?= $cr['estado_xml'] ?></span></p>
                                </div>
                            </div>
                            <?php if ($cr['clave_acceso']): ?>
                            <p><strong>Clave de Acceso:</strong><br><code><?= $cr['clave_acceso'] ?></code></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Sujeto Retenido -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Sujeto Retenido</h6></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Razón Social:</strong> <?= htmlspecialchars($cr['razon_social_sujeto_retenido']) ?></p>
                                    <p><strong>Identificación:</strong> <?= $cr['identificacion_sujeto_retenido'] ?> (<?= $tipoIden ?>)</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Proveedor:</strong> <?= htmlspecialchars($cr['prov_nombre']) ?></p>
                                    <p><strong>Dirección Establecimiento:</strong> <?= htmlspecialchars($cr['dir_establecimiento'] ?? '') ?></p>
                                </div>
                            </div>
                            <?php if (!empty($cr['contribuyente_especial'])): ?>
                            <p><strong>Contribuyente Especial:</strong> <?= $cr['contribuyente_especial'] ?></p>
                            <?php endif; ?>
                            <p><strong>Obligado a Contabilidad:</strong> <?= $cr['obligado_contabilidad'] ?></p>
                            <?php if ($cr['comentarios']): ?>
                            <p><strong>Comentarios:</strong> <?= htmlspecialchars($cr['comentarios']) ?></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Documentos Sustento -->
                    <div class="card shadow mb-4">
                        <div class="card-header"><h6 class="font-weight-bold text-primary">Documentos Sustento y Retenciones</h6></div>
                        <div class="card-body">
                            <?php if (count($docs_agrupados) > 0): ?>
                                <?php $totalRet = 0; $totalRenta=0; $totalIVA=0; $totalISD=0; ?>
                                <?php foreach ($docs_agrupados as $key => $doc): $dd = $doc['datos']; ?>
                                    <div class="card bg-light mb-3">
                                        <div class="card-header py-2">
                                            <strong>Documento: <?= $dd['num_documento'] ?></strong>
                                            <span class="float-right"><?= date('d/m/Y', strtotime($dd['fecha_emision_doc_sustento'])) ?></span>
                                        </div>
                                        <div class="card-body py-2">
                                            <div class="row">
                                                <div class="col-md-3"><small><strong>Código Sustento:</strong> <?= $dd['cod_sustento'] ?></small></div>
                                                <div class="col-md-3"><small><strong>Tipo Doc.:</strong> <?= $dd['cod_doc_nr'] ?></small></div>
                                                <div class="col-md-3"><small><strong>Monto Total:</strong> $<?= number_format($dd['monto_total'],2) ?></small></div>
                                                <div class="col-md-3"><small><strong>Pago:</strong> <?= $dd['pago_loc_ext']=='01'?'Local':'Exterior' ?></small></div>
                                            </div>
                                            <div class="row mt-1">
                                                <div class="col-md-6"><small><strong>Nro. Autorización:</strong> <?= $dd['num_aut_documento'] ?></small></div>
                                                <div class="col-md-3"><small><strong>Total Sin Imp.:</strong> $<?= number_format($dd['total_sin_impuestos'],2) ?></small></div>
                                            </div>

                                            <!-- Impuestos del Documento -->
                                            <?php if (!empty($doc['impuestos_doc'])): ?>
                                            <table class="table table-sm table-bordered mt-2 mb-2">
                                                <thead class="thead-light">
                                                    <tr><th>Impuesto</th><th>Código %</th><th>Base Imponible</th><th>Tarifa</th><th>Valor</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($doc['impuestos_doc'] as $imp): ?>
                                                    <tr>
                                                        <td><?= $imp['cod_impuesto_doc_sustento']=='2'?'IVA':$imp['cod_impuesto_doc_sustento'] ?></td>
                                                        <td><?= $imp['codigo_porcentaje'] ?></td>
                                                        <td class="text-right">$<?= number_format($imp['base_imponible'],2) ?></td>
                                                        <td class="text-right"><?= number_format($imp['tarifa'],2) ?>%</td>
                                                        <td class="text-right">$<?= number_format($imp['valor_impuesto'],2) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php endif; ?>

                                            <!-- Retenciones -->
                                            <?php if (!empty($doc['retenciones'])): ?>
                                            <table class="table table-sm table-bordered">
                                                <thead class="thead-warning">
                                                    <tr><th>Impuesto</th><th>Código Ret.</th><th>% Retener</th><th>Base Imponible</th><th>Valor Retenido</th></tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($doc['retenciones'] as $ret):
                                                        $st = $pdo->prepare("SELECT nombre, codigo_retencion FROM impuestos_retencion WHERE id = ?");
                                                        $st->execute([$ret['codigo_retencion']]);
                                                        $ir = $st->fetch();
                                                        $impName = $ret['codigo_impuesto_retencion']=='1'?'Renta':($ret['codigo_impuesto_retencion']=='2'?'IVA':'ISD');
                                                        $codRet = $ir['codigo_retencion'] ?? $ret['codigo_retencion'];
                                                        if ($ret['codigo_impuesto_retencion']=='1') $totalRenta += $ret['valor_retenido'];
                                                        elseif ($ret['codigo_impuesto_retencion']=='2') $totalIVA += $ret['valor_retenido'];
                                                        elseif ($ret['codigo_impuesto_retencion']=='6') $totalISD += $ret['valor_retenido'];
                                                        $totalRet += $ret['valor_retenido'];
                                                    ?>
                                                    <tr>
                                                        <td><?= $impName ?></td>
                                                        <td><?= $codRet ?></td>
                                                        <td class="text-right"><?= number_format($ret['porcentaje_retener'],2) ?>%</td>
                                                        <td class="text-right">$<?= number_format($ret['base_imponible_retencion'],2) ?></td>
                                                        <td class="text-right font-weight-bold">$<?= number_format($ret['valor_retenido'],2) ?></td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Totales por impuesto -->
                                <div class="card shadow-sm mt-3">
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-3">
                                                <div class="alert alert-secondary text-center p-2 mb-0">
                                                    <strong>Ret. Renta</strong><br>
                                                    $<?= number_format($totalRenta,2) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="alert alert-info text-center p-2 mb-0">
                                                    <strong>Ret. IVA</strong><br>
                                                    $<?= number_format($totalIVA,2) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="alert alert-warning text-center p-2 mb-0">
                                                    <strong>Ret. ISD</strong><br>
                                                    $<?= number_format($totalISD,2) ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="alert alert-success text-center p-2 mb-0">
                                                    <strong>TOTAL RETENIDO</strong><br>
                                                    <strong>$<?= number_format($totalRet,2) ?></strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <p class="text-center">No hay documentos registrados.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if($cr['estado_xml'] != 'AUTORIZADO'): ?>
                    <div class="text-center mb-4">
                        <a href="comprobante_retencion_completa.php?id=<?= $cr['id'] ?>" class="btn btn-success btn-lg"><i class="fas fa-check"></i> AUTORIZAR</a>
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
