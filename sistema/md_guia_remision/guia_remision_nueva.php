<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener clientes
$sql_clientes = "SELECT id, razon_social, identificacion, direccion, email, telefono 
                 FROM clientes WHERE usuario_id = :usuario_id";
$stmt_clientes = $pdo->prepare($sql_clientes);
$stmt_clientes->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll();

// Obtener empresas activas
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :usuario_id AND activa = 1";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll();

// Obtener productos
$sql_productos = "SELECT id, nombre, codigo, precio_unitario FROM productos WHERE usuario_id = :usuario_id";
$stmt_productos = $pdo->prepare($sql_productos);
$stmt_productos->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_productos->execute();
$productos = $stmt_productos->fetchAll();

// Obtener facturas autorizadas del usuario
$sql_facturas = "SELECT f.id, f.clave_acceso, f.establecimiento, f.punto_emision, f.secuencial,
                        c.razon_social, c.id AS cliente_id, f.total, f.fecha_emision, e.nombre_comercial
                 FROM facturas f
                 JOIN clientes c ON f.cliente_id = c.id
                 JOIN empresa e ON f.empresa_id = e.id
                 WHERE e.usuario_id = :usuario_id AND f.estado_xml = 'AUTORIZADO'
                 ORDER BY f.id DESC LIMIT 50";
$stmt_facturas = $pdo->prepare($sql_facturas);
$stmt_facturas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_facturas->execute();
$facturas_autorizadas = $stmt_facturas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php'); ?>
    <title>Nueva Guía de Remisión</title>
    <style>
        .doc-sustento-info { background: #f8f9fc; border-left: 4px solid #4e73df; padding: 15px; margin-bottom: 20px; border-radius: 6px; }
        .clave-monospace { font-family: monospace; font-size: 0.9rem; word-break: break-all; }
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
                        <h1 class="h3 mb-0 text-gray-800">Crear Guía de Remisión</h1>
                    </div>

                    <form action="guia_remision_graba.php" method="POST" id="guiaForm">
                        <!-- Selección de empresa y punto de emisión -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Datos de Emisión</h6></div>
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
                                            <label>Empresa</label>
                                            <select class="form-control" id="empresa_id" name="empresa_id" required>
                                                <option value="">Seleccione una empresa activa</option>
                                                <?php foreach ($empresas as $emp): ?>
                                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre_comercial']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Punto de Emisión</label>
                                            <select class="form-control" id="punto_emision_id" name="punto_emision_id" required>
                                                <option value="">Seleccione un punto de emisión</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Documento sustento (factura asociada) -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Documento Sustento</h6>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <label>Factura Asociada (opcional)</label>
                                    <select class="form-control" id="factura_sustento_id" name="factura_sustento_id">
                                        <option value="">Seleccione factura autorizada (opcional)...</option>
                                        <?php foreach ($facturas_autorizadas as $f): 
                                            $num = str_pad($f['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($f['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($f['secuencial'],9,'0',STR_PAD_LEFT);
                                        ?>
                                            <option value="<?= $f['id'] ?>"
                                                    data-empresa="<?= htmlspecialchars($f['nombre_comercial']) ?>"
                                                    data-cliente="<?= htmlspecialchars($f['razon_social']) ?>"
                                                    data-cliente-id="<?= $f['cliente_id'] ?>"
                                                    data-total="<?= $f['total'] ?>"
                                                    data-fecha="<?= $f['fecha_emision'] ?>"
                                                    data-clave="<?= $f['clave_acceso'] ?>"
                                                    data-estab="<?= $f['establecimiento'] ?>"
                                                    data-pto="<?= $f['punto_emision'] ?>"
                                                    data-sec="<?= $f['secuencial'] ?>">
                                                #<?= $num ?> - <?= htmlspecialchars($f['razon_social']) ?> - $<?= number_format($f['total'],2) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div id="doc_sustento_info" style="display:none;background:#f8f9fc;border-left:4px solid #4e73df;padding:15px;border-radius:6px;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <p><strong>Factura:</strong> <span id="factura_numero"></span></p>
                                            <p><strong>Cliente:</strong> <span id="factura_cliente"></span></p>
                                            <p><strong>Clave Acceso:</strong> <span id="factura_clave" class="clave-monospace"></span></p>
                                        </div>
                                        <div class="col-md-6">
                                            <p><strong>Fecha:</strong> <span id="factura_fecha"></span></p>
                                            <p><strong>Empresa:</strong> <span id="factura_empresa"></span></p>
                                        </div>
                                    </div>
                                    <input type="hidden" id="num_doc_sustento" name="num_doc_sustento" value="">
                                    <input type="hidden" id="num_aut_doc_sustento" name="num_aut_doc_sustento" value="">
                                    <input type="hidden" id="fecha_emision_doc_sustento" name="fecha_emision_doc_sustento" value="">
                                </div>
                            </div>
                        </div>

                        <!-- Información de la Guía de Remisión -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Información del Traslado</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Dirección de Partida *</label>
                                            <input type="text" class="form-control" name="dir_partida" required placeholder="Dirección desde donde se origina el traslado">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Ruta</label>
                                            <input type="text" class="form-control" name="ruta" placeholder="Ruta del traslado (opcional)">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Fecha Inicio de Transporte *</label>
                                            <input type="datetime-local" class="form-control" name="fecha_inicio_transporte" required>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Fecha Fin de Transporte *</label>
                                            <input type="datetime-local" class="form-control" name="fecha_fin_transporte" required>
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Placa del Vehículo *</label>
                                            <input type="text" class="form-control" name="placa" required placeholder="Ej: ABC-1234" maxlength="20">
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="form-group">
                                            <label>Comentarios</label>
                                            <textarea class="form-control" name="comentarios" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos del Transportista -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Transportista</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Tipo Identificación *</label>
                                            <select class="form-control" name="tipo_identificacion_transportista" required>
                                                <option value="">Seleccione...</option>
                                                <option value="04">RUC</option>
                                                <option value="05">Cédula</option>
                                                <option value="06">Pasaporte</option>
                                                <option value="07">Consumidor Final</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>RUC / Identificación *</label>
                                            <input type="text" class="form-control" name="ruc_transportista" required maxlength="20" placeholder="Número de identificación">
                                        </div>
                                    </div>
                                    <div class="col-lg-4">
                                        <div class="form-group">
                                            <label>Razón Social Transportista *</label>
                                            <input type="text" class="form-control" name="razon_social_transportista" required maxlength="300" placeholder="Nombre o razón social">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Datos del Destinatario -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Destinatario</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Identificación Destinatario *</label>
                                            <input type="text" class="form-control" id="identificacion_destinatario" name="identificacion_destinatario" required maxlength="20" placeholder="RUC / Cédula">
                                        </div>
                                        <div class="form-group">
                                            <label>Razón Social Destinatario *</label>
                                            <input type="text" class="form-control" id="razon_social_destinatario" name="razon_social_destinatario" required maxlength="300" placeholder="Nombre o razón social">
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Dirección Destinatario *</label>
                                            <input type="text" class="form-control" name="dir_destinatario" required placeholder="Dirección del destinatario">
                                        </div>
                                        <div class="form-group">
                                            <label>Código Establecimiento Destino</label>
                                            <input type="text" class="form-control" name="cod_estab_destino" maxlength="5" placeholder="Ej: 001 (opcional)">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Motivo de Traslado *</label>
                                            <select class="form-control" name="motivo_traslado" required>
                                                <option value="">Seleccione...</option>
                                                <option value="VEN">Venta</option>
                                                <option value="COM">Compra</option>
                                                <option value="TRA">Traslado entre establecimientos</option>
                                                <option value="DON">Donación</option>
                                                <option value="IMP">Importación</option>
                                                <option value="EXP">Exportación</option>
                                                <option value="OTR">Otros</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Documento Aduanero Único</label>
                                            <input type="text" class="form-control" name="doc_aduano_unico" maxlength="50" placeholder="Para importaciones/exportaciones (opcional)">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Productos / Detalles -->
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Productos Trasladados</h6>
                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalAgregarProducto">+ Agregar Producto</button>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered" id="productos_seleccionados">
                                    <thead class="bg-primary text-light">
                                        <tr><th>Código Interno</th><th>Código Adicional</th><th>Descripción</th><th>Cantidad</th><th>Acción</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <input type="hidden" id="productos_ids" name="productos_ids">
                        <input type="hidden" id="productos_codigos" name="productos_codigos">
                        <input type="hidden" id="productos_adicionales" name="productos_adicionales">
                        <input type="hidden" id="productos_descripciones" name="productos_descripciones">
                        <input type="hidden" id="productos_cantidades" name="productos_cantidades">

                        <button type="submit" class="btn btn-primary btn-lg mb-4">Generar Guía de Remisión</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <!-- Modal Agregar Producto -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Seleccionar Producto</h5><button type="button" class="close" data-dismiss="modal"><span>&times;</span></button></div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Buscar Producto</label>
                        <input type="text" id="search_producto" class="form-control" placeholder="Buscar...">
                        <div id="productos_list" style="max-height:200px;overflow-y:auto;margin-top:10px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Producto</label>
                        <select id="producto_id" class="form-control">
                            <option value="">Seleccione</option>
                            <?php foreach ($productos as $p): ?>
                                <option value="<?= $p['id'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>" data-codigo="<?= htmlspecialchars($p['codigo']) ?>"><?= htmlspecialchars($p['nombre']) ?> - <?= htmlspecialchars($p['codigo']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Código Adicional (opcional)</label>
                        <input type="text" id="codigo_adicional" class="form-control" placeholder="Ej: código auxiliar">
                    </div>
                    <div class="form-group"><label>Cantidad</label><input type="number" id="cantidad" class="form-control" value="1" min="0.000001" step="0.000001"></div>
                    <button type="button" id="agregar_producto" class="btn btn-primary">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>

    <script>
        let productos = [];

        // Auto-carga destinatario al seleccionar factura
        $('#factura_sustento_id').change(function() {
            const o = $(this).find('option:selected');
            if (!o.val()) {
                $('#doc_sustento_info').hide();
                $('#num_doc_sustento').val('');
                $('#num_aut_doc_sustento').val('');
                $('#fecha_emision_doc_sustento').val('');
                return;
            }
            // Llenar info del sustento
            const num = o.text().split(' - ')[0];
            $('#factura_numero').text(num);
            $('#factura_cliente').text(o.data('cliente'));
            $('#factura_empresa').text(o.data('empresa'));
            $('#factura_fecha').text(o.data('fecha'));
            $('#factura_clave').text(o.data('clave'));
            $('#doc_sustento_info').show();

            // Llenar campos ocultos del sustento
            const numDoc = o.data('estab').toString().padStart(3,'0')+'-'+o.data('pto').toString().padStart(3,'0')+'-'+o.data('sec').toString().padStart(9,'0');
            $('#num_doc_sustento').val(numDoc);
            $('#num_aut_doc_sustento').val(o.data('clave'));
            $('#fecha_emision_doc_sustento').val(o.data('fecha'));

            // Auto-llenar destinatario
            $('#razon_social_destinatario').val(o.data('cliente'));
            // Cargar datos del cliente desde la factura vía AJAX
            const clienteId = o.data('cliente-id');
            if (clienteId) {
                $.post('../md_facturacion/get_cliente_info.php', { id: clienteId }, function(d) {
                    if (d && d.identificacion) $('#identificacion_destinatario').val(d.identificacion);
                    if (d && d.direccion) {
                        const dird = $('[name="dir_destinatario"]');
                        if (!dird.val()) dird.val(d.direccion);
                    }
                }, 'json');
            }
        });

        $('#search_producto').on('input', function() {
            const term = $(this).val().toLowerCase();
            const list = $('#productos_list').empty();
            <?php foreach ($productos as $p): ?>
                if ("<?= strtolower(addslashes($p['nombre'])) ?>".includes(term) || "<?= strtolower(addslashes($p['codigo'])) ?>".includes(term)) {
                    list.append('<div class="p-2 border-bottom clickable" onclick="seleccionarProductoModal(<?= $p['id'] ?>, \'<?= addslashes($p['nombre']) ?>\', \'<?= addslashes($p['codigo']) ?>\')" style="cursor:pointer"><?= addslashes($p['nombre']) ?> (<?= addslashes($p['codigo']) ?>)</div>');
                }
            <?php endforeach; ?>
        });

        function seleccionarProductoModal(id, n, c) {
            $('#producto_id').val(id);
            $('#search_producto').val(n);
            $('#productos_list').empty();
        }

        $('#agregar_producto').click(function() {
            const id = $('#producto_id').val();
            const cant = parseFloat($('#cantidad').val());
            const codAd = $('#codigo_adicional').val().trim();
            const o = $('#producto_id option:selected');
            if (!id || !cant || cant <= 0) { alert('Seleccione producto y cantidad válida.'); return; }
            const nombre = o.data('nombre'), codigo = o.data('codigo');
            if (productos.some(p => p.id == id)) { alert('Producto ya agregado.'); return; }
            productos.push({ id: parseInt(id), codigo, codigo_adicional: codAd, nombre, cantidad: cant });
            const row = `<tr data-id="${id}">
                <td>${codigo}</td>
                <td>${codAd || '-'}</td>
                <td>${nombre}</td>
                <td><input type="number" class="form-control form-control-sm cantidad" value="${cant}" min="0.000001" step="0.000001"></td>
                <td><button class="btn btn-danger btn-sm btn-eliminar">X</button></td>
            </tr>`;
            $('#productos_seleccionados tbody').append(row);
            actualizarCamposOcultos();
            $('#modalAgregarProducto').modal('hide');
            $('#producto_id').val(''); $('#cantidad').val(1); $('#codigo_adicional').val(''); $('#search_producto').val('');
        });

        $(document).on('click', '.btn-eliminar', function() {
            const id = $(this).closest('tr').data('id');
            productos = productos.filter(p => p.id != id);
            $(this).closest('tr').remove();
            actualizarCamposOcultos();
        });

        $(document).on('input', '.cantidad', function() {
            const tr = $(this).closest('tr'), id = tr.data('id');
            const cant = parseFloat($(this).val()) || 1;
            const p = productos.find(x => x.id == id);
            if (p) { p.cantidad = cant; }
            actualizarCamposOcultos();
        });

        $('#empresa_id').change(function() {
            const eid = $(this).val();
            if (!eid) { $('#punto_emision_id').html('<option value="">Seleccione...</option>'); return; }
            $.post('get_puntos_emision.php', { empresa_id: eid }, function(d) {
                if (d.error) { $('#punto_emision_id').html('<option value="">'+d.error+'</option>'); return; }
                $('#punto_emision_id').html('<option value="">Seleccione...</option>');
                d.forEach(p => { $('#punto_emision_id').append('<option value="'+p.id+'">'+p.establecimiento+'-'+p.punto_emision+'-'+p.secuencial_guia_remision+'</option>'); });
            },'json');
        });

        function actualizarCamposOcultos() {
            $('#productos_ids').val(productos.map(p => p.id).join(','));
            $('#productos_codigos').val(productos.map(p => p.codigo).join('||'));
            $('#productos_adicionales').val(productos.map(p => p.codigo_adicional).join('||'));
            $('#productos_descripciones').val(productos.map(p => p.nombre).join('||'));
            $('#productos_cantidades').val(productos.map(p => p.cantidad).join(','));
        }

        $('#guiaForm').on('submit', function(e) {
            if (productos.length === 0) { e.preventDefault(); alert('Agregue al menos un producto.'); return; }
            actualizarCamposOcultos();
        });
    </script>
</body>
</html>
