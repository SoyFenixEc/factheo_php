<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// --- Configurar zona horaria de PHP y MySQL --- //
date_default_timezone_set('America/Guayaquil');
$pdo->exec("SET time_zone = '-05:00';"); // GMT-5 (Guayaquil)

// --- Obtener ID_USUARIO desde SESSION --- //
$usuario_id = $_SESSION['usuario_id'] ?? null;

// --- Inicializar estadísticas con valores por defecto --- //
$stats = [
    'semana' => 0,
    'mes' => 0,
    'total' => 0
];

// --- Consulta para estadísticas (con fechas calculadas en PHP) --- //
if ($usuario_id) {
    // Calcular fechas en PHP (precisión horaria)
    $hoy = new DateTime('now', new DateTimeZone('America/Guayaquil'));
    $semana_pasada = (clone $hoy)->sub(new DateInterval('P1W'))->format('Y-m-d H:i:s');
    $mes_pasado = (clone $hoy)->sub(new DateInterval('P1M'))->format('Y-m-d H:i:s');

    $sql_stats = "SELECT 
                    COUNT(CASE WHEN fecha >= ? THEN 1 END) as semana,
                    COUNT(CASE WHEN fecha >= ? THEN 1 END) as mes,
                    COUNT(*) as total
                  FROM auditoria_login 
                  WHERE usuario_id = ?";
    $stmt_stats = $pdo->prepare($sql_stats);
    
    if ($stmt_stats->execute([$semana_pasada, $mes_pasado, $usuario_id])) {
        $result = $stmt_stats->fetch(PDO::FETCH_ASSOC);
        if ($result) {
            $stats = $result;
        }
    } else {
        error_log("Error en consulta SQL: " . print_r($stmt_stats->errorInfo(), true));
    }
}

?>

<!DOCTYPE html>
<html lang="es">

<head>
    <?php
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php');
    require('../entorno/funciones.php');
    require('../entorno/combo_box.php');
    ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        table {
            font-size: 12px;
        }
        .table thead th {
            vertical-align: middle;
            text-align: center;
        }
        .table tbody td {
            vertical-align: middle;
            text-align: center;
            word-wrap: break-word;
            white-space: nowrap;
        }
        .filters {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
        }
        .filters input, .filters button {
            font-size: 12px;
        }
        .btn-export {
            margin-left: 5px;
        }
        .btn-icon {
            background: none;
            border: none;
            cursor: pointer;
        }
        .btn-icon i {
            font-size: 1.5rem;
        }
        .stats-card {
            border-left: .25rem solid #4e73df;
        }
        .stats-card .card-body {
            padding: 1rem;
        }
        .stats-value {
            font-size: 1.5rem;
            font-weight: 700;
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

                <!-- Contenido dinámico -->
                <div id="dynamic-content" class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Gestión de Sesiones</h1>
                        <div>
                            <button class="btn-icon" onclick="exportarExcel()" title="Exportar a Excel">
                                <i class="fas fa-file-excel text-success"></i>
                            </button>
                            <button class="btn-icon" onclick="exportarPDF()" title="Exportar a PDF">
                                <i class="fas fa-file-pdf text-danger"></i>
                            </button>
                        </div>
                    </div>

                    <!-- Tarjetas de estadísticas -->
                    <div class="row mb-4">
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stats-card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Sesiones esta semana</div>
                                            <div class="stats-value mb-0 text-gray-800"><?= $stats['semana'] ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar-week fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stats-card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Sesiones este mes</div>
                                            <div class="stats-value mb-0 text-gray-800"><?= $stats['mes'] ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-calendar-alt fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-4 col-md-6 mb-4">
                            <div class="card stats-card shadow h-100 py-2">
                                <div class="card-body">
                                    <div class="row no-gutters align-items-center">
                                        <div class="col mr-2">
                                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                Total de sesiones</div>
                                            <div class="stats-value mb-0 text-gray-800"><?= $stats['total'] ?></div>
                                        </div>
                                        <div class="col-auto">
                                            <i class="fas fa-history fa-2x text-gray-300"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Filtros y buscador -->
                    <div class="filters">
                        <input type="date" id="startDate" class="form-control form-control-sm" placeholder="Desde">
                        <input type="date" id="endDate" class="form-control form-control-sm" placeholder="Hasta">
                        <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Buscar...">
                        <button type="button" class="btn btn-primary btn-sm" id="applyFilters">Aplicar</button>
                    </div>

                    <!-- Tabla de sesiones -->
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead class="thead-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Fecha</th>
                                    <th>Dirección IP</th>
                                    <th>Dispositivo</th>
                                    <th>Resultado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="sessionTableBody">
                                <?php
                                $sql = "SELECT *
                                        FROM auditoria_login
                                        WHERE usuario_id = ?
                                        ORDER BY fecha DESC
                                        LIMIT 10";
                                $stmt = $pdo->prepare($sql);
                                $stmt->execute([$usuario_id]);
                                $contador = 1;
                                
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    $resultado_class = ($row['resultado'] == 'exitoso') ? 'text-success' : 'text-danger';
                                    ?>
                                    <tr>
                                        <td><?= $contador++ ?></td>
                                        <td><?= htmlspecialchars($row['fecha']) ?></td>
                                        <td><?= htmlspecialchars($row['ip_address']) ?></td>
                                        <td><?= htmlspecialchars(substr($row['user_agent'], 0, 30) . (strlen($row['user_agent']) > 30 ? '...' : '')) ?></td>
                                        <td class="<?= $resultado_class ?>">
                                            <?= htmlspecialchars(ucfirst($row['resultado'])) ?>
                                        </td>
                                        <td>
                                            <button class='btn btn-sm btn-info view-btn' data-id='<?= $row['id'] ?>' title="Ver detalles">
                                                <i class='fas fa-eye'></i>
                                            </button>
                                            <button class='btn btn-sm btn-danger delete-btn' data-id='<?= $row['id'] ?>' title="Eliminar registro">
                                                <i class='fas fa-trash'></i>
                                            </button>
                                        </td>
                                    </tr>
                                    <?php
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Botones de paginación -->
                    <nav aria-label="Page navigation example" class="mt-3">
                        <ul class="pagination justify-content-center">
                            <li class="page-item"><a class="page-link" href="#">Anterior</a></li>
                            <li class="page-item"><a class="page-link" href="#">1</a></li>
                            <li class="page-item"><a class="page-link" href="#">2</a></li>
                            <li class="page-item"><a class="page-link" href="#">3</a></li>
                            <li class="page-item"><a class="page-link" href="#">Siguiente</a></li>
                        </ul>
                    </nav>
                </div>
            </div>
            
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <!-- Scripts -->
    <?php require('../entorno/script.php'); ?>

    <script>
        // Exportar a Excel
        function exportarExcel() {
            location.href = '../entorno/funciones.php?export=excel&tabla=sesiones';
        }

        // Exportar a PDF
        function exportarPDF() {
            location.href = '../entorno/funciones.php?export=pdf&tabla=sesiones';
        }

        // Filtros
        document.getElementById('applyFilters').addEventListener('click', function() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            // Aquí iría la lógica para filtrar los datos
            // Puedes hacer una nueva petición AJAX o filtrar los datos en el cliente
            console.log('Filtrar con:', {startDate, endDate, searchTerm});
        });

        // Botones de acción
        document.querySelectorAll('.view-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                alert('Ver detalles de sesión ID: ' + id);
                // Aquí podrías abrir un modal con más detalles
            });
        });

        document.querySelectorAll('.delete-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const id = this.getAttribute('data-id');
                if(confirm('¿Estás seguro de eliminar este registro de sesión?')) {
                    // Aquí iría la petición AJAX para eliminar
                    console.log('Eliminar sesión ID:', id);
                }
            });
        });
    </script>
</body>
</html>