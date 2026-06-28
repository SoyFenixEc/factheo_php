<?php
// ================================================
// FACTUHEO MPOS - Punto de Venta Rápido
// Adaptado de tienda_mpos.php
// ================================================
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
$usuario_id = $_SESSION['usuario_id'];
$empresa_id = isset($_GET['empresa']) ? intval($_GET['empresa']) : 0;

// Si no hay empresa, redirigir a selección
if (!$empresa_id) {
    $stmt_e = $pdo->prepare("SELECT id, nombre_comercial, ruc, razon_social FROM empresa WHERE usuario_id = ? AND activa = 1 LIMIT 1");
    $stmt_e->execute([$usuario_id]);
    $empresa = $stmt_e->fetch();
    if (!$empresa) die("No hay empresas activas.");
    $empresa_id = $empresa['id'];
} else {
    $stmt_e = $pdo->prepare("SELECT id, nombre_comercial, ruc, razon_social FROM empresa WHERE id = ? AND usuario_id = ? AND activa = 1");
    $stmt_e->execute([$empresa_id, $usuario_id]);
    $empresa = $stmt_e->fetch();
    if (!$empresa) die("Empresa no encontrada.");
}

// Obtener punto de emisión
$stmt_pe = $pdo->prepare("SELECT * FROM punto_emision WHERE empresa_id = ? LIMIT 1");
$stmt_pe->execute([$empresa_id]);
$pto_emi = $stmt_pe->fetch();
if (!$pto_emi) die("No hay punto de emisión configurado.");

// Usar última forma_pago_id de la BD o default 1
$stmt_fp = $pdo->query("SELECT id, nombre, codigo_sri FROM formas_pago ORDER BY id");
$formas_pago = $stmt_fp->fetchAll();

// Ambiente
$ambiente = $empresa['ambiente_id'] ?? 2;

// Obtener categorías (opcional, si hay columna)
$categorias = [];
$check_cat = $pdo->query("SHOW COLUMNS FROM productos LIKE 'categoria_id'");
if ($check_cat->fetch()) {
    try {
        $stmt_cat = $pdo->query("SELECT DISTINCT categoria_id AS id, categoria_id AS nombre FROM productos WHERE usuario_id = $usuario_id AND categoria_id IS NOT NULL AND categoria_id > 0 ORDER BY categoria_id");
        $categorias = $stmt_cat->fetchAll();
    } catch (Exception $e) {}
}

// Filtros
$categoria_filtro = isset($_GET['categoria']) ? intval($_GET['categoria']) : 0;
$busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Obtener productos del usuario
$sql_prod = "SELECT p.* FROM productos p WHERE p.usuario_id = ?";
$params = [$usuario_id];

// Verificar si existe columna categoria_id
$check_cat2 = $pdo->query("SHOW COLUMNS FROM productos LIKE 'categoria_id'");
if ($check_cat2->fetch() && $categoria_filtro > 0) {
    $sql_prod .= " AND p.categoria_id = ?";
    $params[] = $categoria_filtro;
}

if (!empty($busqueda)) {
    $sql_prod .= " AND (p.nombre LIKE ? OR p.codigo LIKE ?)";
    $st = "%$busqueda%";
    $params[] = $st; $params[] = $st;
}

$sql_prod .= " ORDER BY p.nombre";
$stmt_prod = $pdo->prepare($sql_prod);
$stmt_prod->execute($params);
$productos = $stmt_prod->fetchAll();

// ===== INICIALIZAR CARRITO EN SESIÓN =====
if (!isset($_SESSION['mpos_cart'])) $_SESSION['mpos_cart'] = [];
if (!isset($_SESSION['mpos_opts'])) $_SESSION['mpos_opts'] = ['factura' => 1, 'envio' => 0];

$carrito = [];
$prod_ids = array_keys($_SESSION['mpos_cart']);
if (!empty($prod_ids)) {
    $in = implode(',', array_fill(0, count($prod_ids), '?'));
    $stmt_c = $pdo->prepare("SELECT id, codigo, nombre, precio_unitario, foto, stock FROM productos WHERE id IN ($in)");
    $stmt_c->execute($prod_ids);
    foreach ($stmt_c->fetchAll() as $p) {
        $p['cantidad'] = $_SESSION['mpos_cart'][$p['id']];
        $p['subtotal'] = $p['precio_unitario'] * $p['cantidad'];
        $carrito[] = $p;
    }
}

$carrito_subtotal = array_sum(array_column($carrito, 'subtotal'));
$carrito_count = array_sum(array_column($carrito, 'cantidad'));
$opciones_factura = $_SESSION['mpos_opts']['factura'];
$opciones_envio = $_SESSION['mpos_opts']['envio'];
$costo_envio = $opciones_envio ? 5.00 : 0.00;
$iva_porcentaje = 15;
$iva = $opciones_factura ? ($carrito_subtotal * $iva_porcentaje / 100) : 0;
$total_carrito = $carrito_subtotal + $iva + $costo_envio;

// ===== AJAX POST =====
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $accion = $_POST['accion'] ?? '';
    $is_ajax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
    $prod_id = isset($_POST['producto_id']) ? intval($_POST['producto_id']) : 0;
    $cantidad = isset($_POST['cantidad']) ? intval($_POST['cantidad']) : 1;

    if ($accion === 'agregar' && $prod_id) {
        $_SESSION['mpos_cart'][$prod_id] = ($_SESSION['mpos_cart'][$prod_id] ?? 0) + $cantidad;
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit(); }
    }
    elseif ($accion === 'actualizar' && $prod_id) {
        if ($cantidad <= 0) unset($_SESSION['mpos_cart'][$prod_id]);
        else $_SESSION['mpos_cart'][$prod_id] = $cantidad;
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit(); }
    }
    elseif ($accion === 'eliminar' && $prod_id) {
        unset($_SESSION['mpos_cart'][$prod_id]);
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true]); exit(); }
    }
    elseif ($accion === 'opciones') {
        $_SESSION['mpos_opts']['factura'] = isset($_POST['factura']) ? 1 : 0;
        $_SESSION['mpos_opts']['envio'] = isset($_POST['envio']) ? 1 : 0;
        $n_iva = $_SESSION['mpos_opts']['factura'] ? ($carrito_subtotal * $iva_porcentaje / 100) : 0;
        $n_envio = $_SESSION['mpos_opts']['envio'] ? 5.00 : 0.00;
        if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => true, 'iva' => $n_iva, 'costo_envio' => $n_envio, 'total' => $carrito_subtotal + $n_iva + $n_envio]); exit(); }
    }
    elseif ($accion === 'finalizar') {
        // Recibir datos del formulario
        $cliente_id = intval($_POST['cliente_id']);
        $forma_pago_id = intval($_POST['forma_pago_id'] ?? 1);
        $comentarios = trim($_POST['comentarios'] ?? '');

        if (!$cliente_id) die("Debe seleccionar un cliente.");
        if (empty($carrito)) die("Carrito vacío.");

        try {
            $pdo->beginTransaction();
            $secuencial = intval($pto_emi['secuencial_factura']) + 1;

            $subtotal1 = $carrito_subtotal;
            $descuento = 0;
            $subtotal2 = $subtotal1 - $descuento;
            $valor_iva = $iva;
            $total = $total_carrito;

            // Insertar factura
            $sql_f = "INSERT INTO facturas 
                (usuario_id, empresa_id, cliente_id, forma_pago_id, punto_emision_id,
                 establecimiento, punto_emision, secuencial,
                 subtotal1, descuento, subtotal2, iva, valor_iva, total,
                 tipo_comprobante_id, ambiente_id,
                 estado, estado_xml, estado_sri,
                 fecha_emision, comentarios)
                VALUES (?,?,?,?,?,  ?,?,?,  ?,?,?,?,?,?,  '01',?,  'generado','PENDIENTE','Pendiente',  NOW(),?)";
            $stmt_f = $pdo->prepare($sql_f);
            $stmt_f->execute([
                $usuario_id, $empresa_id, $cliente_id, $forma_pago_id, $pto_emi['id'],
                $pto_emi['establecimiento'], $pto_emi['punto_emision'], $secuencial,
                $subtotal1, $descuento, $subtotal2, $iva_porcentaje, $valor_iva, $total,
                $ambiente,
                $comentarios
            ]);
            $factura_id = $pdo->lastInsertId();

            // Detalle
            $stmt_det = $pdo->prepare("INSERT INTO detalle_factura 
                (factura_id, producto_id, cantidad, precio_unitario, subtotal, iva, total, iva_porcentaje)
                VALUES (?,?,?,?,?,?,?,?)");
            foreach ($carrito as $item) {
                $iva_item = $opciones_factura ? ($item['subtotal'] * $iva_porcentaje / 100) : 0;
                $total_item = $item['subtotal'] + $iva_item;
                $stmt_det->execute([
                    $factura_id, $item['id'], $item['cantidad'], $item['precio_unitario'],
                    $item['subtotal'], $iva_item, $total_item, $opciones_factura ? $iva_porcentaje : 0
                ]);
            }

            // Actualizar secuencial
            $pdo->prepare("UPDATE punto_emision SET secuencial_factura = ? WHERE id = ?")->execute([$secuencial, $pto_emi['id']]);
            $pdo->commit();

            // Limpiar carrito
            $_SESSION['mpos_cart'] = [];

            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'factura_id' => $factura_id]);
                exit();
            } else {
                header("Location: facturacion_completa.php?id=$factura_id");
                exit();
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            if ($is_ajax) { header('Content-Type: application/json'); echo json_encode(['success' => false, 'error' => $e->getMessage()]); exit(); }
            die("Error: " . $e->getMessage());
        }
    }

    // Si no es ajax, redirigir
    if (!$is_ajax) { header("Location: facturacion_mpos.php?empresa=$empresa_id"); exit(); }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/link.php'); ?>
    <title>MPOS - Nueva Factura Rápida</title>
    <style>
        :root{--primary:#4e73df;--secondary:#6c757d;--success:#1cc88a;--warning:#f6c23e;--danger:#e74a3b;--light:#f8f9fc;--dark:#5a5c69;--border:#e3e6f0;--shadow:0 0.15rem 1.75rem 0 rgba(58,59,69,0.15)}
        body{background:#f8f9fc;font-size:.85rem}
        .main-layout{display:flex;gap:1rem;min-height:calc(100vh-200px)}
        .left-column{width:220px;flex-shrink:0}
        .center-column{flex:1;min-width:0}
        .right-column{width:340px;flex-shrink:0}
        .sidebar-cat{background:white;border-radius:.5rem;box-shadow:var(--shadow);overflow:hidden}
        .cat-item{display:flex;justify-content:space-between;padding:.55rem 1rem;border-bottom:1px solid var(--border);text-decoration:none;color:var(--dark);transition:.2s}
        .cat-item:hover{background:var(--light);color:var(--primary);padding-left:1.3rem}
        .cat-item.active{background:var(--primary);color:white}
        .cat-count{font-size:.75rem;background:var(--light);padding:.15rem .5rem;border-radius:10px}
        .cat-item.active .cat-count{background:rgba(255,255,255,.2);color:white}
        .products-panel{background:white;border-radius:.5rem;box-shadow:var(--shadow);padding:1.25rem;display:flex;flex-direction:column;height:100%}
        .search-bar{display:flex;gap:.5rem;margin-bottom:.75rem}
        .search-bar input{flex:1;padding:.45rem .75rem;border:1px solid var(--border);border-radius:.375rem;font-size:.85rem}
        .prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:.75rem;flex:1;overflow-y:auto;padding-right:.5rem}
        .prod-card{background:white;border-radius:.5rem;overflow:hidden;box-shadow:0 .125rem .25rem rgba(0,0,0,.075);transition:.3s;border:1px solid var(--border);display:flex;flex-direction:column}
        .prod-card:hover{transform:translateY(-3px);box-shadow:0 .5rem 1rem rgba(0,0,0,.1);border-color:var(--primary)}
        .prod-img{height:120px;background:var(--light);display:flex;align-items:center;justify-content:center;overflow:hidden}
        .prod-img img{max-height:100%;max-width:100%;object-fit:contain;padding:.75rem}
        .prod-body{padding:.65rem;flex:1;display:flex;flex-direction:column}
        .prod-cat{font-size:.65rem;color:var(--secondary);margin-bottom:.15rem}
        .prod-name{font-size:.8rem;font-weight:600;margin-bottom:.3rem;line-height:1.2;height:2rem;overflow:hidden}
        .prod-price{font-size:.95rem;font-weight:700;color:var(--primary);margin-bottom:.4rem}
        .prod-stock{font-size:.7rem;margin-bottom:.4rem}
        .prod-stock.ok{color:var(--success)}
        .prod-stock.low{color:var(--warning)}
        .prod-stock.out{color:var(--danger)}
        .prod-actions{display:flex;gap:.4rem;margin-top:auto}
        .qty-group{display:flex;align-items:center;background:var(--light);border:1px solid var(--border);border-radius:.25rem}
        .qty-group button{width:26px;height:26px;border:none;background:white;cursor:pointer;font-size:.8rem}
        .qty-group button:hover{background:var(--primary);color:white}
        .qty-group input{width:34px;text-align:center;border:none;background:transparent;font-size:.8rem;font-weight:600}
        .btn-add{flex:1;background:linear-gradient(135deg,var(--primary),#2e59d9);color:white;border:none;border-radius:.25rem;padding:.4rem;font-size:.75rem;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.25rem}
        .btn-add:hover{background:linear-gradient(135deg,#2e59d9,var(--primary))}
        .cart-panel{display:flex;flex-direction:column;background:white;border-radius:.5rem;box-shadow:var(--shadow);overflow:hidden;border:1px solid var(--border)}
        .cart-header{background:linear-gradient(135deg,var(--primary),#2e59d9);color:white;padding:.65rem 1rem}
        .cart-header h6{margin:0;display:flex;justify-content:space-between;align-items:center;font-size:.85rem}
        .cart-count{background:rgba(255,255,255,.2);padding:.15rem .5rem;border-radius:20px;font-size:.75rem}
        .cart-items{flex:1;overflow-y:auto;padding:.75rem;min-height:250px;max-height:400px}
        .cart-empty{text-align:center;padding:2rem;color:var(--secondary)}
        .cart-empty i{font-size:2rem;opacity:.3;margin-bottom:.75rem}
        .cart-item{padding:.6rem;margin-bottom:.5rem;background:var(--light);border-radius:.375rem;border:1px solid var(--border)}
        .cart-item:last-child{margin-bottom:0}
        .cart-item-name{font-size:.78rem;font-weight:600;margin-bottom:.2rem}
        .cart-item-price{font-size:.75rem;color:var(--primary)}
        .cart-item-controls{display:flex;align-items:center;justify-content:space-between;margin-top:.35rem;padding-top:.35rem;border-top:1px dashed #dee2e6}
        .cart-qty{display:flex;align-items:center;gap:.2rem}
        .cart-qty button{width:22px;height:22px;border:1px solid #ced4da;background:white;border-radius:.25rem;font-size:.65rem;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0;color:#495057}
        .cart-qty button:hover{background:#e9ecef}
        .cart-qty input{width:30px;height:22px;text-align:center;border:1px solid #ced4da;border-radius:.25rem;font-size:.75rem;font-weight:600;padding:0}
        .cart-remove{width:22px;height:22px;border:none;background:#f8d7da;color:#721c24;border-radius:.25rem;font-size:.6rem;cursor:pointer;display:flex;align-items:center;justify-content:center}
        .cart-remove:hover{background:#f5c6cb}
        .cart-opts{padding:.6rem 1rem;border-bottom:1px solid var(--border);background:var(--light)}
        .opt-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:.4rem;font-size:.78rem}
        .opt-row:last-child{margin-bottom:0}
        .switch{position:relative;display:inline-block;width:36px;height:18px}
        .switch input{opacity:0;width:0;height:0}
        .slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#ccc;transition:.4s;border-radius:18px}
        .slider:before{position:absolute;content:"";height:14px;width:14px;left:2px;bottom:2px;background:white;transition:.4s;border-radius:50%}
        input:checked+.slider{background:var(--primary)}
        input:checked+.slider:before{transform:translateX(18px)}
        .cart-summary{padding:.75rem 1rem}
        .sum-row{display:flex;justify-content:space-between;margin-bottom:.35rem;font-size:.8rem}
        .sum-total{font-weight:700;font-size:.95rem;color:var(--primary);border-top:1px solid var(--border);padding-top:.4rem;margin-top:.4rem}
        .btn-checkout{background:linear-gradient(135deg,#1cc88a,#17a673);color:white;border:none;border-radius:.375rem;padding:.55rem;font-size:.8rem;font-weight:600;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;margin-top:.6rem;transition:.3s}
        .btn-checkout:hover{background:linear-gradient(135deg,#17a673,#1cc88a)}
        .btn-checkout:disabled{opacity:.5;cursor:not-allowed}
        .prod-grid::-webkit-scrollbar,.cart-items::-webkit-scrollbar{width:4px}
        .prod-grid::-webkit-scrollbar-thumb,.cart-items::-webkit-scrollbar-thumb{background:#c1c1c1;border-radius:3px}
        @media(max-width:1200px){.main-layout{flex-direction:column}.left-column,.center-column,.right-column{width:100%}}
        @media(max-width:768px){.prod-grid{grid-template-columns:repeat(auto-fill,minmax(140px,1fr))}.search-bar{flex-direction:column}}
        /* Modal */
        .modal-client-list{max-height:300px;overflow-y:auto}
        .client-search-result{padding:.5rem .75rem;cursor:pointer;border-bottom:1px solid var(--border);transition:.2s}
        .client-search-result:hover{background:var(--primary);color:white}
        .client-search-result.selected{background:var(--primary);color:white}
        .client-search-result .client-name{font-weight:600;font-size:.85rem}
        .client-search-result .client-id{font-size:.75rem;color:var(--secondary)}
        .client-search-result:hover .client-id{color:rgba(255,255,255,.7)}
    </style>
</head>
<body id="page-top">
<div id="wrapper">
    <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar">
        <?php require('../entorno/menu.php'); ?>
    </ul>
    <div id="content-wrapper" class="d-flex flex-column">
        <div id="content">
            <nav class="navbar navbar-expand navbar-light bg-white topbar mb-3 static-top shadow">
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

            <div class="container-fluid">
                <!-- Cabecera -->
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div>
                        <h5 class="mb-0"><i class="fas fa-shopping-cart text-primary mr-2"></i>Punto de Venta Rápido</h5>
                        <small class="text-muted"><?= htmlspecialchars($empresa['nombre_comercial']) ?> | <?= $pto_emi['establecimiento'] ?>-<?= $pto_emi['punto_emision'] ?></small>
                    </div>
                    <div>
                        <span class="badge badge-primary badge-pill p-2">
                            <i class="fas fa-shopping-cart"></i> <span id="cart-count-top"><?= $carrito_count ?></span> items - $<span id="cart-total-top"><?= number_format($carrito_subtotal,2) ?></span>
                        </span>
                    </div>
                </div>

                <!-- Layout -->
                <div class="main-layout">
                    <!-- IZQUIERDA: CATEGORÍAS -->
                    <!-- CENTRO: PRODUCTOS -->
                    <div class="center-column">
                        <div class="products-panel">
                            <div class="search-bar">
                                <input type="text" id="search-input" value="<?= htmlspecialchars($busqueda) ?>" placeholder="🔍 Buscar producto...">
                            </div>
                            <div style="font-size:.75rem;color:var(--secondary);margin-bottom:.75rem">
                                <i class="fas fa-boxes mr-1"></i> <?= count($productos) ?> productos
                            </div>
                            <div class="prod-grid">
                                <?php if (count($productos) > 0): ?>
                                <?php foreach ($productos as $p):
                                    $stock_class = $p['stock'] <= 0 ? 'out' : ($p['stock'] <= 5 ? 'low' : 'ok');
                                ?>
                                <div class="prod-card">
                                    <div class="prod-img">
                                        <img src="<?= !empty($p['foto']) ? $p['foto'] : 'https://via.placeholder.com/200x120/ccc/fff?text=📦' ?>" alt="<?= htmlspecialchars($p['nombre']) ?>">
                                    </div>
                                    <div class="prod-body">
                                        
                                        <div class="prod-name"><?= htmlspecialchars($p['nombre']) ?></div>
                                        <div class="prod-price">$<?= number_format($p['precio_unitario'],2) ?></div>
                                        <div class="prod-stock <?= $stock_class ?>">
                                            <?php if ($p['stock'] <= 0): ?>❌ Agotado
                                            <?php elseif ($p['stock'] <= 5): ?>⚠️ <?= $p['stock'] ?> uds.
                                            <?php else: ?>✅ <?= $p['stock'] ?> uds.<?php endif; ?>
                                        </div>
                                        <div class="prod-actions">
                                            <div class="qty-group">
                                                <button class="qty-dec" data-id="<?= $p['id'] ?>">-</button>
                                                <input type="text" class="qty-val" value="1" data-id="<?= $p['id'] ?>" readonly>
                                                <button class="qty-inc" data-id="<?= $p['id'] ?>">+</button>
                                            </div>
                                            <button class="btn-add add-cart" data-id="<?= $p['id'] ?>" data-name="<?= htmlspecialchars($p['nombre']) ?>">
                                                <i class="fas fa-cart-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="text-center py-4" style="grid-column:1/-1">
                                    <i class="fas fa-box-open fa-2x text-muted mb-2"></i>
                                    <p class="text-muted">No hay productos</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- DERECHA: CARRITO -->
                    <div class="right-column">
                        <div class="cart-panel">
                            <div class="cart-header">
                                <h6><span><i class="fas fa-shopping-cart mr-1"></i>Carrito</span><span class="cart-count" id="cart-count-badge"><?= $carrito_count ?> items</span></h6>
                            </div>
                            <div class="cart-items" id="cart-items">
                                <?php if (count($carrito) > 0): ?>
                                <?php foreach ($carrito as $item): ?>
                                <div class="cart-item" data-id="<?= $item['id'] ?>">
                                    <div class="cart-item-name"><?= htmlspecialchars($item['nombre']) ?></div>
                                    <div class="d-flex justify-content-between">
                                        <span class="cart-item-price">$<?= number_format($item['precio_unitario'],2) ?></span>
                                        <span style="font-weight:600;font-size:.8rem">$<?= number_format($item['subtotal'],2) ?></span>
                                    </div>
                                    <div class="cart-item-controls">
                                        <div class="cart-qty">
                                            <button class="cart-dec" data-id="<?= $item['id'] ?>">−</button>
                                            <input type="text" class="cart-qty-input" value="<?= $item['cantidad'] ?>" data-id="<?= $item['id'] ?>" readonly>
                                            <button class="cart-inc" data-id="<?= $item['id'] ?>">+</button>
                                        </div>
                                        <button class="cart-remove" data-id="<?= $item['id'] ?>"><i class="fas fa-trash-alt"></i></button>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="cart-empty">
                                    <i class="fas fa-shopping-cart"></i>
                                    <p class="mb-1">Carrito vacío</p>
                                    <small class="text-muted">Agrega productos</small>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="cart-opts">
                                <div class="opt-row">
                                    <span><i class="fas fa-receipt text-primary mr-1"></i>Factura con IVA</span>
                                    <label class="switch"><input type="checkbox" id="opt-factura" <?= $opciones_factura ? 'checked' : '' ?>><span class="slider"></span></label>
                                </div>
                                <div class="opt-row">
                                    <span><i class="fas fa-truck text-primary mr-1"></i>Envío (+$5)</span>
                                    <label class="switch"><input type="checkbox" id="opt-envio" <?= $opciones_envio ? 'checked' : '' ?>><span class="slider"></span></label>
                                </div>
                            </div>

                            <div class="cart-summary">
                                <div class="sum-row"><span>Subtotal:</span><span id="sum-subtotal">$<?= number_format($carrito_subtotal,2) ?></span></div>
                                <div class="sum-row <?= !$opciones_factura ? 'd-none' : '' ?>" id="sum-iva-row"><span>IVA (<?= $iva_porcentaje ?>%):</span><span id="sum-iva">$<?= number_format($iva,2) ?></span></div>
                                <div class="sum-row <?= !$opciones_envio ? 'd-none' : '' ?>" id="sum-envio-row"><span>Envío:</span><span id="sum-envio">$<?= number_format($costo_envio,2) ?></span></div>
                                <div class="sum-row sum-total"><span>TOTAL:</span><span id="sum-total">$<?= number_format($total_carrito,2) ?></span></div>
                                <button class="btn-checkout" id="btn-checkout" <?= empty($carrito) ? 'disabled' : '' ?>>
                                    <i class="fas fa-credit-card"></i> Finalizar Venta
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php require('../entorno/footer.php'); ?>
    </div>
</div>

<!-- MODAL CLIENTE -->
<div class="modal fade" id="modalCliente" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h6 class="modal-title"><i class="fas fa-user mr-1"></i> Seleccionar Cliente</h6>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-7">
                        <div class="form-group">
                            <label>Buscar cliente</label>
                            <input type="text" id="search-cliente" class="form-control" placeholder="Nombre, RUC o cédula...">
                        </div>
                        <div class="modal-client-list" id="client-list">
                            <p class="text-muted text-center py-3">Escribe para buscar...</p>
                        </div>
                    </div>
                    <div class="col-md-5">
                        <h6 class="font-weight-bold">O crear nuevo</h6>
                        <form id="form-cliente-nuevo">
                            <div class="form-group"><label>Razón Social</label><input type="text" name="n_razon_social" class="form-control form-control-sm" required></div>
                            <div class="form-group"><label>Identificación</label><input type="text" name="n_identificacion" class="form-control form-control-sm" required></div>
                            <div class="row">
                                <div class="col-6"><div class="form-group"><label>Tipo ID</label><select name="n_tipo_id" class="form-control form-control-sm">
                                    <option value="2">Cédula</option><option value="1">RUC</option><option value="3">Pasaporte</option>
                                </select></div></div>
                                <div class="col-6"><div class="form-group"><label>Teléfono</label><input type="text" name="n_telefono" class="form-control form-control-sm"></div></div>
                            </div>
                            <div class="form-group"><label>Email</label><input type="email" name="n_email" class="form-control form-control-sm"></div>
                            <div class="form-group"><label>Dirección</label><input type="text" name="n_direccion" class="form-control form-control-sm"></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <form id="form-finalizar" method="POST">
                    <input type="hidden" name="accion" value="finalizar">
                    <input type="hidden" name="cliente_id" id="cliente-id-seleccionado" value="0">
                    <input type="hidden" name="forma_pago_id" value="1">
                    <input type="hidden" name="ajax" value="1">
                </form>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btn-cliente-consumidor" data-id="1"><i class="fas fa-user"></i> Consumidor Final</button>
                <button type="button" class="btn btn-success" id="btn-confirmar-venta" disabled><i class="fas fa-check"></i> Confirmar Venta</button>
            </div>
        </div>
    </div>
</div>

<?php require('../entorno/script.php'); ?>
<script>
// ===== CARRITO: Agregar =====
$(document).on('click', '.add-cart', function() {
    const id = $(this).data('id');
    const qty = $(this).closest('.prod-actions').find('.qty-val').val();
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {accion:'agregar', producto_id:id, cantidad:qty, ajax:'1'}, function() {
        location.reload();
    });
});

// Qty producto
$(document).on('click', '.qty-inc', function() {
    const inp = $(this).siblings('.qty-val'); inp.val(parseInt(inp.val())+1); });
$(document).on('click', '.qty-dec', function() {
    const inp = $(this).siblings('.qty-val'); const v = parseInt(inp.val()); if (v>1) inp.val(v-1); });

// Carrito: actualizar cantidad
$(document).on('click', '.cart-inc', function() {
    const id = $(this).data('id');
    const inp = $(this).siblings('.cart-qty-input');
    const val = parseInt(inp.val())+1;
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {accion:'actualizar', producto_id:id, cantidad:val, ajax:'1'}, function(){ location.reload(); });
});
$(document).on('click', '.cart-dec', function() {
    const id = $(this).data('id');
    const inp = $(this).siblings('.cart-qty-input');
    const val = parseInt(inp.val())-1;
    if (val < 1) { eliminarItem(id); return; }
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {accion:'actualizar', producto_id:id, cantidad:val, ajax:'1'}, function(){ location.reload(); });
});
$(document).on('click', '.cart-remove', function() { eliminarItem($(this).data('id')); });
function eliminarItem(id) {
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {accion:'eliminar', producto_id:id, ajax:'1'}, function(){ location.reload(); });
}

// Opciones
$('#opt-factura, #opt-envio').change(function() {
    const factura = $('#opt-factura').is(':checked') ? 1 : 0;
    const envio = $('#opt-envio').is(':checked') ? 1 : 0;
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {accion:'opciones', factura:factura, envio:envio, ajax:'1'}, function(r) {
        if (r.success) {
            $('#sum-iva').text('$'+r.iva.toFixed(2));
            $('#sum-envio').text('$'+r.costo_envio.toFixed(2));
            $('#sum-total').text('$'+r.total.toFixed(2));
            $('#sum-iva-row').toggleClass('d-none', r.iva==0);
            $('#sum-envio-row').toggleClass('d-none', r.costo_envio==0);
        }
    }, 'json');
});

// Checkout
$('#btn-checkout').click(function() {
    if ($(this).is(':disabled')) return;
    $('#modalCliente').modal('show');
    $('#cliente-id-seleccionado').val(0);
    $('#btn-confirmar-venta').prop('disabled', true);
    $('#client-list').html('<p class="text-muted text-center py-3">Escribe para buscar...</p>');
});

// Buscar cliente
let searchTimer;
$('#search-cliente').on('input', function() {
    clearTimeout(searchTimer);
    const q = $(this).val();
    if (q.length < 2) { $('#client-list').html('<p class="text-muted text-center py-3">Escribe al menos 2 caracteres...</p>'); return; }
    searchTimer = setTimeout(function() {
        $.get('../md_clientes/cliente_buscar.php?q='+encodeURIComponent(q), function(html) {
            $('#client-list').html(html);
        });
    }, 300);
});

// Seleccionar cliente en lista
$(document).on('click', '.client-search-result', function() {
    $('.client-search-result').removeClass('selected');
    $(this).addClass('selected');
    $('#cliente-id-seleccionado').val($(this).data('id'));
    $('#btn-confirmar-venta').prop('disabled', false);
});

// Consumidor Final
$('#btn-cliente-consumidor').click(function() {
    const id = $(this).data('id');
    $('.client-search-result').removeClass('selected');
    $('#cliente-id-seleccionado').val(id);
    $('#btn-confirmar-venta').prop('disabled', false);
});

// Confirmar venta
$('#btn-confirmar-venta').click(function() {
    const cid = $('#cliente-id-seleccionado').val();
    if (!cid || cid == '0') { alert('Selecciona un cliente o usa Consumidor Final'); return; }
    const fp = $('input[name=forma_pago_id]').val();
    $.post('facturacion_mpos.php?empresa=<?= $empresa_id ?>', {
        accion:'finalizar', cliente_id:cid, forma_pago_id:fp, ajax:'1'
    }, function(r) {
        if (r.success) {
            $('#modalCliente').modal('hide');
            window.location.href = 'facturacion_completa.php?id='+r.factura_id;
        } else {
            alert('Error: '+r.error);
        }
    }, 'json');
});
</script>
</body>
</html>
