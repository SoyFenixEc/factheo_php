<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$liqui_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($liqui_id <= 0) { header("Location: liquidacion_compra_lista.php"); exit; }

$sql = "
    SELECT 
        f.id, f.fecha_emision, f.total, f.comentarios, f.subtotal1, f.descuento, f.subtotal2,
        f.iva, f.valor_iva, f.clave_acceso, f.estado_xml, f.numero_autorizacion,
        f.fecha_autorizacion, f.observacion_sri,
        fp.nombre AS forma_pago,
        pv.nombre AS proveedor_nombre, pv.ruc AS proveedor_ruc,
        pv.direccion AS proveedor_direccion, pv.telefono AS proveedor_telefono,
        e.nombre_comercial AS empresa_nombre, e.razon_social AS empresa_razon_social,
        e.ruc AS empresa_ruc, e.direccion AS empresa_direccion, e.telefono AS empresa_telefono,
        e.logo AS logo,
        p.establecimiento, p.punto_emision, p.secuencial_liquidacion_compra
    FROM facturas f
    JOIN proveedores pv ON f.proveedor_id = pv.id
    JOIN empresa e ON f.empresa_id = e.id
    JOIN punto_emision p ON f.punto_emision_id = p.id
    JOIN formas_pago fp ON f.forma_pago_id = fp.id
    WHERE f.id = ? AND f.tipo_comprobante_id = '03'
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$liqui_id]);
$factura = $stmt->fetch();
if (!$factura) { die("Liquidación no encontrada."); }

$secuencial = str_pad($factura['secuencial_liquidacion_compra'], 9, '0', STR_PAD_LEFT);
$numero = $factura['establecimiento'] . '-' . $factura['punto_emision'] . '-' . $secuencial;

$estado = $factura['estado_xml'] ?? 'GENERADO';
$badge_class = match($estado) { 'GENERADO'=>'badge-info', 'FIRMADO'=>'badge-warning', 'AUTORIZADO'=>'badge-success', default=>'badge-secondary' };

$sql_det = "SELECT df.cantidad, df.precio_unitario, df.subtotal, p.nombre AS producto, p.codigo
            FROM detalle_factura df JOIN productos p ON df.producto_id = p.id
            WHERE df.factura_id = ?";
$stmt_det = $pdo->prepare($sql_det);
$stmt_det->execute([$liqui_id]);
$detalle = $stmt_det->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>RIDE - Liquidación #<?php echo $factura['id']; ?></title>
    <?php require('../entorno/link.php'); ?>
    <style>
        body { background-color: #f8f9fc; font-family: 'Arial', sans-serif; }
        .ride-container { max-width: 1000px; margin: 20px auto; background: white; border: 1px solid #dee2e6; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); overflow: hidden; }
        .ride-header { background: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 20px; text-align: center; }
        .ride-logo { max-height: 60px; margin-bottom: 10px; }
        .ride-empresa { font-size: 1.1em; font-weight: bold; color: #333; }
        .ride-ruc { font-size: 0.9em; color: #555; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 20px; }
        .info-box { border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background: #fdfdfd; }
        .info-box h6 { margin: 0 0 10px 0; font-size: 1.05em; color: #495057; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
        .info-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 0.95em; }
        .product-table { width: 100%; border-collapse: collapse; margin: 20px; font-size: 0.9em; }
        .product-table th { background-color: #2e59d9; color: white; text-align: left; padding: 10px; font-weight: 600; }
        .product-table td { padding: 8px 10px; border-bottom: 1px solid #dee2e6; }
        .totales-box { width: 300px; float: right; border: 1px solid #dee2e6; border-radius: 8px; margin: 0 20px 20px 20px; background: #fdfdfd; }
        .totales-row { display: flex; justify-content: space-between; padding: 6px 15px; font-size: 0.95em; }
        .totales-row.bold { font-weight: bold; background: #f8f9fa; }
        .clave-acceso { font-family: monospace; font-size: 0.85em; word-break: break-all; margin: 10px 20px; }
        .estado-badge { display: inline-block; padding: 5px 12px; border-radius: 4px; font-size: 0.85em; font-weight: bold; color: white; }
        .badge-info { background: #17a2b8; } .badge-warning { background: #ffc107; color: #000; } .badge-success { background: #28a745; } .badge-secondary { background: #6c757d; }
        .actions { text-align: center; padding: 20px; border-top: 1px solid #dee2e6; background: #f8f9fa; }
        @media print { .actions, #wrapper, #content-wrapper, #sidebarToggleTop { display: none !important; } body, .ride-container { margin: 0; box-shadow: none; border: none; } }
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
                    <div class="ride-container">
                        <div class="ride-header">
                            <img src="../md_empresa/logos/<?php echo $factura['logo']; ?>" class="ride-logo">
                            <div class="ride-empresa"><?php echo $factura['empresa_nombre']; ?></div>
                            <div class="ride-ruc">RUC: <?php echo $factura['empresa_ruc']; ?></div>
                        </div>
                        <div class="info-grid">
                            <div class="info-box">
                                <h6>Datos del Emisor</h6>
                                <div class="info-row"><strong>Razón Social:</strong> <?php echo $factura['empresa_razon_social']; ?></div>
                                <div class="info-row"><strong>Dirección:</strong> <?php echo $factura['empresa_direccion']; ?></div>
                                <div class="info-row"><strong>Teléfono:</strong> <?php echo $factura['empresa_telefono']; ?></div>
                            </div>
                            <div class="info-box">
                                <h6>Datos del Proveedor</h6>
                                <div class="info-row"><strong>Nombre:</strong> <?php echo $factura['proveedor_nombre']; ?></div>
                                <div class="info-row"><strong>RUC/CI:</strong> <?php echo $factura['proveedor_ruc']; ?></div>
                                <div class="info-row"><strong>Dirección:</strong> <?php echo $factura['proveedor_direccion']; ?></div>
                                <div class="info-row"><strong>Teléfono:</strong> <?php echo $factura['proveedor_telefono']; ?></div>
                            </div>
                        </div>
                        <div class="info-grid">
                            <div class="info-box">
                                <h6>Información del Comprobante</h6>
                                <div class="info-row"><strong>Tipo:</strong> LIQUIDACIÓN DE COMPRA</div>
                                <div class="info-row"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha_emision'])); ?></div>
                                <div class="info-row"><strong>Número:</strong> <?php echo $numero; ?></div>
                                <div class="info-row"><strong>Forma de Pago:</strong> <?php echo $factura['forma_pago']; ?></div>
                                <div class="info-row"><strong>Estado:</strong> <span class="estado-badge <?php echo $badge_class; ?>"><?php echo $estado; ?></span></div>
                                <?php if($estado=='DEVUELTA'){ ?>
                                    <div class="info-row"><strong>Respuesta SRI: </strong> <?php echo $factura['observacion_sri']; ?></div>
                                <?php } ?>
                            </div>
                            <div class="info-box">
                                <h6>Autorización</h6>
                                <div class="info-row"><strong>Clave de Acceso:</strong></div>
                                <div class="clave-acceso"><span style="font-size:13px"><?php echo $factura['clave_acceso']; ?></span></div>
                                <?php if ($factura['numero_autorizacion']): ?>
                                    <div class="info-row"><strong>N° Autorización:</strong> <span style="font-size:13px"><?php echo $factura['numero_autorizacion']; ?></span></div>
                                    <div class="info-row"><strong>Fecha:</strong> <?php echo date('d/m/Y H:i', strtotime($factura['fecha_autorizacion'])); ?></div>
                                <?php else: ?>
                                    <div class="info-row"><em>No autorizado aún</em></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <table class="product-table">
                            <thead><tr><th>Código</th><th>Descripción</th><th>Cantidad</th><th>Precio Unit.</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($detalle as $prod): ?>
                                <tr>
                                    <td><?php echo $prod['codigo']; ?></td>
                                    <td><?php echo $prod['producto']; ?></td>
                                    <td><?php echo number_format($prod['cantidad'], 2); ?></td>
                                    <td>$<?php echo number_format($prod['precio_unitario'], 2); ?></td>
                                    <td>$<?php echo number_format($prod['subtotal'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="totales-box">
                            <div class="totales-row"><strong>Subtotal:</strong> <span>$<?php echo number_format($factura['subtotal1'], 2); ?></span></div>
                            <div class="totales-row"><strong>IVA <?php echo $factura['iva']; ?>%:</strong> <span>$<?php echo number_format($factura['valor_iva'], 2); ?></span></div>
                            <div class="totales-row bold"><strong>TOTAL:</strong> <span>$<?php echo number_format($factura['total'], 2); ?></span></div>
                        </div>
                        <div class="actions">
                            <a href="liquidacion_compra_lista.php" class="btn btn-secondary ml-2"><i class="fas fa-arrow-left"></i> Volver</a>
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
