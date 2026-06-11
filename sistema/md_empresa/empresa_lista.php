<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener el ID del usuario actual
$usuario_id = $_SESSION['usuario_id'];

// Consultar solo las empresas del usuario actual
$sql = "SELECT * FROM empresa WHERE usuario_id = :usuario_id ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$empresas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Gestión de Empresas</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-empresa {
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border: none;
            overflow: hidden;
        }
        .card-empresa:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.15);
        }
        .card-empresa-header {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            color: white;
            padding: 15px 20px;
            position: relative;
        }
        .card-empresa-body {
            padding: 20px;
        }
        .empresa-info {
            margin-bottom: 15px;
        }
        .empresa-info-item {
            display: flex;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        .empresa-info-icon {
            min-width: 24px;
            margin-right: 12px;
            color: #4e73df;
            font-size: 1.1rem;
        }
        .empresa-info-content {
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
        .empresa-id {
            background-color: #f8f9fa;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
        }
        .empresa-logo {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            object-fit: cover;
            border: 2px solid #e3e6f0;
            margin-right: 15px;
        }
        .empresa-header-content {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        .empresa-name {
            font-weight: 600;
            color: #5a5c69;
            margin-bottom: 5px;
            font-size: 1.2rem;
        }
        .empresa-razon-social {
            color: #6e707e;
            font-size: 0.9rem;
            line-height: 1.4;
        }
        .table-actions {
            white-space: nowrap;
        }
        .logo-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 10px;
            background: linear-gradient(135deg, #dddfeb 0%, #eaecf4 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #b7b9cc;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        .filter-buttons {
            margin-bottom: 20px;
        }
        .filter-btn {
            margin-right: 8px;
            margin-bottom: 8px;
        }
        @media (max-width: 768px) {
            .card-empresa {
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
            .empresa-header-content {
                flex-direction: column;
                text-align: center;
            }
            .empresa-logo, .logo-placeholder {
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
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Empresas</h1>
                        <a href="empresa_nueva.php" class="btn btn-primary shadow-sm">
                            <i class="fas fa-building fa-sm text-white-50"></i> Nueva Empresa
                        </a>
                    </div>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar empresa..." id="searchInput">
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
                            <?php if (count($empresas) > 0): ?>
                                <?php foreach ($empresas as $empresa): ?>
                                <div class="col-xl-4 col-lg-6 col-md-12 mb-4">
                                    <div class="card card-empresa">
                                        <div class="card-empresa-header">
                                            <h5 class="mb-0"><?php echo htmlspecialchars($empresa['nombre_comercial']); ?></h5>
                                            <span class="badge badge-estado">ID: <?php echo $empresa['id']; ?></span>
                                        </div>
                                        <div class="card-empresa-body">
                                            <div class="empresa-header-content">
                                                <?php if (!empty($empresa['logo'])): ?>
                                                    <img src="logos/<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo" class="empresa-logo">
                                                <?php else: ?>
                                                    <div class="logo-placeholder">
                                                        <i class="fas fa-building"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <div class="empresa-name"><?php echo htmlspecialchars($empresa['nombre_comercial']); ?></div>
                                                    <div class="empresa-razon-social"><?php echo htmlspecialchars($empresa['razon_social']); ?></div>
                                                </div>
                                            </div>
                                            <div class="empresa-info">
                                                <div class="empresa-info-item">
                                                    <span class="empresa-info-icon">
                                                        <i class="fas fa-building"></i>
                                                    </span>
                                                    <div class="empresa-info-content">
                                                        <strong>Razón Social:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($empresa['razon_social']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="empresa-info-item">
                                                    <span class="empresa-info-icon">
                                                        <i class="fas fa-id-card"></i>
                                                    </span>
                                                    <div class="empresa-info-content">
                                                        <strong>RUC:</strong>
                                                        <p class="mb-0"><?php echo htmlspecialchars($empresa['ruc']); ?></p>
                                                    </div>
                                                </div>
                                                <div class="empresa-info-item">
                                                    <span class="empresa-info-icon">
                                                        <i class="fas fa-phone"></i>
                                                    </span>
                                                    <div class="empresa-info-content">
                                                        <strong>Teléfono:</strong>
                                                        <p class="mb-0"><?php echo !empty($empresa['telefono']) ? htmlspecialchars($empresa['telefono']) : '<span class="text-muted">No especificado</span>'; ?></p>
                                                    </div>
                                                </div>
                                                <div class="empresa-info-item">
                                                    <span class="empresa-info-icon">
                                                        <i class="fas fa-envelope"></i>
                                                    </span>
                                                    <div class="empresa-info-content">
                                                        <strong>Email:</strong>
                                                        <p class="mb-0"><?php echo !empty($empresa['email']) ? htmlspecialchars($empresa['email']) : '<span class="text-muted">No especificado</span>'; ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="action-buttons d-flex justify-content-end">
                                                <a href="empresa_editar.php?id=<?php echo $empresa['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                                <a href="empresa_borrar.php?id=<?php echo $empresa['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Está seguro de eliminar esta empresa?');">
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
                                            <i class="fas fa-building"></i>
                                            <h4>No hay empresas registradas</h4>
                                            <p>Comienza agregando tu primera empresa al sistema.</p>
                                            <a href="empresa_nueva.php" class="btn btn-primary mt-3">
                                                <i class="fas fa-building"></i> Crear Empresa
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
                                <h6 class="m-0 font-weight-bold text-primary">Lista de Empresas</h6>
                                <span class="badge badge-primary">Total: <?php echo count($empresas); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th width="80">ID</th>
                                                <th>Nombre Comercial</th>
                                                <th>Razón Social</th>
                                                <th>RUC</th>
                                                <th>Teléfono</th>
                                                <th>Email</th>
                                                <th>Logo</th>
                                                <th width="150" class="text-center">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($empresas) > 0): ?>
                                                <?php foreach ($empresas as $empresa): ?>
                                                    <tr>
                                                        <td><span class="empresa-id"><?php echo $empresa['id']; ?></span></td>
                                                        <td>
                                                            <div class="empresa-name"><?php echo htmlspecialchars($empresa['nombre_comercial']); ?></div>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($empresa['razon_social']); ?></td>
                                                        <td><?php echo htmlspecialchars($empresa['ruc']); ?></td>
                                                        <td><?php echo !empty($empresa['telefono']) ? htmlspecialchars($empresa['telefono']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td><?php echo !empty($empresa['email']) ? htmlspecialchars($empresa['email']) : '<span class="text-muted">N/A</span>'; ?></td>
                                                        <td>
                                                            <?php if (!empty($empresa['logo'])): ?>
                                                                <img src="logos/<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo" style="max-width: 60px; border-radius: 5px;">
                                                            <?php else: ?>
                                                                <div class="logo-placeholder" style="width: 60px; height: 60px; font-size: 1.2rem;">
                                                                    <i class="fas fa-building"></i>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="table-actions text-center">
                                                            <a href="empresa_editar.php?id=<?php echo $empresa['id']; ?>" class="btn btn-warning btn-sm" title="Editar">
                                                                <i class="fas fa-edit"></i>
                                                            </a>
                                                            <a href="empresa_borrar.php?id=<?php echo $empresa['id']; ?>" class="btn btn-danger btn-sm" title="Eliminar" onclick="return confirm('¿Está seguro de eliminar esta empresa?');">
                                                                <i class="fas fa-trash"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">
                                                        <div class="py-4">
                                                            <i class="fas fa-building fa-3x text-gray-300 mb-3"></i>
                                                            <p class="mb-0">No hay empresas registradas</p>
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