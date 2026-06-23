<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener los clientes registrados del usuario actual
$sql_clientes = "SELECT id, razon_social, identificacion, direccion, email, telefono 
                 FROM clientes WHERE usuario_id = :usuario_id";
$stmt_clientes = $pdo->prepare($sql_clientes);
$stmt_clientes->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_clientes->execute();
$clientes = $stmt_clientes->fetchAll();

// Obtener las empresas registradas del usuario actual y que estén activas
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :usuario_id AND activa = 1";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll();

// Obtener los productos registrados del usuario actual
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
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php 
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php'); ?>
    <title>Factura Nueva</title>
</head>
<body>
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
            <?php require('../entorno/menu.php'); ?>
        </ul>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <nav class="navbar navbar-expand navbar-light bg-white topbar mb-4 static-top shadow">
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3">
                        <i class="fa fa-bars"></i>
                    </button>
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
                        <h1 class="h3 mb-0 text-gray-800">Crear Factura</h1>
                    </div>
                    <!-- Formulario de Factura -->
                    <form action="facturacion_graba.php" method="POST" id="facturaForm">
                        <div class="row">
                            <!-- Selección de Empresa y Punto de Emisión -->
                            <div class="col-lg-6">
                                <!-- Campo Fecha de la Factura -->
                                <div class="form-group">
                                    <label for="fecha_emision">Fecha de Emisión</label>
                                    <input type="date" class="form-control" id="fecha_emision" name="fecha_emision" required value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <!-- Selección de la Empresa -->
                                <div class="form-group">
                                    <label for="empresa_id">Empresa</label>
                                    <select class="form-control" id="empresa_id" name="empresa_id" required>
                                        <option value="">Seleccione una empresa activa</option>
                                        <?php foreach ($empresas as $empresa): ?>
                                            <option value="<?php echo $empresa['id']; ?>"><?php echo htmlspecialchars($empresa['nombre_comercial']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <!-- Selección del Punto de Emisión -->
                                <div class="form-group">
                                    <label for="punto_emision_id">Punto de Emisión</label>
                                    <select class="form-control" id="punto_emision_id" name="punto_emision_id" required>
                                        <option value="">Seleccione un punto de emisión</option>
                                    </select>
                                </div>
                            </div>
                            <!-- Datos del Cliente -->
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label>Buscar Cliente <a class="btn btn-sm btn-success" href="../md_clientes/cliente_nuevo.php">Registrar</a> </label>
                                    <input type="text" id="cliente_buscar" class="form-control" placeholder="Buscar por RUC/Cédula o Nombre">
                                    <div id="cliente_list"></div>
                                </div>
                                <!-- Información del Cliente -->
                                <div id="cliente_info" style="display:none;">
                                    <p><strong>RUC/Cédula: </strong><span id="identificacion"></span></p>
                                    <p><strong>Dirección: </strong><span id="direccion"></span></p>
                                    <p><strong>Email: </strong><span id="email"></span></p>
                                    <p><strong>Teléfono: </strong><span id="telefono"></span></p>
                                </div>
                                <input type="hidden" id="cliente_id" name="cliente_id" value="">
                            </div>
                            <!-- Productos de la Factura -->
                            <div class="col-lg-12">
                                <div class="form-group">
                                    <button type="button" class="float-right btn btn-info" data-toggle="modal" data-target="#modalAgregarProducto">Agregar Producto</button>
                                </div>
                                <!-- Lista de Productos seleccionados -->
                                <table class="table table-bordered" id="productos_seleccionados">
                                    <thead class="bg-primary text-light">
                                        <tr>
                                            <th>Producto</th>
                                            <th>Código</th>
                                            <th>Cantidad</th>
                                            <th>Precio Unitario</th>
                                            <th>Subtotal</th>
                                            <th>IVA</th>
                                            <th>Total + IVA</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <!-- Forma de Pago y Comentarios -->
                            <div class="col-lg-6">
                                <div class="form-group">
                                    <label for="forma_pago_id">Forma de Pago</label>
                                    <select class="form-control" id="forma_pago_id" name="forma_pago_id" required>
                                        <option value="">Seleccione una forma de pago</option>
                                        <?php foreach ($formas_pago as $forma): ?>
                                            <option value="<?php echo $forma['id']; ?>" data-codigo="<?php echo $forma['codigo_sri']; ?>">
                                                <?php echo $forma['nombre']; ?> (<?php echo $forma['codigo_sri']; ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Comentarios</label>
                                    <textarea class="form-control" id="comentarios" name="comentarios" rows="3"></textarea>
                                </div>
                            </div>
							                            <!-- Totales -->
                            <div class="col-lg-6">  
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">Subtotal Sin IVA</span>
                                    <input type="text" class="form-control" id="subtotal_sin_iva" name="subtotal1" readonly value="0.00">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">Descuento (%)</span>
                                    <input type="number" class="form-control" id="descuento" name="descuento" value="0" min="0" max="100">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">Subtotal con Descuento</span>
                                    <input type="text" class="form-control" id="subtotal_con_descuento" name="subtotal2" readonly value="0.00">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">IVA (%)</span>
                                    <input type="text" class="form-control" id="iva_porcentaje" name="iva" readonly value="0.00">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">Valor IVA</span>
                                    <input type="text" class="form-control" id="valor_iva" name="valor_iva" readonly value="0.00">
                                </div>
                                <div class="input-group mb-3">
                                    <span class="border-0 bg-light input-group-text w-50">TOTAL</span>
                                    <input type="text" class="form-control" id="total" name="total" readonly value="0.00">
                                </div>
                            </div>
                        </div>

                        <!-- Campos ocultos para enviar datos de productos -->
                        <input type="hidden" id="productos_ids" name="productos_ids">
                        <input type="hidden" id="productos_cantidades" name="productos_cantidades">
                        <input type="hidden" id="productos_precios" name="productos_precios">
                        <input type="hidden" id="productos_subtotales" name="productos_subtotales">
                        <input type="hidden" id="productos_ivas" name="productos_ivas">
                        <input type="hidden" id="productos_totales" name="productos_totales">

                        <button type="submit" class="btn btn-primary">Generar Factura</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <!-- Modal para agregar productos -->
    <div class="modal fade" id="modalAgregarProducto" tabindex="-1" role="dialog" aria-labelledby="modalAgregarProductoLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalAgregarProductoLabel">Seleccionar Producto</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>Buscar Producto</label>
                        <input type="text" id="search_producto" class="form-control" placeholder="Buscar por nombre o código">
                        <div id="productos_list" style="max-height: 200px; overflow-y: auto; margin-top: 10px;"></div>
                    </div>
                    <div class="form-group">
                        <label>Producto</label>
                        <select id="producto_id" class="form-control">
                            <option value="">Seleccione un producto</option>
                            <?php foreach ($productos as $producto): ?>
                                <option value="<?php echo $producto['id']; ?>" 
                                        data-precio="<?php echo $producto['precio_unitario']; ?>" 
                                        data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>" 
                                        data-codigo="<?php echo $producto['codigo']; ?>">
                                    <?php echo htmlspecialchars($producto['nombre']); ?> - Código: <?php echo $producto['codigo']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Cantidad</label>
                        <input type="number" id="cantidad" class="form-control" value="1" min="1">
                    </div>
                    <button type="button" id="agregar_producto" class="btn btn-primary">Agregar</button>
                </div>
            </div>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>

    <script>
        let productos = [];
        let ivaPorcentaje = 0;

        // Buscar productos en el modal
        $('#search_producto').on('input', function() {
            const term = $(this).val().toLowerCase();
            const list = $('#productos_list').empty();
            <?php foreach ($productos as $producto): ?>
                if ("<?php echo strtolower($producto['nombre']); ?>".includes(term) || 
                    "<?php echo strtolower($producto['codigo']); ?>".includes(term)) {
                    list.append(`
                        <div class="p-2 border-bottom clickable" 
                             onclick="seleccionarProductoModal(
                                 <?php echo $producto['id']; ?>, 
                                 '<?php echo addslashes($producto['nombre']); ?>', 
                                 '<?php echo $producto['codigo']; ?>', 
                                 <?php echo $producto['precio_unitario']; ?>
                             )">
                            <?php echo $producto['nombre']; ?> (<?php echo $producto['codigo']; ?>) - $<?php echo $producto['precio_unitario']; ?>
                        </div>
                    `);
                }
            <?php endforeach; ?>
        });

        function seleccionarProductoModal(id, nombre, codigo, precio) {
            $('#producto_id').val(id);
            $('#search_producto').val(nombre);
            $('#productos_list').empty();
        }

        // Agregar producto desde el select
        $('#agregar_producto').click(function() {
            let producto_id = $('#producto_id').val();
            let cantidad = parseInt($('#cantidad').val());
            let option = $('#producto_id option:selected');
            if (!producto_id || !cantidad || cantidad <= 0) {
                alert('Por favor, seleccione un producto y cantidad válida.');
                return;
            }
            let nombre = option.data('nombre');
            let codigo = option.data('codigo');
            let precio = parseFloat(option.data('precio'));
            let subtotal = cantidad * precio;
            let iva = subtotal * (ivaPorcentaje / 100);
            let total = subtotal + iva;

            // Evitar duplicados
            if (productos.some(p => p.id == producto_id)) {
                alert('Este producto ya fue agregado.');
                return;
            }

            productos.push({ 
                id: producto_id, 
                nombre, 
                codigo, 
                cantidad, 
                precio, 
                subtotal,
                iva,
                total
            });

            $('#productos_seleccionados tbody').append(`
                <tr data-id="${producto_id}">
                    <td>${nombre}</td>
                    <td>${codigo}</td>
                    <td><input type="number" class="form-control cantidad" value="${cantidad}" min="1"></td>
                    <td><input type="number" step="0.01" class="form-control precio-unitario" value="${precio.toFixed(2)}" min="0"></td>
                    <td class="subtotal">${subtotal.toFixed(2)}</td>
                    <td class="iva">${iva.toFixed(2)}</td>
                    <td class="total-item">${total.toFixed(2)}</td>
                    <td><button class="btn btn-danger btn-sm btn-eliminar">X</button></td>
                </tr>
            `);

            actualizarTotales();
            actualizarCamposOcultos();
            $('#modalAgregarProducto').modal('hide');
            $('#producto_id').val('');
            $('#cantidad').val(1);
            $('#search_producto').val('');
        });

        // Eliminar producto
        $(document).on('click', '.btn-eliminar', function() {
            let id = $(this).closest('tr').data('id');
            productos = productos.filter(p => p.id != id);
            $(this).closest('tr').remove();
            actualizarTotales();
            actualizarCamposOcultos();
        });

        // Actualizar cantidad o precio
        $(document).on('input', '.cantidad, .precio-unitario', function() {
            let tr = $(this).closest('tr');
            let id = tr.data('id');
            let cantidad = parseFloat(tr.find('.cantidad').val()) || 1;
            let precio = parseFloat(tr.find('.precio-unitario').val()) || 0;
            let subtotal = cantidad * precio;
            let iva = subtotal * (ivaPorcentaje / 100);
            let total = subtotal + iva;

            tr.find('.subtotal').text(subtotal.toFixed(2));
            tr.find('.iva').text(iva.toFixed(2));
            tr.find('.total-item').text(total.toFixed(2));

            let prod = productos.find(p => p.id == id);
            if (prod) {
                prod.cantidad = cantidad;
                prod.precio = precio;
                prod.subtotal = subtotal;
                prod.iva = iva;
                prod.total = total;
            }

            actualizarTotales();
            actualizarCamposOcultos();
        });

        // Buscar cliente
        $('#cliente_buscar').on('input', function() {
            const term = $(this).val().toLowerCase();
            const list = $('#cliente_list').empty();
            <?php foreach ($clientes as $cliente): ?>
                if ("<?php echo strtolower($cliente['razon_social']); ?>".includes(term) || 
                    "<?php echo strtolower($cliente['identificacion']); ?>".includes(term)) {
                    list.append(`
                        <div class="p-2 border-bottom clickable" 
                             onclick="seleccionarCliente(
                                 <?php echo $cliente['id']; ?>, 
                                 '<?php echo addslashes($cliente['razon_social']); ?>', 
                                 '<?php echo $cliente['identificacion']; ?>', 
                                 '<?php echo addslashes($cliente['direccion']); ?>', 
                                 '<?php echo $cliente['email']; ?>', 
                                 '<?php echo $cliente['telefono']; ?>'
                             )">
                            <?php echo $cliente['razon_social']; ?> - <?php echo $cliente['identificacion']; ?>
                        </div>
                    `);
                }
            <?php endforeach; ?>
        });

        function seleccionarCliente(id, razon_social, identificacion, direccion, email, telefono) {
            $('#cliente_id').val(id);
            $('#cliente_buscar').val(razon_social);
            $('#cliente_list').empty();
            $('#identificacion').text(identificacion);
            $('#direccion').text(direccion);
            $('#email').text(email);
            $('#telefono').text(telefono);
            $('#cliente_info').show();
        }

        // Cargar puntos de emisión SOLO de empresas activas
        $('#empresa_id').change(function() {
            const empresa_id = $(this).val();
            if (!empresa_id) {
                $('#punto_emision_id').html('<option value="">Seleccione...</option>');
                return;
            }
            $.post('get_puntos_emision.php', { empresa_id }, function(data) {
                if (data.error) {
                    $('#punto_emision_id').html(`<option value="">${data.error}</option>`);
                    return;
                }
                $('#punto_emision_id').html('<option value="">Seleccione...</option>');
                data.forEach(p => {
                    $('#punto_emision_id').append(`<option value="${p.id}">${p.establecimiento}-${p.punto_emision}-${p.secuencial_factura}</option>`);
                });
            }, 'json');
        });

        // Obtener IVA del punto de emisión
        $('#punto_emision_id').change(function() {
            const punto_id = $(this).val();
            if (!punto_id) return;
            $.post('get_iva_punto.php', { punto_emision_id: punto_id }, function(data) {
                if (data.iva !== undefined) {
                    ivaPorcentaje = parseFloat(data.iva);
                    $('#iva_porcentaje').val(ivaPorcentaje.toFixed(2));
                    // Recalcular IVA para todos los productos
                    productos.forEach(prod => {
                        prod.iva = prod.subtotal * (ivaPorcentaje / 100);
                        prod.total = prod.subtotal + prod.iva;
                    });
                    // Actualizar la tabla
                    $('#productos_seleccionados tbody tr').each(function() {
                        let id = $(this).data('id');
                        let prod = productos.find(p => p.id == id);
                        if (prod) {
                            $(this).find('.iva').text(prod.iva.toFixed(2));
                            $(this).find('.total-item').text(prod.total.toFixed(2));
                        }
                    });
                    actualizarTotales();
                }
            }, 'json');
        });

        // Actualizar totales generales
        function actualizarTotales() {
            let subtotalSinIva = productos.reduce((sum, p) => sum + p.subtotal, 0);
            let descuento = parseFloat($('#descuento').val()) || 0;
            let subtotalConDescuento = subtotalSinIva - (subtotalSinIva * descuento / 100);
            let valorIva = productos.reduce((sum, p) => sum + p.iva, 0);
            let total = subtotalConDescuento + valorIva;

            // Redondeo a 2 decimales
            subtotalSinIva = Math.round(subtotalSinIva * 100) / 100;
            subtotalConDescuento = Math.round(subtotalConDescuento * 100) / 100;
            valorIva = Math.round(valorIva * 100) / 100;
            total = Math.round(total * 100) / 100;

            $('#subtotal_sin_iva').val(subtotalSinIva.toFixed(2));
            $('#subtotal_con_descuento').val(subtotalConDescuento.toFixed(2));
            $('#valor_iva').val(valorIva.toFixed(2));
            $('#total').val(total.toFixed(2));
        }

        // Actualizar campos ocultos
        function actualizarCamposOcultos() {
            $('#productos_ids').val(productos.map(p => p.id).join(','));
            $('#productos_cantidades').val(productos.map(p => p.cantidad).join(','));
            $('#productos_precios').val(productos.map(p => p.precio.toFixed(2)).join(','));
            $('#productos_subtotales').val(productos.map(p => p.subtotal.toFixed(2)).join(','));
            $('#productos_ivas').val(productos.map(p => p.iva.toFixed(2)).join(','));
            $('#productos_totales').val(productos.map(p => p.total.toFixed(2)).join(','));
        }

        // Validar antes de enviar
        $('#facturaForm').on('submit', function(e) {
            if (productos.length === 0) {
                e.preventDefault();
                alert('Debe agregar al menos un producto.');
                return false;
            }
            if (!$('#cliente_id').val()) {
                e.preventDefault();
                alert('Debe seleccionar un cliente.');
                return false;
            }
            // IVA 0% permitido (RUC que facturan sin IVA)
            actualizarCamposOcultos();
        });

        // Inicializar
        $('#descuento').on('input', actualizarTotales);
    </script>
</body>
</html>