<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['id'];

// Obtener empresas del usuario sin suscripción activa
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = ? AND suscripcion_activa = 0";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->execute([$usuario_id]);
$empresas = $stmt_empresas->fetchAll();

// Obtener planes disponibles
$sql_planes = "SELECT * FROM planes WHERE activo = 1 ORDER BY duracion_meses ASC";
$stmt_planes = $pdo->query($sql_planes);
$planes = $stmt_planes->fetchAll();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Suscripción - Sistema Contable</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .plan-card {
            border: 1px solid #e3e6f0;
            border-radius: 15px;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        .plan-card.recomendado {
            border: 3px solid #4e73df;
            transform: scale(1.02);
        }
        .plan-header {
            background: linear-gradient(45deg, #4e73df, #224abe);
            color: white;
            padding: 25px;
            border-radius: 15px 15px 0 0;
            text-align: center;
        }
        .plan-price {
            font-size: 2.8rem;
            font-weight: bold;
        }
        .ahorro-badge {
            background: #28a745;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-left: 10px;
        }
        .plan-features {
            padding: 25px;
        }
        .feature-item {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }
        .feature-item i {
            color: #4e73df;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        .btn-subscribe {
            background: linear-gradient(45deg, #4e73df, #224abe);
            border: none;
            padding: 15px 35px;
            font-weight: bold;
            font-size: 1.1rem;
            border-radius: 25px;
            margin: 20px;
            transition: all 0.3s;
        }
        .btn-subscribe:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 115, 223, 0.3);
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
            <h1 class="h3 mb-0 text-gray-800">Suscripciones</h1>
        </div>

        <?php if (empty($empresas)): ?>
            <div class="alert alert-info">
                <h4>No tienes empresas disponibles para suscribir</h4>
                <p>Todas tus empresas ya tienen una suscripción activa.</p>
                <a href="../md_empresa/empresa_nueva.php" class="btn btn-primary">Crear Nueva Empresa</a>
            </div>
        <?php else: ?>

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h5 class="m-0">Seleccionar Empresa</h5>
                    </div>
                    <div class="card-body">
                        <form id="formSeleccion">
                            <div class="form-group">
                                <label>Empresa a suscribir:</label>
                                <select class="form-control" id="empresa_id" required>
                                    <option value="">Seleccionar empresa...</option>
                                    <?php foreach ($empresas as $empresa): ?>
                                        <option value="<?= $empresa['id'] ?>"><?= htmlspecialchars($empresa['nombre_comercial']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="row" id="planes-container">
                    <?php foreach ($planes as $index => $plan): ?>
                    <div class="col-md-6 mb-4">
                        <div class="plan-card shadow <?= $plan['duracion_meses'] == 12 ? 'recomendado' : '' ?>">
                            <div class="plan-header">
                                <h4><?= htmlspecialchars($plan['nombre']) ?></h4>
                                <div class="plan-price">
                                    $<?= number_format($plan['precio_total'], 2) ?>
                                    <?php if ($plan['ahorro_porcentaje'] > 0): ?>
                                        <span class="ahorro-badge">Ahorras <?= $plan['ahorro_porcentaje'] ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <small><?= $plan['duracion_meses'] ?> meses</small>
                            </div>
                            <div class="plan-features">
                                <div class="feature-item">
                                    <i class="fas fa-building"></i>
                                    <?= $plan['max_empresas'] ?> Empresa(s)
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-users"></i>
                                    <?= $plan['max_usuarios'] ?> Usuario(s)
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-file-invoice"></i>
                                    <?= $plan['max_facturas_mes'] ?> Facturas/mes
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-calendar"></i>
                                    <?= $plan['duracion_dias'] ?> Días de acceso
                                </div>
                                <div class="feature-item">
                                    <i class="fas fa-dollar-sign"></i>
                                    $<?= number_format($plan['precio_mensual'], 2) ?> por mes
                                </div>
                            </div>
                            <div class="text-center">
                                <button class="btn btn-subscribe" 
                                        onclick="seleccionarPlan(<?= $plan['id'] ?>, '<?= htmlspecialchars($plan['nombre']) ?>', <?= $plan['precio_total'] ?>)">
                                    Suscribirse
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Modal de Método de Pago -->
        <div class="modal fade" id="modalPago" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Completar Suscripción</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <form id="formPago" action="suscripciones_procesar.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="empresa_id" id="empresa_seleccionada">
                            <input type="hidden" name="plan_id" id="plan_seleccionado">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Plan seleccionado:</label>
                                        <input type="text" class="form-control" id="plan_nombre" readonly>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label>Precio Total:</label>
                                        <input type="text" class="form-control" id="plan_precio" readonly>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group">
                                <label>Método de Pago:</label>
                                <select class="form-control" name="metodo_pago" id="metodo_pago" required onchange="toggleComprobante()">
                                    <option value="transferencia">Transferencia Bancaria</option>
                                    <option value="payphone">PayPhone (Tarjeta) +5%</option>
                                </select>
                            </div>

                            <div class="form-group" id="comprobante-group">
                                <label>Comprobante de Pago:</label>
                                <input type="file" class="form-control-file" name="comprobante" accept=".pdf,.jpg,.jpeg,.png">
                                <small class="form-text text-muted">Suba su comprobante de transferencia</small>
                            </div>

                            <div class="alert alert-info" id="payphone-info" style="display: none;">
                                <strong>PayPhone:</strong> Se aplicará una comisión del 5% + IVA. El monto final a pagar será: 
                                <span id="monto-final"></span>
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-primary" onclick="confirmarPago()">Confirmar Pago</button>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>
    </div>

    <?php require('../entorno/script.php'); ?>
    <script>
    let planPrecio = 0;
    
    function seleccionarPlan(planId, planNombre, planPrecioTotal) {
        const empresaId = document.getElementById('empresa_id').value;
        
        if (!empresaId) {
            Swal.fire('Error', 'Por favor selecciona una empresa primero', 'error');
            return;
        }

        planPrecio = planPrecioTotal;
        
        // Llenar el modal
        document.getElementById('empresa_seleccionada').value = empresaId;
        document.getElementById('plan_seleccionado').value = planId;
        document.getElementById('plan_nombre').value = planNombre;
        document.getElementById('plan_precio').value = '$' + planPrecioTotal.toFixed(2);
        
        // Actualizar monto PayPhone
        actualizarMontoPayPhone();
        
        // Mostrar modal
        $('#modalPago').modal('show');
    }

    function toggleComprobante() {
        const metodo = document.getElementById('metodo_pago').value;
        const comprobanteGroup = document.getElementById('comprobante-group');
        const payphoneInfo = document.getElementById('payphone-info');
        
        if (metodo === 'transferencia') {
            comprobanteGroup.style.display = 'block';
            payphoneInfo.style.display = 'none';
        } else {
            comprobanteGroup.style.display = 'none';
            payphoneInfo.style.display = 'block';
            actualizarMontoPayPhone();
        }
    }

    function actualizarMontoPayPhone() {
        const montoConComision = calcularMontoConComision(planPrecio);
        document.getElementById('monto-final').textContent = '$' + montoConComision.toFixed(2);
    }

    function calcularMontoConComision(monto) {
        const comision = 0.05; // 5%
        const iva = 0.15; // 15%
        const comisionConIva = comision * (1 + iva);
        return monto * (1 + comisionConIva);
    }

    function confirmarPago() {
        const metodo = document.getElementById('metodo_pago').value;
        
        if (metodo === 'transferencia' && !document.querySelector('input[name="comprobante"]').files[0]) {
            Swal.fire('Error', 'Debe subir un comprobante de pago', 'error');
            return;
        }

        document.getElementById('formPago').submit();
    }

    // Inicializar
    document.addEventListener('DOMContentLoaded', function() {
        toggleComprobante();
    });
    </script>
</body>
</html>