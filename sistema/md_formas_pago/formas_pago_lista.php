<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener todas las formas de pago
$sql = "SELECT * FROM formas_pago";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$formas_pago = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Gestión de Formas de Pago</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-forma-pago {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-forma-pago:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-forma-pago-header {
            background: linear-gradient(135deg, #36b9cc 0%, #1cc88a 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-forma-pago-body {
            padding: 20px;
        }
        .forma-pago-info {
            margin-bottom: 15px;
        }
        .forma-pago-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .forma-pago-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #36b9cc;
            font-size: 1.1rem;
        }
        .forma-pago-info-content {
            flex: 1;
        }
        .badge-estado {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.75rem;
            padding: 5px 10px;
            border-radius: 20px;
            background-color: rgba(255,255,255,0.2);
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
        .forma-pago-id {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .card-forma-pago {
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
        }
        .table-actions {
            white-space: nowrap;
        }
        .forma-pago-name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
        }
        .forma-pago-desc {
            color: #6e707e;
            font-size: 0.9rem;
            line-height: 1.4;
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
                        <?php require('../entorno/nav_user_dropdown.php'); ?>
                    </ul>
                </nav>

                <div id="dynamic-content" class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Formas de Pago</h1>
                        <a href="formas_pago_nueva.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Forma de Pago
                        </a>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar forma de pago..." id="searchInput">
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

                    <!-- Vista de Tarjetas (Predeterminada) -->
                    <div id="cardView">
                        <div class="row">
                            <?php if (count($formas_pago) > 0): ?>
                                <?php foreach ($formas_pago as $forma): ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-forma-pago">
                                        <div class="card-forma-pago-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($forma['nombre']); ?></h5>
                                            <span class="badge badge-estado">ID: <?php echo $forma['id']; ?></span>
                                        </div>
                                        <div class="card-forma-pago-body">
                                            <div class="forma-pago-info">
                                                <div class="forma-pago-info-item">
                                                    <span class="forma-pago-info-icon">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                    <div class="forma-pago-info-content">
                                                        <strong>Descripción:</strong>
                                                        <p class="mb-0"><?php echo !empty($forma['descripcion']) ? htmlspecialchars($forma['descripcion']) : '<span class="text-muted">Sin descripción</span>'; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="formas_pago_editar.php?id=<?php echo $forma['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="formas_pago_borrar.php?id=<?php echo $forma['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de eliminar esta forma de pago?')">
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
                                            <i class="fas fa-credit-card"></i>
                                            <h4>No hay formas de pago registradas</h4>
                                            <p>Comienza agregando tu primera forma de pago al sistema.</p>
                                            <a href="formas_pago_nueva.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus"></i> Crear Forma de Pago
                                            </a>
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
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Formas de Pago</h6>
                                <span class="badge badge-primary">Total: <?php echo count($formas_pago); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th>Nombre</th>
                                                <th>Descripción</th>
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($formas_pago) > 0): ?>
                                                <?php foreach ($formas_pago as $forma): ?>
                                                    <tr>
                                                        <td><span class="forma-pago-id"><?php echo $forma['id']; ?></span></td>
                                                        <td>
                                                            <div class="forma-pago-name"><?php echo htmlspecialchars($forma['nombre']); ?></div>
                                                        </td>
                                                        <td>
                                                            <div class="forma-pago-desc"><?php echo !empty($forma['descripcion']) ? htmlspecialchars($forma['descripcion']) : '<span class="text-muted">Sin descripción</span>'; ?></div>
                                                        </td>
                                                        <td class="table-actions text-center">
                                                            <a href="formas_pago_editar.php?id=<?php echo $forma['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="formas_pago_borrar.php?id=<?php echo $forma['id']; ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Estás seguro de eliminar esta forma de pago?')">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="4" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-credit-card fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay formas de pago registradas</p>
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