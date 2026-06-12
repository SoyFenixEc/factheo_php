<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
require('../entorno/funciones.php');
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <?php
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php');
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

        .filters input,
        .filters button {
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
                                    <th>Fecha</th>
                                    <th>Nombre de Usuario</th>
                                    <th>Dirección IP</th>
                                    <th>Agente de Usuario</th>
                                    <th>Expiración</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="sessionTableBody">
                                <?php
                                $sql = "SELECT s.*, u.nombre AS usuario_nombre 
                                        FROM sesiones s
                                        INNER JOIN usuarios u ON s.usuario_id = u.id
                                        ORDER BY s.creado_en DESC
                                        LIMIT 10";
                                $stmt = $pdo->query($sql);
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<tr>";
                                    echo "<td>" . htmlspecialchars($row['creado_en']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['usuario_nombre']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['ip_address']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['user_agent']) . "</td>";
                                    echo "<td>" . htmlspecialchars($row['expiracion']) . "</td>";
                                    echo "<td>
                                            <button class='btn btn-sm btn-info view-btn' data-id='{$row['id']}'><i class='fas fa-eye'></i></button>
                                            <button class='btn btn-sm btn-warning edit-btn' data-id='{$row['id']}'><i class='fas fa-edit'></i></button>
                                            <button class='btn btn-sm btn-danger delete-btn' data-id='{$row['id']}'><i class='fas fa-trash'></i></button>
                                          </td>";
                                    echo "</tr>";
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

                    <!-- Gráfico -->
                    <div class="row mt-5">
                        <div class="col-lg-6">
                            <canvas id="sessionsChart"></canvas>
                        </div>
                    </div>
                </div>
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

            // Inicializar gráfico
            const ctx = document.getElementById('sessionsChart').getContext('2d');
            const sessionsChart = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'],
                    datasets: [{
                        label: 'Sesiones por Mes',
                        data: [12, 19, 3, 5, 2, 3, 10, 15, 9, 11, 6, 4],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.2)',
                            'rgba(54, 162, 235, 0.2)',
                            'rgba(255, 206, 86, 0.2)',
                            'rgba(75, 192, 192, 0.2)',
                            'rgba(153, 102, 255, 0.2)',
                            'rgba(255, 159, 64, 0.2)',
                            'rgba(199, 199, 199, 0.2)',
                            'rgba(83, 102, 255, 0.2)',
                            'rgba(100, 159, 64, 0.2)',
                            'rgba(70, 70, 255, 0.2)',
                            'rgba(90, 90, 90, 0.2)',
                            'rgba(100, 100, 100, 0.2)'
                        ],
                        borderWidth: 1
                    }]
                }
            });
        </script>
</body>

</html>
