<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener el ID del usuario de la sesión
$usuario_id = $_SESSION['usuario_id'];

// Consultar solo las bodegas que pertenecen al usuario actual
$sql = "SELECT * FROM bodegas WHERE usuario_id = :usuario_id ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$bodegas = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Gestión de Bodegas</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-bodega {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-bodega:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .card-bodega-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-bodega-body {
            padding: 20px;
        }
        .bodega-info {
            margin-bottom: 15px;
        }
        .bodega-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
        }
        .bodega-info-icon {
            min-width: 24px;
            margin-right: 10px;
            color: #4e73df;
        }
        .bodega-info-content {
            flex: 1;
        }
        .badge-estado {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.7rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
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
            padding: 8px 15px;
            border-radius: 5px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        .view-toggle-btn.active {
            background: #4e73df;
            border-color: #4e73df;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        .empty-state i {
            font-size: 5rem;
            margin-bottom: 20px;
            color: #dddfeb;
        }
        .card-view {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        @media (max-width: 768px) {
            .card-bodega {
                margin-bottom: 15px;
            }
            .view-toggle {
                flex-direction: column;
                gap: 5px;
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
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Bodegas</h1>
                        <a href="bodega_nuevo.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nueva Bodega
                        </a>
                    </div>

                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar bodega..." id="searchInput">
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
                            <?php if (count($bodegas) > 0): ?>
                                <?php foreach ($bodegas as $bodega): ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-bodega">
                                        <div class="card-bodega-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($bodega['nombre']); ?></h5>
                                            <span class="badge badge-estado badge-success">Activa</span>
                                        </div>
                                        <div class="card-bodega-body">
                                            <div class="bodega-info">
                                                <div class="bodega-info-item">
                                                    <span class="bodega-info-icon">
                                                        <i class="fas fa-map-marker-alt"></i>
                                                    </span>
                                                    <div class="bodega-info-content">
                                                        <strong>Dirección:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($bodega['direccion']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="bodega-info-item">
                                                    <span class="bodega-info-icon">
                                                        <i class="fas fa-phone"></i>
                                                    </span>
                                                    <div class="bodega-info-content">
                                                        <strong>Teléfono:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($bodega['telefono']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="bodega-info-item">
                                                    <span class="bodega-info-icon">
                                                        <i class="fas fa-envelope"></i>
                                                    </span>
                                                    <div class="bodega-info-content">
                                                        <strong>Email:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($bodega['email']); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="bodega_editar.php?id=<?php echo $bodega['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="bodega_borrar.php?id=<?php echo $bodega['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar esta bodega?');">
                                                    <i class="fas fa-trash"></i> Eliminar
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="card empty-state-card">
                                        <div class="card-body empty-state">
                                            <i class="fas fa-warehouse"></i>
                                            <h4>No hay bodegas registradas</h4>
                                            <p>Comienza agregando tu primera bodega al sistema.</p>
                                            <a href="bodega_nuevo.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-plus"></i> Crear Bodega
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
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Bodegas</h6>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>ID</th>
                                                <th>Nombre</th>
                                                <th>Dirección</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th>Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($bodegas) > 0): ?>
                                                <?php foreach ($bodegas as $bodega): ?>
                                                    <tr>
                                                        <td><?php echo $bodega['id']; ?></td>
                                                        <td><?php echo htmlspecialchars($bodega['nombre']); ?></td>
                                                        <td><?php echo htmlspecialchars($bodega['direccion']); ?></td>
                                                        <td><?php echo htmlspecialchars($bodega['telefono']); ?></td>
                                                        <td><?php echo htmlspecialchars($bodega['email']); ?></td>
                                                        <td>
                                                            <a href="bodega_editar.php?id=<?php echo $bodega['id']; ?>" class="btn btn-warning btn-sm">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="bodega_borrar.php?id=<?php echo $bodega['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar esta bodega?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-warehouse fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay bodegas registradas</p>
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