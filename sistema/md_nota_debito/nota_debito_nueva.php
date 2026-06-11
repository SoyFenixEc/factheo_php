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

// Obtener formas de pago
$sql_formas_pago = "SELECT id, nombre, codigo_sri FROM formas_pago ORDER BY nombre";
$stmt_formas_pago = $pdo->prepare($sql_formas_pago);
$stmt_formas_pago->execute();
$formas_pago = $stmt_formas_pago->fetchAll();

// Obtener facturas autorizadas del usuario
$sql_facturas = "SELECT f.id, f.clave_acceso, f.establecimiento, f.punto_emision, f.secuencial,
                        c.razon_social, f.total, f.fecha_emision, e.nombre_comercial
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
    <title>Nota de Débito Nueva</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Crear Nota de Débito</h1>
                    </div>

                    <!-- Selección de documento a modificar -->
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Seleccionar Factura a Modificar</h6>
                        </div>
                        <div class="card-body">
                            <div class="form-group">
                                <select class="form-control" id="factura_sustento_id" name="factura_sustento_id">
                                    <option value="">Seleccione la factura autorizada...</option>
                                    <?php foreach ($facturas_autorizadas as $f): 
                                        $num = str_pad($f['establecimiento'],3,'0',STR_PAD_LEFT).'-'.str_pad($f['punto_emision'],3,'0',STR_PAD_LEFT).'-'.str_pad($f['secuencial'],9,'0',STR_PAD_LEFT);
                                    ?>
                                        <option value="<?= $f['id'] ?>"
                                                data-empresa="<?= htmlspecialchars($f['nombre_comercial']) ?>"
                                                data-cliente="<?= htmlspecialchars($f['razon_social']) ?>"
                                                data-total="<?= $f['total'] ?>"
                                                data-fecha="<?= $f['fecha_emision'] ?>"
                                                data-clave="<?= $f['clave_acceso'] ?>">
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
                                        <p><strong>Empresa:</strong> <span id="factura_empresa"></span></p>
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Fecha:</strong> <span id="factura_fecha"></span></p>
                                        <p><strong>Total:</strong> $<span id="factura_total"></span></p>
                                        <p><strong>Clave Acceso:</strong> <span id="factura_clave" style="font-family:monospace;font-size:0.9rem;word-break:break-all;"></span></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <form action="nota_debito_graba.php" method="POST" id="notaDebitoForm">
                        <input type="hidden" id="factura_sustento_id_val" name="factura_sustento_id" value="">
                        <input type="hidden" id="cliente_id" name="cliente_id" value="">

                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Datos de la Nota de Débito</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Fecha de Emisión</label>
                                            <input type="date" class="form-control" name="fecha_emision" required value="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Empresa</label>
                                            <select class="form-control" id="empresa_id" name="empresa_id" required>
                                                <option value="">Seleccione una empresa activa</option>
                                                <?php foreach ($empresas as $emp): ?>
                                                    <option value="<?= $emp['id'] ?>"><?= htmlspecialchars($emp['nombre_comercial']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Punto de Emisión</label>
                                            <select class="form-control" id="punto_emision_id" name="punto_emision_id" required>
                                                <option value="">Seleccione un punto de emisión</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Motivo</label>
                                            <select class="form-control" name="motivo" required>
                                                <option value="">Seleccione...</option>
                                                <option value="DEVOLUCION">Devolución</option>
                                                <option value="ANULACION">Anulación</option>
                                                <option value="DESCUENTO">Descuento</option>
                                                <option value="REBAJA">Rebaja</option>
                                                <option value="OTROS">Otros</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Descripción del Motivo</label>
                                            <textarea class="form-control" name="motivo_descripcion" rows="2" required placeholder="Describa detalladamente..."></textarea>
                                        </div>
                                        <div class="form-group">
                                            <label>Comentarios</label>
                                            <textarea class="form-control" name="comentarios" rows="2"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Productos</h6>
                                <button type="button" class="btn btn-info btn-sm" data-toggle="modal" data-target="#modalAgregarProducto">+ Agregar Producto</button>
                            </div>
                            <div class="card-body">
                                <table class="table table-bordered" id="productos_seleccionados">
                                    <thead class="bg-primary text-light">
                                        <tr><th>Producto</th><th>Código</th><th>Cantidad</th><th>Precio Unitario</th><th>Subtotal</th><th>IVA</th><th>Total</th><th>Acción</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="card shadow mb-4">
                            <div class="card-header py-3"><h6 class="m-0 font-weight-bold text-primary">Totales</h6></div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-lg-6">
                                        <div class="form-group">
                                            <label>Forma de Pago</label>
                                            <select class="form-control" name="forma_pago_id" required>
                                                <option value="">Seleccione...</option>
                                                <?php foreach ($formas_pago as $fp): ?>
                                                    <option value="<?= $fp['id'] ?>"><?= $fp['nombre'] ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-lg-6">
                                        <div class="input-group mb-3"><span class="border-0 bg-light input-group-text w-50">Subtotal</span><input type="text" class="form-control" id="subtotal_sin_iva" name="subtotal1" readonly value="0.00"></div>
                                        <div class="input-group mb-3"><span class="border-0 bg-light input-group-text w-50">IVA (%)</span><input type="text" class="form-control" id="iva_porcentaje" name="iva" readonly value="0.00"></div>
                                        <div class="input-group mb-3"><span class="border-0 bg-light input-group-text w-50">Valor IVA</span><input type="text" class="form-control" id="valor_iva" name="valor_iva" readonly value="0.00"></div>
                                        <div class="input-group mb-3"><span class="border-0 bg-light input-group-text w-50">Valor Modificación</span><input type="text" class="form-control" id="total" name="total" readonly value="0.00"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" id="productos_ids" name="productos_ids">
                        <input type="hidden" id="productos_cantidades" name="productos_cantidades">
                        <input type="hidden" id="productos_precios" name="productos_precios">
                        <input type="hidden" id="productos_subtotales" name="productos_subtotales">
                        <input type="hidden" id="productos_ivas" name="productos_ivas">
                        <input type="hidden" id="productos_totales" name="productos_totales">

                        <button type="submit" class="btn btn-primary btn-lg mb-4">Generar Nota de Débito</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

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
                                <option value="<?= $p['id'] ?>" data-precio="<?= $p['precio_unitario'] ?>" data-nombre="<?= htmlspecialchars($p['nombre']) ?>" data-codigo="<?= $p['codigo'] ?>"><?= htmlspecialchars($p['nombre']) ?> - <?= $p['codigo'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Cantidad</label><input type="number" id="cantidad" class="form-control" value="1" min="1"></div>
                    <button type="button" id="agregar_producto" class="btn btn-primary">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>

    <script>
        let productos = [];
        let ivaPorcentaje = 0;

        $('#factura_sustento_id').change(function() {
            const o = $(this).find('option:selected');
            if (!o.val()) { $('#doc_sustento_info').hide(); $('#factura_sustento_id_val').val(''); return; }
            $('#factura_sustento_id_val').val(o.val());
            $('#factura_numero').text(o.text().split(' - ')[0]);
            $('#factura_cliente').text(o.data('cliente'));
            $('#factura_empresa').text(o.data('empresa'));
            $('#factura_fecha').text(o.data('fecha'));
            $('#factura_total').text(parseFloat(o.data('total')).toFixed(2));
            $('#factura_clave').text(o.data('clave'));
            $('#doc_sustento_info').show();
            const emp = o.data('empresa');
            $('#empresa_id option').each(function() { if ($(this).text() === emp) { $('#empresa_id').val($(this).val()).trigger('change'); } });
        });

        $('#search_producto').on('input', function() {
            const term = $(this).val().toLowerCase();
            const list = $('#productos_list').empty();
            <?php foreach ($productos as $p): ?>
                if ("<?= strtolower($p['nombre']) ?>".includes(term) || "<?= strtolower($p['codigo']) ?>".includes(term)) {
                    list.append('<div class="p-2 border-bottom clickable" onclick="seleccionarProductoModal(<?= $p['id'] ?>, \'<?= addslashes($p['nombre']) ?>\', \'<?= $p['codigo'] ?>\', <?= $p['precio_unitario'] ?>)"><?= $p['nombre'] ?> (<?= $p['codigo'] ?>) - $<?= $p['precio_unitario'] ?></div>');
                }
            <?php endforeach; ?>
        });

        function seleccionarProductoModal(id, n, c, p) { $('#producto_id').val(id); $('#search_producto').val(n); $('#productos_list').empty(); }

        $('#agregar_producto').click(function() {
            const id = $('#producto_id').val();
            const cant = parseInt($('#cantidad').val());
            const o = $('#producto_id option:selected');
            if (!id || !cant || cant <= 0) { alert('Seleccione producto y cantidad.'); return; }
            const nombre = o.data('nombre'), codigo = o.data('codigo'), precio = parseFloat(o.data('precio'));
            const sub = cant * precio, iva = sub * (ivaPorcentaje / 100), tot = sub + iva;
            if (productos.some(p => p.id == id)) { alert('Producto ya agregado.'); return; }
            productos.push({ id: parseInt(id), nombre, codigo, cantidad: cant, precio, subtotal: sub, iva, total: tot });
            $('#productos_seleccionados tbody').append(`<tr data-id="${id}"><td>${nombre}</td><td>${codigo}</td><td><input type="number" class="form-control cantidad" value="${cant}" min="1"></td><td><input type="number" step="0.01" class="form-control precio-unitario" value="${precio.toFixed(2)}" min="0"></td><td class="subtotal">${sub.toFixed(2)}</td><td class="iva">${iva.toFixed(2)}</td><td class="total-item">${tot.toFixed(2)}</td><td><button class="btn btn-danger btn-sm btn-eliminar">X</button></td></tr>`);
            actualizarTotales(); actualizarCamposOcultos();
            $('#modalAgregarProducto').modal('hide'); $('#producto_id').val(''); $('#cantidad').val(1); $('#search_producto').val('');
        });

        $(document).on('click', '.btn-eliminar', function() {
            const id = $(this).closest('tr').data('id');
            productos = productos.filter(p => p.id != id);
            $(this).closest('tr').remove();
            actualizarTotales(); actualizarCamposOcultos();
        });

        $(document).on('input', '.cantidad, .precio-unitario', function() {
            const tr = $(this).closest('tr'), id = tr.data('id');
            const cant = parseFloat(tr.find('.cantidad').val())||1, precio = parseFloat(tr.find('.precio-unitario').val())||0;
            const sub = cant * precio, iva = sub * (ivaPorcentaje / 100), tot = sub + iva;
            tr.find('.subtotal').text(sub.toFixed(2)); tr.find('.iva').text(iva.toFixed(2)); tr.find('.total-item').text(tot.toFixed(2));
            const p = productos.find(x => x.id == id);
            if (p) { p.cantidad = cant; p.precio = precio; p.subtotal = sub; p.iva = iva; p.total = tot; }
            actualizarTotales(); actualizarCamposOcultos();
        });

        $('#empresa_id').change(function() {
            const eid = $(this).val();
            if (!eid) { $('#punto_emision_id').html('<option value="">Seleccione...</option>'); return; }
            $.post('get_puntos_emision.php', { empresa_id: eid }, function(d) {
                if (d.error) { $('#punto_emision_id').html('<option value="">'+d.error+'</option>'); return; }
                $('#punto_emision_id').html('<option value="">Seleccione...</option>');
                d.forEach(p => { $('#punto_emision_id').append('<option value="'+p.id+'">'+p.establecimiento+'-'+p.punto_emision+'-'+p.secuencial_factura+'</option>'); });
            },'json');
        });

        $('#punto_emision_id').change(function() {
            const pid = $(this).val();
            if (!pid) return;
            $.post('get_iva_punto.php', { punto_emision_id: pid }, function(d) {
                if (d.iva !== undefined) {
                    ivaPorcentaje = parseFloat(d.iva);
                    $('#iva_porcentaje').val(ivaPorcentaje.toFixed(2));
                    productos.forEach(p => { p.iva = p.subtotal * (ivaPorcentaje/100); p.total = p.subtotal + p.iva; });
                    $('#productos_seleccionados tbody tr').each(function() {
                        const id = $(this).data('id'), p = productos.find(x => x.id == id);
                        if (p) { $(this).find('.iva').text(p.iva.toFixed(2)); $(this).find('.total-item').text(p.total.toFixed(2)); }
                    });
                    actualizarTotales();
                }
            },'json');
        });

        function actualizarTotales() {
            const s = Math.round(productos.reduce((a,p)=>a+p.subtotal,0)*100)/100;
            const v = Math.round(productos.reduce((a,p)=>a+p.iva,0)*100)/100;
            const t = Math.round((s+v)*100)/100;
            $('#subtotal_sin_iva').val(s.toFixed(2)); $('#valor_iva').val(v.toFixed(2)); $('#total').val(t.toFixed(2));
        }

        function actualizarCamposOcultos() {
            $('#productos_ids').val(productos.map(p=>p.id).join(','));
            $('#productos_cantidades').val(productos.map(p=>p.cantidad).join(','));
            $('#productos_precios').val(productos.map(p=>p.precio.toFixed(2)).join(','));
            $('#productos_subtotales').val(productos.map(p=>p.subtotal.toFixed(2)).join(','));
            $('#productos_ivas').val(productos.map(p=>p.iva.toFixed(2)).join(','));
            $('#productos_totales').val(productos.map(p=>p.total.toFixed(2)).join(','));
        }

        $('#notaDebitoForm').on('submit', function(e) {
            if (!$('#factura_sustento_id_val').val()) { e.preventDefault(); alert('Seleccione factura a modificar.'); return; }
            if (productos.length === 0) { e.preventDefault(); alert('Agregue al menos un producto.'); return; }
            actualizarCamposOcultos();
        });
    </script>
</body>
</html>
