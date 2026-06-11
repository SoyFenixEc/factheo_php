<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener el ID del usuario actual
$usuario_id = $_SESSION['usuario_id'];

// Consultar solo los clientes del usuario actual
$sql = "SELECT * FROM clientes WHERE usuario_id = :usuario_id ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Gestión de Clientes</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-cliente {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-cliente:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-cliente-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-cliente-body {
            padding: 20px;
        }
        .cliente-info {
            margin-bottom: 15px;
        }
        .cliente-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .cliente-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #4e73df;
            font-size: 1.1rem;
        }
        .cliente-info-content {
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
        .cliente-id {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }
        .cliente-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
            margin-right: 15px;
        }
        .cliente-header-content {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .cliente-name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        .cliente-details {
            color: #6e707e;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .table-actions {
            white-space: nowrap;
        }
        .filter-buttons {
            margin-bottom: 20px;
        }
        .filter-btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .card-cliente {
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
            .cliente-header-content {
                flex-direction: column;
                text-align: center;
            }
            .cliente-avatar {
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
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Clientes</h1>
                        <a href="cliente_nuevo.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-user-plus fa-sm text-white-50"></i> Nuevo Cliente
                        </a>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar cliente..." id="searchInput">
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
                    <!-- Filtros rápidos -->
                    <div class="filter-buttons mb-4">
                        <button class="btn btn-outline-primary filter-btn active" data-filter="all">Todos</button>
                        <button class="btn btn-outline-secondary filter-btn" data-filter="ruc">Con RUC</button>
                        <button class="btn btn-outline-secondary filter-btn" data-filter="cedula">Con Cédula</button>
                    </div>
                    <!-- Vista de Tarjetas (Predeterminada) -->
                    <div id="cardView">
                        <div class="row">
                            <?php if (count($clientes) > 0): ?>
                                <?php foreach ($clientes as $cliente): ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-cliente">
                                        <div class="card-cliente-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($cliente['razon_social']); ?></h5>
                                            <span class="badge badge-estado">ID: <?php echo $cliente['id']; ?></span>
                                        </div>
                                        <div class="card-cliente-body">
                                            <div class="cliente-header-content">
                                                <div class="cliente-avatar">
                                                    <?php echo strtoupper(substr($cliente['razon_social'], 0, 1)); ?>
                                                </div>
                                                <div>
                                                    <div class="cliente-name"><?php echo htmlspecialchars($cliente['razon_social']); ?></div>
                                                    <div class="cliente-details">
                                                        <?php echo htmlspecialchars($cliente['identificacion']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="cliente-info">
                                                <div class="cliente-info-item">
                                                    <span class="cliente-info-icon">
                                                        <i class="fas fa-id-card"></i>
                                                    </span>
                                                    <div class="cliente-info-content">
                                                        <strong>Identificación:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($cliente['identificacion']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="cliente-info-item">
                                                    <span class="cliente-info-icon">
                                                        <i class="fas fa-phone"></i>
                                                    </span>
                                                    <div class="cliente-info-content">
                                                        <strong>Teléfono:</strong>
                                                        <p class="mb-0"><?php echo !empty($cliente['telefono']) ? htmlspecialchars($cliente['telefono']) : '<span class="text-muted">No especificado</span>'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="cliente-info-item">
                                                    <span class="cliente-info-icon">
                                                        <i class="fas fa-envelope"></i>
                                                    </span>
                                                    <div class="cliente-info-content">
                                                        <strong>Email:</strong>
                                                        <p class="mb-0"><?php echo !empty($cliente['email']) ? htmlspecialchars($cliente['email']) : '<span class="text-muted">No especificado</span>'; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="cliente_editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="cliente_borrar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar este cliente?');">
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
                                            <i class="fas fa-users"></i>
                                            <h4>No hay clientes registrados</h4>
                                            <p>Comienza agregando tu primer cliente al sistema.</p>
                                            <a href="cliente_nuevo.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-user-plus"></i> Crear Cliente
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
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Clientes</h6>
                                <span class="badge badge-primary">Total: <?php echo count($clientes); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th>Nombre/Razón Social</th>
                                                <th>RUC/Cédula</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($clientes) > 0): ?>
                                                <?php foreach ($clientes as $cliente): ?>
                                                    <tr>
                                                        <td><span class="cliente-id"><?php echo $cliente['id']; ?></span></td>
                                                        <td>
                                                            <div class="cliente-name"><?php echo htmlspecialchars($cliente['razon_social']); ?></div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($cliente['identificacion']); ?></td>
                                                        <td><?php echo !empty($cliente['telefono']) ? htmlspecialchars($cliente['telefono']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td><?php echo !empty($cliente['email']) ? htmlspecialchars($cliente['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td class="table-actions text-center">
                                                            <a href="cliente_editar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="cliente_borrar.php?id=<?php echo $cliente['id']; ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar este cliente?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-users fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay clientes registrados</p>
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
            // Filtros rápidos
            $('.filter-btn').click(function() {
                $('.filter-btn').removeClass('active');
                $(this).addClass('active');
                var filter = $(this).data('filter');
                if (filter === 'all') {
                    $('#cardView .col-xl-4, #tableView tbody tr').show();
                } else if (filter === 'ruc') {
                    // Mostrar solo clientes con RUC (asumiendo que RUC tiene 13 dígitos)
                    $('#cardView .col-xl-4, #tableView tbody tr').hide();
                    $('#cardView .col-xl-4:contains("13"), #tableView tbody tr:contains("13")').show();
                } else if (filter === 'cedula') {
                    // Mostrar solo clientes con cédula (asumiendo que cédula tiene 10 dígitos)
                    $('#cardView .col-xl-4, #tableView tbody tr').hide();
                    $('#cardView .col-xl-4:contains("10"), #tableView tbody tr:contains("10")').show();
                }
            });
        });
    </script>
</body>
</html>