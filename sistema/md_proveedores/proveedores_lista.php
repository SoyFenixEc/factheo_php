<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener el filtro si se ha enviado por POST
$filtro_tipo = $_POST['filtro_tipo'] ?? 'all';

// Construir la consulta SQL base
$sql = "SELECT * FROM proveedores";

// Aplicar filtro si no es 'all'
if ($filtro_tipo !== 'all') {
    if ($filtro_tipo === 'con_ruc') {
        $sql .= " WHERE ruc IS NOT NULL AND ruc != ''";
    } elseif ($filtro_tipo === 'sin_ruc') {
        $sql .= " WHERE ruc IS NULL OR ruc = ''";
    }
}

$sql .= " ORDER BY id DESC";

// Preparar y ejecutar la consulta
$stmt = $pdo->prepare($sql);
$stmt->execute();
$proveedores = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Lista de Proveedores</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-proveedor {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-proveedor:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-proveedor-header {
            background: linear-gradient(135deg, #36b9cc 0%, #2c9faf 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-proveedor-body {
            padding: 20px;
        }
        .proveedor-info {
            margin-bottom: 15px;
        }
        .proveedor-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .proveedor-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #36b9cc;
            font-size: 1.1rem;
        }
        .proveedor-info-content {
            flex: 1;
        }
        .badge-estado {
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
        .proveedor-id {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }
        .proveedor-name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        .proveedor-details {
            color: #6e707e;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .table-actions {
            white-space: nowrap;
        }
        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        .filter-select {
            width: 200px;
        }
        .proveedor-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #36b9cc 0%, #2c9faf 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 15px;
        }
        .proveedor-header-content {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        @media (max-width: 768px) {
            .card-proveedor {
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
            .proveedor-header-content {
                flex-direction: column;
                text-align: center;
            }
            .proveedor-avatar {
                margin-right: 0;
                margin-bottom: 15px;
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
                        <h1 class="h3 mb-0 text-gray-800">Lista de Proveedores</h1>
                        <a href="proveedor_nuevo.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Proveedor
                        </a>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar proveedor, RUC o teléfono..." id="searchInput">
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

                    <!-- Filtros con SELECT y FORM -->
                    <div class="filter-form">
                        <span class="mr-2"><strong>Filtrar por:</strong></span>
                        <form method="POST" class="d-flex align-items-center">
                            <select name="filtro_tipo" class="form-control filter-select mr-2" onchange="this.form.submit()">
                                <option value="all" <?= $filtro_tipo == 'all' ? 'selected' : '' ?>>Todos los proveedores</option>
                                <option value="con_ruc" <?= $filtro_tipo == 'con_ruc' ? 'selected' : '' ?>>Con RUC</option>
                                <option value="sin_ruc" <?= $filtro_tipo == 'sin_ruc' ? 'selected' : '' ?>>Sin RUC</option>
                            </select>
                            <button type="submit" class="btn btn-primary">Filtrar</button>
                        </form>
                    </div>

                    <!-- Vista de Tarjetas (Predeterminada) -->
                    <div id="cardView">
                        <div class="row">
                            <?php if (count($proveedores) > 0): ?>
                                <?php foreach ($proveedores as $proveedor): ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-proveedor">
                                        <div class="card-proveedor-header">
                                            <h5 class="mb-0"><?= htmlspecialchars($proveedor['nombre']) ?></h5>
                                            <span class="badge badge-estado bg-secondary">ID: <?= $proveedor['id'] ?></span>
                                        </div>
                                        <div class="card-proveedor-body">
                                            <div class="proveedor-header-content">
                                                <div class="proveedor-avatar">
                                                    <?= strtoupper(substr($proveedor['nombre'], 0, 1)) ?>
                                                </div>
                                                <div>
                                                    <div class="proveedor-name"><?= htmlspecialchars($proveedor['nombre']) ?></div>
                                                    <div class="proveedor-details">
                                                        <?= !empty($proveedor['ruc']) ? 'RUC: ' . htmlspecialchars($proveedor['ruc']) : 'Sin RUC' ?>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="proveedor-info">
                                                <div class="proveedor-info-item">
                                                    <span class="proveedor-info-icon">
                                                        <i class="fas fa-id-card"></i>
                                                    </span>
                                                    <div class="proveedor-info-content">
                                                        <strong>RUC:</strong>
                                                        <p class="mb-0"><?= !empty($proveedor['ruc']) ? htmlspecialchars($proveedor['ruc']) : '<span class="text-muted">No especificado</span>' ?></p>
                                                    </div>
                                                </div>
                                                <div class="proveedor-info-item">
                                                    <span class="proveedor-info-icon">
                                                        <i class="fas fa-phone"></i>
                                                    </span>
                                                    <div class="proveedor-info-content">
                                                        <strong>Teléfono:</strong>
                                                        <p class="mb-0"><?= !empty($proveedor['telefono']) ? htmlspecialchars($proveedor['telefono']) : '<span class="text-muted">No especificado</span>' ?></p>
                                                    </div>
                                                </div>
                                                <div class="proveedor-info-item">
                                                    <span class="proveedor-info-icon">
                                                        <i class="fas fa-envelope"></i>
                                                    </span>
                                                    <div class="proveedor-info-content">
                                                        <strong>Email:</strong>
                                                        <p class="mb-0"><?= !empty($proveedor['email']) ? htmlspecialchars($proveedor['email']) : '<span class="text-muted">No especificado</span>' ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="proveedores_editar.php?id=<?= $proveedor['id'] ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="proveedores_borrar.php?id=<?= $proveedor['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este proveedor?');">
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
                                            <i class="fas fa-truck"></i>
                                            <h4>No hay proveedores <?= $filtro_tipo != 'all' ? 'con el filtro seleccionado' : 'registrados' ?></h4>
                                            <p><?= $filtro_tipo != 'all' ? 'Intenta con otro filtro o' : 'Comienza agregando tu primer proveedor.' ?></p>
                                            <a href="proveedor_nuevo.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus"></i> Crear Proveedor
                                            </a>
                                            <?php if ($filtro_tipo != 'all'): ?>
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
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Proveedores</h6>
                                <span class="badge badge-primary">Total: <?= count($proveedores) ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th>Nombre</th>
                                                <th>RUC</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($proveedores) > 0): ?>
                                                <?php foreach ($proveedores as $proveedor): ?>
                                                    <tr>
                                                        <td><span class="proveedor-id"><?= $proveedor['id'] ?></span></td>
                                                        <td>
                                                            <div class="proveedor-name"><?= htmlspecialchars($proveedor['nombre']) ?></div>
                                                        </td>
                                                        <td><?= !empty($proveedor['ruc']) ? htmlspecialchars($proveedor['ruc']) : '<span class="text-muted">N/A</span>' ?></td>
                                                        <td><?= !empty($proveedor['telefono']) ? htmlspecialchars($proveedor['telefono']) : '<span class="text-muted">N/A</span>' ?></td>
                                                        <td><?= !empty($proveedor['email']) ? htmlspecialchars($proveedor['email']) : '<span class="text-muted">N/A</span>' ?></td>
                                                        <td class="table-actions text-center">
                                                            <a href="proveedores_editar.php?id=<?= $proveedor['id'] ?>" class="btn btn-warning btn-sm" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="proveedores_borrar.php?id=<?= $proveedor['id'] ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar este proveedor?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-truck fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay proveedores <?= $filtro_tipo != 'all' ? 'con el filtro seleccionado' : 'registrados' ?></p>
                                                            <?php if ($filtro_tipo != 'all'): ?>
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