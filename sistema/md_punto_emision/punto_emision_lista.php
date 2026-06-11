<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

// Obtener todos los puntos de emisión del usuario actual
$sql = "SELECT pe.*, e.nombre_comercial, e.activa
        FROM punto_emision pe
        JOIN empresa e ON pe.empresa_id = e.id
        WHERE e.usuario_id = :usuario_id
        ORDER BY pe.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt->execute();
$puntos_emision = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Lista de Puntos de Emisión</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .card-custom {
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s;
            margin-bottom: 20px;
            border: none;
        }
        .card-custom:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.15);
        }
        .badge-estado {
            font-size: 0.8rem;
            padding: 5px 10px;
            border-radius: 20px;
        }
        .secuencial-item {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 8px 12px;
            margin-bottom: 8px;
        }
        .secuencial-label {
            font-weight: 600;
            color: #6c757d;
        }
        .secuencial-value {
            font-weight: 700;
            color: #495057;
        }
        .action-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .search-container {
            margin-bottom: 20px;
        }
        .punto-emision-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        .view-toggle {
            display: flex;
            gap: 10px;
        }
        .view-toggle-btn {
            border: 1px solid #ddd;
            padding: 5px 10px;
            border-radius: 5px;
            background: white;
            cursor: pointer;
        }
        .view-toggle-btn.active {
            background: #e9ecef;
            border-color: #007bff;
            color: #007bff;
        }
        @media (max-width: 768px) {
            .card-custom {
                margin-bottom: 15px;
            }
            .punto-emision-header {
                flex-direction: column;
                align-items: flex-start;
            }
            .view-toggle {
                margin-top: 10px;
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
                    <div class="punto-emision-header">
                        <h1 class="h3 mb-0 text-gray-800">Puntos de Emisión</h1>
                        <div class="view-toggle">
                            <button class="view-toggle-btn active" id="cardViewBtn">
                                <i class="fas fa-th-large"></i> Vista Tarjetas
                            </button>
                            <button class="view-toggle-btn" id="tableViewBtn">
                                <i class="fas fa-table"></i> Vista Tabla
                            </button>
                        </div>
                    </div>
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <div class="search-container">
                            <div class="input-group">
                                <input type="text" class="form-control" placeholder="Buscar punto de emisión..." id="searchInput">
                                <div class="input-group-append">
                                    <button class="btn btn-primary" type="button">
                                        <i class="fas fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <a href="punto_emision_nuevo.php" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
                            <i class="fas fa-plus fa-sm text-white-50"></i> Nuevo Punto de Emisión
                        </a>
                    </div>
                    <!-- Vista de Tarjetas (Predeterminada) -->
                    <div class="row" id="cardView">
                        <?php if (count($puntos_emision) > 0): ?>
                            <?php foreach ($puntos_emision as $punto): ?>
                            <div class="col-xl-4 col-lg-6 col-md-12">
                                <div class="card card-custom">
                                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($punto['punto_emision']); ?></h5>
                                        <span class="badge badge-estado <?php echo $punto['activa'] == '1' ? 'badge-success' : 'badge-secondary'; ?>">
                                            <?php echo $punto['activa'] == '1' ? 'Activo' : 'Inactivo'; ?>
                                        </span>
                                    </div>
                                    <div class="card-body">
                                        <h6 class="card-subtitle mb-2 text-muted"><?php echo htmlspecialchars($punto['nombre_comercial']); ?></h6>
                                        <p class="card-text"><?php echo htmlspecialchars($punto['descripcion']); ?></p>
                                        <div class="secuenciales-container mt-3">
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Establecimiento:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['establecimiento']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Factura:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_factura']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Nota Crédito:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_nota_credito']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">IVA:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['iva']); ?>%</span>
                                            </div>
                                        </div>
                                        <div class="action-buttons mt-3">
                                            <?php if ($punto['activa'] == '1'): ?>
                                                <a href="punto_emision_editar.php?id=<?php echo $punto['id']; ?>" class="btn btn-warning btn-sm">
                                                    <i class="fas fa-edit"></i> Editar
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-warning btn-sm" disabled>
                                                    <i class="fas fa-edit"></i> Editar (Empresa inactiva)
                                                </button>
                                            <?php endif; ?>
                                            <a href="punto_emision_borrar.php?id=<?php echo $punto['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que deseas eliminar este punto de emisión?')">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </a>
                                            <button class="btn btn-info btn-sm toggle-details" data-target="details-<?php echo $punto['id']; ?>">
                                                <i class="fas fa-ellipsis-h"></i> Más
                                            </button>
                                        </div>
                                        <div class="additional-details mt-3" id="details-<?php echo $punto['id']; ?>" style="display: none;">
                                            <hr>
                                            <h6>Secuenciales Adicionales</h6>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Nota Débito:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_nota_debito']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Comprobante Retención:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_comprobante_retencion']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Liquidación Compra:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_liquidacion_compra']); ?></span>
                                            </div>
                                            <div class="d-flex justify-content-between secuencial-item">
                                                <span class="secuencial-label">Guía Remisión:</span>
                                                <span class="secuencial-value"><?php echo htmlspecialchars($punto['secuencial_guia_remision']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="col-12">
                                <div class="card shadow">
                                    <div class="card-body text-center">
                                        <i class="fas fa-map-marker-alt fa-4x text-gray-300 mb-3"></i>
                                        <h4>No hay puntos de emisión registrados</h4>
                                        <p>Comienza creando tu primer punto de emisión.</p>
                                        <a href="punto_emision_nuevo.php" class="btn btn-primary mt-3">
                                            <i class="fas fa-plus"></i> Crear Punto de Emisión
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- Vista de Tabla (Oculta inicialmente) -->
                    <div class="table-responsive" id="tableView" style="display: none;">
                        <table class="table table-bordered table-hover" id="tabla_puntos_emision">
                            <thead class="thead-light">
                                <tr>
                                    <th>Nombre Comercial</th>
                                    <th>Punto de Emisión</th>
                                    <th>Establecimiento</th>
                                    <th>Sec. Factura</th>
                                    <th>Sec. N/Crédito</th>
                                    <th>IVA</th>
                                    <th>Estado Empresa</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (count($puntos_emision) > 0): ?>
                                    <?php foreach ($puntos_emision as $punto): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($punto['nombre_comercial']); ?></td>
                                            <td><?php echo htmlspecialchars($punto['punto_emision']); ?></td>
                                            <td><?php echo htmlspecialchars($punto['establecimiento']); ?></td>
                                            <td><?php echo htmlspecialchars($punto['secuencial_factura']); ?></td>
                                            <td><?php echo htmlspecialchars($punto['secuencial_nota_credito']); ?></td>
                                            <td><?php echo htmlspecialchars($punto['iva']); ?>%</td>
                                            <td>
                                                <span class="badge <?php echo $punto['activa'] == '1' ? 'badge-success' : 'badge-secondary'; ?>">
                                                    <?php echo $punto['activa'] == '1' ? 'Activo' : 'Inactivo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($punto['activa'] == '1'): ?>
                                                    <a href="punto_emision_editar.php?id=<?php echo $punto['id']; ?>" class="btn btn-warning btn-sm">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-warning btn-sm" disabled>
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <a href="punto_emision_borrar.php?id=<?php echo $punto['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Estás seguro de que deseas eliminar este punto de emisión?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                                <button class="btn btn-info btn-sm toggle-row-details" data-target="row-details-<?php echo $punto['id']; ?>">
                                                    <i class="fas fa-ellipsis-h"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <tr class="detail-row" id="row-details-<?php echo $punto['id']; ?>" style="display: none;">
                                            <td colspan="8">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <strong>Descripción:</strong> <?php echo htmlspecialchars($punto['descripcion']); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Sec. N/Débito:</strong> <?php echo htmlspecialchars($punto['secuencial_nota_debito']); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Sec. Comp. Retención:</strong> <?php echo htmlspecialchars($punto['secuencial_comprobante_retencion']); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Sec. Liquidación Compra:</strong> <?php echo htmlspecialchars($punto['secuencial_liquidacion_compra']); ?>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <strong>Sec. Guía Remisión:</strong> <?php echo htmlspecialchars($punto['secuencial_guia_remision']); ?>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center">
                                            <div class="py-4">
                                                <i class="fas fa-map-marker-alt fa-3x text-gray-300 mb-3"></i>
                                                <p class="mb-0">No hay puntos de emisión registrados</p>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
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
            // Toggle para detalles adicionales en tarjetas
            $('.toggle-details').click(function() {
                var target = $(this).data('target');
                $('#' + target).slideToggle();
            });
            // Toggle para detalles adicionales en tabla
            $('.toggle-row-details').click(function() {
                var target = $(this).data('target');
                $('#' + target).toggle();
            });
            // Búsqueda
            $('#searchInput').on('keyup', function() {
                var value = $(this).val().toLowerCase();
                // Búsqueda en vista de tarjetas
                $('#cardView .col-xl-4').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
                // Búsqueda en vista de tabla
                $('#tableView tbody tr:not(.detail-row)').filter(function() {
                    $(this).toggle($(this).text().toLowerCase().indexOf(value) > -1);
                });
            });
        });
    </script>
</body>
</html>