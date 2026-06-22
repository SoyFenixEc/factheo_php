<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener empresas activas
$sql_empresas = "SELECT id, nombre_comercial, ruc FROM empresa WHERE usuario_id = :usuario_id AND activa = 1";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll();

// Obtener proveedores
$sql_proveedores = "SELECT id, nombre, ruc, direccion FROM proveedores WHERE usuario_id = :usuario_id ORDER BY nombre";
$stmt_proveedores = $pdo->prepare($sql_proveedores);
$stmt_proveedores->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_proveedores->execute();
$proveedores = $stmt_proveedores->fetchAll();

// Obtener catálogo de impuestos_retencion (Renta + IVA + ISD)
$sql_imp = "SELECT * FROM impuestos_retencion WHERE activo = 1 ORDER BY codigo_impuesto, codigo_retencion";
$stmt_imp = $pdo->prepare($sql_imp);
$stmt_imp->execute();
$impuestos_retencion = $stmt_imp->fetchAll();

// Obtener tipos de comprobante para docs sustento (facturas, liquidaciones)
$sql_tipos_doc = "SELECT codigo, nombre FROM tipos_comprobante WHERE activo = 1 AND codigo IN ('01','03','04','05','06','41') ORDER BY codigo";
$stmt_tipos_doc = $pdo->prepare($sql_tipos_doc);
$stmt_tipos_doc->execute();
$tipos_doc_sustento = $stmt_tipos_doc->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php'); ?>
    <title>Comprobante de Retención Nuevo</title>
    <style>
        .doc-card { background: #f8f9fc; border-left: 4px solid #4e73df; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .retencion-row { background: #fff3cd; }
        .clave-monospace { font-family: monospace; font-size: 0.9rem; word-break: break-all; }
        .doc-header { background: #e8eaf6; padding: 8px 12px; border-radius: 4px; margin-bottom: 10px; }
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
                        <h1 class="h3 mb-0 text-gray-800">Crear Comprobante de Retención</h1>
                    </div>

                    <form action="comprobante_retencion_graba.php" method="POST" id="retencionForm">
                        <!-- Datos del Sujeto Retenido y Cabecera -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Datos del Comprobante de Retención</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Fecha de Emisión</label>
                                            <input type="date" class="form-control" name="fecha_emision" required value="<?= date('Y-m-d') ?>">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Período Fiscal</label>
                                            <input type="month" class="form-control" name="periodo_fiscal" required value="<?= date('Y-m') ?>">
                                            <small class="text-muted">Formato: MM/AAAA</small>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Empresa</label>
                                            <select class="form-control" id="empresa_id" name="empresa_id" required>
                                                <option value="">Seleccione una empresa activa</option>
                                                <?php foreach ($empresas as $emp): ?>
                                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre_comercial']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Punto de Emisión</label>
                                            <select class="form-control" id="punto_emision_id" name="punto_emision_id" required>
                                                <option value="">Seleccione un punto de emisión</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Sujeto Retenido (Proveedor)</label>
                                            <select class="form-control" id="proveedor_id" name="proveedor_id" required>
                                                <option value="">Seleccione un proveedor...</option>
                                                <?php foreach ($proveedores as $prov): ?>
                                                    <option value="<?= $prov['id'] ?>" 
                                                            data-identificacion="<?= $prov['ruc'] ?>"
                                                            data-nombre="<?= htmlspecialchars($prov['nombre']) ?>"
                                                            data-direccion="<?= htmlspecialchars($prov['direccion'] ?? '') ?>">
                                                        <?= htmlspecialchars($prov['nombre']) ?> - <?= $prov['ruc'] ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Comentarios</label>
                                            <textarea class="form-control" name="comentarios" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                                <div id="proveedor_info" style="display:none;" class="doc-card mt-2">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Razón Social:</strong> <span id="prov_razon_social"></span></p>
                                            <p><strong>Identificación:</strong> <span id="prov_identificacion"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Dirección:</strong> <span id="prov_direccion"></span></p>
                                            <p><strong>Tipo Identificación:</strong> <span id="prov_tipo_iden"></span></p>
                                            <input type="hidden" name="tipo_identificacion_sujeto_retenido" id="tipo_identificacion_sujeto_retenido">
                                            <input type="hidden" name="razon_social_sujeto_retenido" id="razon_social_sujeto_retenido">
                                            <input type="hidden" name="identificacion_sujeto_retenido" id="identificacion_sujeto_retenido">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documentos Sustento con Retenciones -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Documentos Sustento y Retenciones</h6>
                                <button type="button" class="btn btn-info btn-sm" onclick="agregarDocSustento()">+ Agregar Documento</button>
                            </div>
                            <div class="card-body">
                                <div id="docs_sustento_container"></div>
                                <div id="no_docs_msg" class="alert alert-info text-center">
                                    <i class="fas fa-plus-circle fa-2x mb-2"></i><br>
                                    No hay documentos agregados. Haga clic en "+ Agregar Documento" para comenzar.
                                </div>
                            </div>
                        </div>

                        <!-- Totales -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Resumen de Retenciones</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="input-group mb-3">
                                            <span class="border-0 bg-light input-group-text w-50">Total Documentos</span>
                                            <input type="text" class="form-control" id="total_documentos" readonly value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="input-group mb-3">
                                            <span class="border-0 bg-light input-group-text w-50">Total Retenido Renta</span>
                                            <input type="text" class="form-control" id="total_retenido_renta" name="total_retenido_renta" readonly value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="input-group mb-3">
                                            <span class="border-0 bg-light input-group-text w-50">Total Retenido IVA</span>
                                            <input type="text" class="form-control" id="total_retenido_iva" name="total_retenido_iva" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-4 offset-lg-4">
                                        <div class="input-group mb-3">
                                            <span class="border-0 bg-light input-group-text w-50">Total Retenido ISD</span>
                                            <input type="text" class="form-control" id="total_retenido_isd" name="total_retenido_isd" readonly value="0.00">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="input-group mb-3">
                                            <span class="border-0 bg-danger text-white input-group-text w-50"><strong>TOTAL RETENIDO</strong></span>
                                            <input type="text" class="form-control font-weight-bold" id="total_retenido" name="total_retenido" readonly value="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="docs_json" name="docs_json" value="">
                        <button type="submit" class="btn btn-primary btn-lg mb-4">Generar Comprobante de Retención</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>

    <script>
    // Catálogo de impuestos_retencion (pasado desde PHP)
    const impuestosRetencion = <?= json_encode($impuestos_retencion) ?>;
    const tiposDocSustento = <?= json_encode($tipos_doc_sustento) ?>;

    let docIdx = 0;
    let docsSustento = [];

    // --- Manejo de proveedor ---
    $('#proveedor_id').change(function() {
        const opt = $(this).find('option:selected');
        if (!opt.val()) { $('#proveedor_info').hide(); return; }
        const iden = opt.data('identificacion') || '';
        const nombre = opt.data('nombre') || '';
        const dir = opt.data('direccion') || '';
        $('#prov_razon_social').text(nombre);
        $('#prov_identificacion').text(iden);
        $('#prov_direccion').text(dir || 'No registrada');

        // Determinar tipo identificación SRI
        let tipoId = '05'; // Cédula por defecto
        if (iden.length === 13) tipoId = '04'; // RUC
        else if (iden.length === 10) tipoId = '05'; // Cédula
        else if (iden.length > 0) tipoId = '06'; // Pasaporte
        $('#prov_tipo_iden').text(tipoId + ' (' + (tipoId=='04'?'RUC':tipoId=='05'?'Cédula':'Pasaporte') + ')');
        $('#tipo_identificacion_sujeto_retenido').val(tipoId);
        $('#razon_social_sujeto_retenido').val(nombre);
        $('#identificacion_sujeto_retenido').val(iden);
        $('#proveedor_info').show();
    });

    // --- Manejo de punto de emisión ---
    $('#empresa_id').change(function() {
        const eid = $(this).val();
        if (!eid) { $('#punto_emision_id').html('<option value="">Seleccione...</option>'); return; }
        $.post('get_puntos_emision.php', { empresa_id: eid }, function(d) {
            if (d.error) { $('#punto_emision_id').html('<option value="">'+d.error+'</option>'); return; }
            $('#punto_emision_id').html('<option value="">Seleccione...</option>');
            d.forEach(function(p) {
                $('#punto_emision_id').append('<option value="'+p.id+'" data-secuencial="'+(p.secuencial_comprobante_retencion||0)+'">'+p.establecimiento+'-'+p.punto_emision+' (Sec: '+(p.secuencial_comprobante_retencion||0)+')</option>');
            });
        },'json');
    });

    // --- Agregar Documento Sustento ---
    function agregarDocSustento(datos) {
        const idx = docIdx++;
        const id = 'doc_' + idx;
        const d = datos || {};

        const html = `
        <div class="card shadow-sm mb-3" id="${id}">
            <div class="card-header py-2 d-flex justify-content-between align-items-center bg-light">
                <h6 class="mb-0 font-weight-bold text-primary">Documento Sustento #${idx+1}</h6>
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarDoc('${id}')"><i class="fas fa-trash"></i></button>
            </div>
            <div class="card-body">
                <div class="doc-header"><strong>Datos del Documento Sustento</strong></div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Código Sustento</label>
                            <select class="form-control cod-sustento" required>
                                <option value="">Seleccione...</option>
                                <option value="01" ${d.cod_sustento=='01'?'selected':''}>01 - Compras locales</option>
                                <option value="02" ${d.cod_sustento=='02'?'selected':''}>02 - Importaciones</option>
                                <option value="03" ${d.cod_sustento=='03'?'selected':''}>03 - Reembolsos</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Tipo Doc. Sustento</label>
                            <select class="form-control cod-doc-nr" required>
                                <option value="">Seleccione...</option>
                                ${tiposDocSustento.map(function(t) {
                                    return '<option value="'+t.codigo+'" '+(d.cod_doc_nr==t.codigo?'selected':'')+'>'+t.codigo+' - '+t.nombre+'</option>';
                                }).join('')}
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Nro. Documento</label>
                            <input type="text" class="form-control num-documento" placeholder="001-001-000000001" value="${d.num_documento||''}" required>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Fecha Emisión Doc.</label>
                            <input type="date" class="form-control fecha-emision-doc" value="${d.fecha_emision_doc_sustento||''}" required>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Nro. Autorización Doc.</label>
                            <input type="text" class="form-control num-aut-documento" placeholder="1234567890" value="${d.num_aut_documento||''}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Fecha Aut. Doc.</label>
                            <input type="date" class="form-control fecha-aut-doc" value="${d.fecha_autorizacion_doc_sustento||''}">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Pago Local/Ext.</label>
                            <select class="form-control pago-loc-ext">
                                <option value="01" ${d.pago_loc_ext=='01'?'selected':''}>01 - Local</option>
                                <option value="02" ${d.pago_loc_ext=='02'?'selected':''}>02 - Exterior</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Monto Total</label>
                            <input type="number" step="0.01" class="form-control monto-total" value="${d.monto_total||''}" onchange="recalcularDoc('${id}')">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Total Sin Impuestos</label>
                            <input type="number" step="0.01" class="form-control total-sin-impuestos" value="${d.total_sin_impuestos||''}" onchange="recalcularDoc('${id}')">
                        </div>
                    </div>
                </div>

                <!-- Impuestos del Documento Sustento -->
                <div class="doc-header"><strong>Impuestos del Documento Sustento</strong></div>
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Impuesto Doc. Sustento</label>
                            <select class="form-control cod-impuesto-doc">
                                <option value="2" ${d.cod_impuesto_doc_sustento=='2'||!d.cod_impuesto_doc_sustento?'selected':''}>2 - IVA</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Código Porcentaje (IVA)</label>
                            <select class="form-control codigo-porcentaje" required>
                                <option value="">Seleccione...</option>
                                <option value="0" ${d.codigo_porcentaje=='0'?'selected':''}>0 - 0%</option>
                                <option value="2" ${d.codigo_porcentaje=='2'?'selected':''}>2 - 12%</option>
                                <option value="3" ${d.codigo_porcentaje=='3'?'selected':''}>3 - 14%</option>
                                <option value="4" ${d.codigo_porcentaje=='4'?'selected':''}>4 - No objeto de impuesto</option>
                                <option value="5" ${d.codigo_porcentaje=='5'?'selected':''}>5 - Exento de IVA</option>
                                <option value="6" ${d.codigo_porcentaje=='6'?'selected':''}>6 - Gravado tarifa 0%</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Base Imponible</label>
                            <input type="number" step="0.01" class="form-control base-imponible" value="${d.base_imponible||''}" onchange="recalcularDoc('${id}')">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Tarifa (%)</label>
                            <input type="number" step="0.01" class="form-control tarifa" value="${d.tarifa||'0'}" onchange="recalcularDoc('${id}')">
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-group">
                            <label>Valor Impuesto</label>
                            <input type="number" step="0.01" class="form-control valor-impuesto" value="${d.valor_impuesto||''}" readonly>
                        </div>
                    </div>
                </div>

                <!-- Retenciones Aplicadas -->
                <div class="doc-header d-flex justify-content-between align-items-center">
                    <strong>Retenciones Aplicadas</strong>
                    <button type="button" class="btn btn-warning btn-sm" onclick="agregarRetencion('${id}')">+ Agregar Retención</button>
                </div>
                <div class="retenciones-container">
                    <table class="table table-bordered table-sm retenciones-table">
                        <thead class="thead-warning">
                            <tr>
                                <th>Impuesto</th>
                                <th>Código Retención</th>
                                <th>% Retener</th>
                                <th>Base Imponible</th>
                                <th>Valor Retenido</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>`;

        if ($('#no_docs_msg').is(':visible')) $('#no_docs_msg').hide();
        $('#docs_sustento_container').append(html);

        // Si hay datos de retenciones, agregarlas
        if (d.retenciones && d.retenciones.length > 0) {
            d.retenciones.forEach(function(r) {
                agregarRetencion(id, r);
            });
        }
    }

    // --- Agregar Retención ---
    function agregarRetencion(docId, datos) {
        const d = datos || {};
        const tbody = $('#' + docId + ' .retenciones-table tbody');
        const rowIdx = tbody.find('tr').length;

        // Filtrar impuestos_retencion por tipo de impuesto
        const optsRenta = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '1'; });
        const optsIva = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '2'; });
        const optsIsd = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '6'; });

        const html = `
        <tr class="retencion-row">
            <td>
                <select class="form-control form-control-sm codigo-impuesto-ret" onchange="cargarCodigosRetencion(this, '${docId}')">
                    <option value="1" ${d.codigo_impuesto_retencion=='1'?'selected':''}>1 - Renta</option>
                    <option value="2" ${d.codigo_impuesto_retencion=='2'?'selected':''}>2 - IVA</option>
                    <option value="6" ${d.codigo_impuesto_retencion=='6'?'selected':''}>6 - ISD</option>
                </select>
            </td>
            <td>
                <select class="form-control form-control-sm codigo-retencion-select" required onchange="actualizarValorRetenido(this, '${docId}')">
                    <option value="">Seleccione...</option>
                </select>
            </td>
            <td>
                <input type="text" class="form-control form-control-sm porcentaje-retener text-center" readonly value="${d.porcentaje_retener||'0'}">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control form-control-sm base-imponible-ret" value="${d.base_imponible_retencion||''}" onchange="actualizarValorRetenido(this, '${docId}')">
            </td>
            <td>
                <input type="number" step="0.01" class="form-control form-control-sm valor-retenido text-right font-weight-bold" value="${d.valor_retenido||''}" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm" onclick="eliminarRetencion(this)"><i class="fas fa-times"></i></button>
            </td>
        </tr>`;

        tbody.append(html);

        // Cargar códigos de retención según impuesto seleccionado
        const selectImp = tbody.find('tr:last .codigo-impuesto-ret');
        const selectCod = tbody.find('tr:last .codigo-retencion-select');

        cargarCodigosRetencion(selectImp[0], docId);

        // Si hay datos, seleccionar el código de retención
        if (d.codigo_retencion) {
            selectCod.val(d.codigo_retencion);
            actualizarValorRetenido(selectCod[0], docId);
        }

        recalcularDoc(docId);
    }

    // --- Cargar códigos de retención según impuesto ---
    function cargarCodigosRetencion(selectEl, docId) {
        const tr = $(selectEl).closest('tr');
        const impuesto = $(selectEl).val();
        const selectCod = tr.find('.codigo-retencion-select');
        const porcentajeInput = tr.find('.porcentaje-retener');
        const baseInput = tr.find('.base-imponible-ret');

        selectCod.html('<option value="">Seleccione...</option>');

        let filtrados;
        if (impuesto == '1') {
            filtrados = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '1'; });
        } else if (impuesto == '2') {
            filtrados = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '2'; });
        } else {
            filtrados = impuestosRetencion.filter(function(i) { return i.codigo_impuesto == '6'; });
        }

        filtrados.forEach(function(ir) {
            selectCod.append('<option value="'+ir.id+'" data-porcentaje="'+ir.porcentaje+'" data-base="'+ir.base_calculo+'">'+ir.codigo_retencion+' - '+ir.nombre+'</option>');
        });

        // Sugerir base por defecto
        if (impuesto == '2') {
            // IVA: base = valor_impuesto del doc sustento
            const valImp = parseFloat($('#'+docId+' .valor-impuesto').val()) || 0;
            baseInput.val(valImp.toFixed(2));
        } else {
            // Renta/ISD: base = base imponible del doc sustento
            const base = parseFloat($('#'+docId+' .base-imponible').val()) || 0;
            baseInput.val(base.toFixed(2));
        }

        porcentajeInput.val('0');
    }

    // --- Actualizar valor retenido ---
    function actualizarValorRetenido(el, docId) {
        const tr = $(el).closest('tr');
        const selectCod = tr.find('.codigo-retencion-select');
        const opt = selectCod.find('option:selected');
        const porcentaje = parseFloat(opt.data('porcentaje')) || 0;
        const baseCalculo = opt.data('base') || 'base_imponible';
        const baseImpInput = tr.find('.base-imponible-ret');
        const baseImp = parseFloat(baseImpInput.val()) || 0;
        const valorRet = tr.find('.valor-retenido');

        tr.find('.porcentaje-retener').val(porcentaje.toFixed(2));

        if (baseCalculo == 'valor_impuesto') {
            // Para IVA: el valor retenido es % del valor del impuesto del doc
            const valImp = parseFloat($('#'+docId+' .valor-impuesto').val()) || 0;
            const retenido = valImp * (porcentaje / 100);
            baseImpInput.val(valImp.toFixed(2));
            valorRet.val(retenido.toFixed(2));
        } else {
            // Para Renta/ISD: % de la base imponible
            const retenido = baseImp * (porcentaje / 100);
            valorRet.val(retenido.toFixed(2));
        }

        recalcularDoc(docId);
    }

    // --- Recalcular totales por documento y global ---
    function recalcularDoc(docId) {
        const cont = $('#' + docId);
        const base = parseFloat(cont.find('.base-imponible').val()) || 0;
        const tarifa = parseFloat(cont.find('.tarifa').val()) || 0;
        const valImp = base * (tarifa / 100);
        cont.find('.valor-impuesto').val(valImp.toFixed(2));

        recalcularGlobal();
    }

    // --- Eliminar retención ---
    function eliminarRetencion(btn) {
        $(btn).closest('tr').remove();
        const docId = $(btn).closest('.card').attr('id');
        recalcularGlobal();
    }

    // --- Eliminar documento ---
    function eliminarDoc(id) {
        $('#' + id).remove();
        recalcularGlobal();
        if ($('#docs_sustento_container').children().length === 0) {
            $('#no_docs_msg').show();
        }
    }

    // --- Calcular totales globales ---
    function recalcularGlobal() {
        let totalDocs = 0;
        let totalRenta = 0;
        let totalIva = 0;
        let totalIsd = 0;

        $('.card[id^="doc_"]').each(function() {
            const monto = parseFloat($(this).find('.monto-total').val()) || 0;
            totalDocs += monto;

            $(this).find('.retenciones-table tbody tr').each(function() {
                const imp = $(this).find('.codigo-impuesto-ret').val();
                const val = parseFloat($(this).find('.valor-retenido').val()) || 0;
                if (imp == '1') totalRenta += val;
                else if (imp == '2') totalIva += val;
                else if (imp == '6') totalIsd += val;
            });
        });

        const totalGeneral = totalRenta + totalIva + totalIsd;
        $('#total_documentos').val(totalDocs.toFixed(2));
        $('#total_retenido_renta').val(totalRenta.toFixed(2));
        $('#total_retenido_iva').val(totalIva.toFixed(2));
        $('#total_retenido_isd').val(totalIsd.toFixed(2));
        $('#total_retenido').val(totalGeneral.toFixed(2));
    }

    // --- Submit: serializar todo a JSON ---
    $('#retencionForm').on('submit', function(e) {
        // Verificar que hay documentos
        if ($('.card[id^="doc_"]').length === 0) {
            e.preventDefault();
            alert('Debe agregar al menos un documento sustento con sus retenciones.');
            return;
        }

        // Verificar proveedor
        if (!$('#proveedor_id').val()) {
            e.preventDefault();
            alert('Debe seleccionar un proveedor (sujeto retenido).');
            return;
        }

        // Validar período fiscal
        const pf = $('input[name="periodo_fiscal"]').val();
        if (!pf) {
            e.preventDefault();
            alert('Debe indicar el período fiscal.');
            return;
        }

        // Serializar todos los documentos con sus retenciones
        const docs = [];
        $('.card[id^="doc_"]').each(function() {
            const doc = {
                cod_sustento: $(this).find('.cod-sustento').val(),
                cod_doc_nr: $(this).find('.cod-doc-nr').val(),
                num_documento: $(this).find('.num-documento').val(),
                fecha_emision_doc_sustento: $(this).find('.fecha-emision-doc').val(),
                num_aut_documento: $(this).find('.num-aut-documento').val(),
                fecha_autorizacion_doc_sustento: $(this).find('.fecha-aut-doc').val(),
                monto_total: $(this).find('.monto-total').val(),
                pago_loc_ext: $(this).find('.pago-loc-ext').val(),
                total_sin_impuestos: $(this).find('.total-sin-impuestos').val(),
                cod_impuesto_doc_sustento: $(this).find('.cod-impuesto-doc').val(),
                codigo_porcentaje: $(this).find('.codigo-porcentaje').val(),
                base_imponible: $(this).find('.base-imponible').val(),
                tarifa: $(this).find('.tarifa').val(),
                valor_impuesto: $(this).find('.valor-impuesto').val(),
                retenciones: []
            };

            $(this).find('.retenciones-table tbody tr').each(function() {
                doc.retenciones.push({
                    codigo_impuesto_retencion: $(this).find('.codigo-impuesto-ret').val(),
                    codigo_retencion: $(this).find('.codigo-retencion-select').val(),
                    porcentaje_retener: $(this).find('.porcentaje-retener').val(),
                    base_imponible_retencion: $(this).find('.base-imponible-ret').val(),
                    valor_retenido: $(this).find('.valor-retenido').val()
                });
            });

            docs.push(doc);
        });

        $('#docs_json').val(JSON.stringify(docs));

        // Validar retenciones
        let valid = true;
        docs.forEach(function(d, i) {
            if (!d.cod_sustento || !d.cod_doc_nr || !d.num_documento) { valid = false; return; }
            d.retenciones.forEach(function(r, j) {
                if (!r.codigo_retencion) { valid = false; return; }
            });
        });

        if (!valid) {
            e.preventDefault();
            alert('Complete todos los campos requeridos en documentos y retenciones.');
            return;
        }
    });
    </script>
</body>
</html>
