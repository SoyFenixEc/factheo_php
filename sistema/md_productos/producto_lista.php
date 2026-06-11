<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener el ID del usuario actual
$usuario_id = $_SESSION['usuario_id'];

// Obtener el filtro de stock si se ha enviado por POST
$filtro_stock = $_POST['filtro_stock'] ?? 'all';

// Construir la consulta SQL base con filtro por usuario
$sql = "SELECT p.*, b.nombre AS bodega_nombre 
        FROM productos p 
        JOIN bodegas b ON p.bodega_id = b.id 
        WHERE p.usuario_id = :usuario_id";

// Aplicar filtro si no es 'all'
if ($filtro_stock !== 'all') {
    if ($filtro_stock === 'bajo') {
        $sql .= " AND p.stock < 10";
    } elseif ($filtro_stock === 'medio') {
        $sql .= " AND p.stock >= 10 AND p.stock < 20";
    } elseif ($filtro_stock === 'alto') {
        $sql .= " AND p.stock >= 20";
    }
}

$sql .= " ORDER BY p.id DESC";

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$productos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Gestión de Productos</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-producto {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
            height: 100%;
        }
        .card-producto:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-producto-header {
            background: linear-gradient(135deg, #1cc88a 0%, #13855c 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-producto-body {
            padding: 20px;
        }
        .producto-info {
            margin-bottom: 15px;
        }
        .producto-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .producto-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #1cc88a;
            font-size: 1.1rem;
        }
        .producto-info-content {
            flex: 1;
        }
        .badge-estado {
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .badge-stock {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .action-buttons .btn {
            margin-right: 8px;
            margin-bottom: 8px;
            border-radius: 6px;
            font-weight: 500;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .view-toggle {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .view-toggle-btn {
            border: 1px solid #ddd;
            padding: 8px 16px;
            border-radius: 6px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        .view-toggle-btn.active {
            background: #4e73df;
            border-color: #4e73df;
            color: white;
        }
        .view-toggle-btn:hover:not(.active) {
            background: #f8f9fa;
        }
        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: #dddfeb;
            opacity: 0.7;
        }
        .card-view {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        .producto-id {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }
        .producto-name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        .producto-details {
            color: #6e707e;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .producto-precio {
            font-size: 1.3rem;
            font-weight: 700;
            color: #2e59d9;
        }
        .producto-stock {
            font-size: 1.1rem;
            font-weight: 600;
        }
        .table-actions {
            white-space: nowrap;
        }
        .producto-imagen {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .imagen-placeholder {
            width: 100%;
            height: 200px;
            border-radius: 8px;
            background: linear-gradient(135deg, #dddfeb 0%, #eaecf4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b7b9cc;
            font-size: 3rem;
            margin-bottom: 15px;
        }
        .filter-buttons {
            margin-bottom: 20px;
        }
        .filter-btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        .stock-bajo {
            color: #e74a3b !important;
        }
        .stock-medio {
            color: #f6c23e !important;
        }
        .stock-alto {
            color: #1cc88a !important;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-select {
            width: 200px;
        }
        @media (max-width: 768px) {
            .card-producto {
                margin-bottom: 15px;
            }
            .view-toggle {
                flex-direction: column;
                gap: 8px;
            }
            .view-toggle-btn {
                width: 100%;
                text-align: center;
            }
            .filter-form {
                flex-direction: column;
                align-items: flex-start;
            }
            .filter-select {
                width: 100%;
            }
        }
    </style>
</head>
<body id="page-top">
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
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Productos</h1>
                        <a href="producto_nuevo.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Producto
                        </a>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar producto, código o bodega..." id="searchInput">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-right">
                            <div class="view-toggle">
                                <button class="view-toggle-btn active" id="cardViewBtn">
                                    <i class="fas fa-th-large"></i> Vista Tarjetas
                                </button>
                                <button class="view-toggle-btn" id="tableViewBtn">
                                    <i class="fas fa-table"></i> Vista Tabla
                                </button>
                            </div>
                        </div>
                    </div>
                    <!-- Filtros por stock con SELECT y FORM -->
                    <div class="filter-buttons mb-4">
                        <span class="mr-2"><strong>Filtrar por stock:</strong></span>
                        <form method="POST" class="filter-form">
                            <select name="filtro_stock" class="form-control filter-select" onchange="this.form.submit()">
                                <option value="all" <?= $filtro_stock == 'all' ? 'selected' : '' ?>>Todos los productos</option>
                                <option value="bajo" <?= $filtro_stock == 'bajo' ? 'selected' : '' ?>>Stock Bajo (0-9 unidades)</option>
                                <option value="medio" <?= $filtro_stock == 'medio' ? 'selected' : '' ?>>Stock Medio (10-19 unidades)</option>
                                <option value="alto" <?= $filtro_stock == 'alto' ? 'selected' : '' ?>>Stock Alto (20+ unidades)</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </form>
                    </div>
                    <!-- Vista de Tarjetas (Predeterminada) -->
                    <div id="cardView">
                        <div class="row">
                            <?php if (count($productos) > 0): ?>
                                <?php foreach ($productos as $producto): 
                                    // Determinar clase de stock
                                    $stockClass = '';
                                    if ($producto['stock'] == 0) {
                                        $stockClass = 'stock-bajo';
                                        $stockBadge = 'bg-danger';
                                        $stockText = 'Sin Stock';
                                    } elseif ($producto['stock'] < 10) {
                                        $stockClass = 'stock-bajo';
                                        $stockBadge = 'bg-danger';
                                        $stockText = 'Bajo';
                                    } elseif ($producto['stock'] < 20) {
                                        $stockClass = 'stock-medio';
                                        $stockBadge = 'bg-warning';
                                        $stockText = 'Medio';
                                    } else {
                                        $stockClass = 'stock-alto';
                                        $stockBadge = 'bg-success';
                                        $stockText = 'Alto';
                                    }
                                ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-producto">
                                        <div class="card-producto-header">
                                            <h5 class="mb-0"><?= htmlspecialchars($producto['nombre']) ?></h5>
                                            <span class="badge badge-estado <?= $stockBadge ?>"><?= $stockText ?></span>
                                        </div>
                                        <div class="card-producto-body"> <!--
                                            <?php if (!empty($producto['foto'])): ?>
                                                <img src="<?= htmlspecialchars($producto['foto']) ?>" alt="Producto" class="producto-imagen">
                                            <?php else: ?>
                                                <div class="imagen-placeholder">
                                                    <i class="fas fa-box"></i>
                                                </div>
                                            <?php endif; ?> -->
                                            <div class="producto-info">
                                                <div class="producto-info-item">
                                                    <span class="producto-info-icon">
                                                        <i class="fas fa-barcode"></i>
                                                    </span>
                                                    <div class="producto-info-content">
                                                        <strong>Código:</strong>
                                                        <p class="mb-0"><?= htmlspecialchars($producto['codigo']) ?></p>
                                                    </div>
                                                </div>
                                                <div class="producto-info-item">
                                                    <span class="producto-info-icon">
                                                        <i class="fas fa-tag"></i>
                                                    </span>
                                                    <div class="producto-info-content">
                                                        <strong>Precio:</strong>
                                                        <p class="mb-0 producto-precio">$ <?= number_format($producto['precio_unitario'], 2) ?></p>
                                                    </div>
                                                </div>
                                                <div class="producto-info-item">
                                                    <span class="producto-info-icon">
                                                        <i class="fas fa-boxes"></i>
                                                    </span>
                                                    <div class="producto-info-content">
                                                        <strong>Stock:</strong>
                                                        <p class="mb-0 producto-stock <?= $stockClass ?>"><?= $producto['stock'] ?> unidades</p>
                                                    </div>
                                                </div>
                                                <div class="producto-info-item">
                                                    <span class="producto-info-icon">
                                                        <i class="fas fa-warehouse"></i>
                                                    </span>
                                                    <div class="producto-info-content">
                                                        <strong>Bodega:</strong>
                                                        <p class="mb-0"><?= htmlspecialchars($producto['bodega_nombre']) ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="producto_editar.php?id=<?= $producto['id'] ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="producto_borrar.php?id=<?= $producto['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este producto?');">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card shadow">
                                        <div class="card-body empty-state">
                                            <i class="fas fa-box"></i>
                                            <h4>No hay productos <?= $filtro_stock != 'all' ? 'con el stock seleccionado' : 'registrados' ?></h4>
                                            <p><?= $filtro_stock != 'all' ? 'Intenta con otro filtro o' : 'Comienza agregando tu primer producto al sistema.' ?></p>
                                            <a href="producto_nuevo.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus"></i> Crear Producto
                                            </a>
                                            <?php if ($filtro_stock != 'all'): ?>
                                                <a href="?" class="btn btn-secondary mt-3 ml-2">
                                                    <i class="fas fa-times"></i> Limpiar filtro
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <!-- Vista de Tabla (Oculta inicialmente) -->
                    <div id="tableView" style="display: none;">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Productos</h6>
                                <span class="badge badge-primary">Total: <?= count($productos) ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th>Código</th>
                                                <th>Nombre</th>
                                                <th>Precio</th>
                                                <th>Stock</th>
                                                <th>Bodega</th>
                                                <!--<th>Imagen</th>-->
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($productos) > 0): ?>
                                                <?php foreach ($productos as $producto): 
                                                    // Determinar clase de stock
                                                    if ($producto['stock'] == 0) {
                                                        $stockClass = 'stock-bajo';
                                                        $stockBadge = 'danger';
                                                    } elseif ($producto['stock'] < 10) {
                                                        $stockClass = 'stock-bajo';
                                                        $stockBadge = 'danger';
                                                    } elseif ($producto['stock'] < 20) {
                                                        $stockClass = 'stock-medio';
                                                        $stockBadge = 'warning';
                                                    } else {
                                                        $stockClass = 'stock-alto';
                                                        $stockBadge = 'success';
                                                    }
                                                ?>
                                                <tr>
                                                    <td><span class="producto-id"><?= $producto['id'] ?></span></td>
                                                    <td><?= htmlspecialchars($producto['codigo']) ?></td>
                                                    <td>
                                                        <div class="producto-name"><?= htmlspecialchars($producto['nombre']) ?></div>
                                                    </td>
                                                    <td><strong>$ <?= number_format($producto['precio_unitario'], 2) ?></strong></td>
                                                    <td>
                                                        <span class="badge badge-<?= $stockBadge ?>">
                                                            <?= $producto['stock'] ?> unidades
                                                        </span>
                                                    </td>
                                                    <td><?= htmlspecialchars($producto['bodega_nombre']) ?></td>
													<!--
                                                    <td>
                                                        <?php if (!empty($producto['foto'])): ?>
                                                            <img src="<?= htmlspecialchars($producto['foto']) ?>" alt="Producto" style="max-width: 60px; border-radius: 5px;">
                                                        <?php else: ?>
                                                            <div class="imagen-placeholder" style="width: 60px; height: 60px; font-size: 1.2rem;">
                                                                <i class="fas fa-box"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td> -->
                                                    <td class="table-actions text-center">
                                                        <a href="producto_editar.php?id=<?= $producto['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <a href="producto_borrar.php?id=<?= $producto['id'] ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar este producto?');">
                                                            <i class="fas fa-trash"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-box fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay productos <?= $filtro_stock != 'all' ? 'con el stock seleccionado' : 'registrados' ?></p>
                                                            <?php if ($filtro_stock != 'all'): ?>
                                                                <a href="?" class="btn btn-secondary mt-3">
                                                                    <i class="fas fa-times"></i> Limpiar filtro
                                                                </a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>
    <script>
        $(document).ready(function() {
            // Toggle entre vistas
            $('#cardViewBtn').click(function() {
                $('#cardView').show();
                $('#tableView').hide();
                $(this).addClass('active');
                $('#tableViewBtn').removeClass('active');
            });
            $('#tableViewBtn').click(function() {
                $('#cardView').hide();
                $('#tableView').show();
                $(this).addClass('active');
                $('#cardViewBtn').removeClass('active');
            });
            // Búsqueda en tiempo real
            $('#searchInput').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                // Búsqueda en vista de tarjetas
                $('#cardView .col-xl-4').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
                // Búsqueda en vista de tabla
                $('#tableView tbody tr').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
        });
    </script>
</body>
</html>