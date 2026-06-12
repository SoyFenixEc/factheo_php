<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');
 
$usuario_id = $_SESSION['usuario_id'];

// Obtener las empresas del usuario actual que estén activas
$sql_empresas = "SELECT id, nombre_comercial FROM empresa WHERE usuario_id = :usuario_id AND activa = '1'";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll();

// Si no hay empresas activas, mostrar mensaje y redirigir
if (count($empresas) === 0) {
    echo "<script>alert('No tienes empresas activas. Debes crear una empresa activa antes de poder crear un punto de emisión.'); window.location.href='empresa_lista.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Nuevo Punto de Emisión</title>
    <?php require('../entorno/link.php'); ?>
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
                        <h1 class="h3 mb-0 text-gray-800">Nuevo Punto de Emisión</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Datos del Punto de Emisión</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="punto_emision_graba.php">
                                        <div class="form-group">
                                            <label>Empresa</label>
                                            <select name="empresa_id" class="form-control" required>
                                                <option value="">Seleccionar empresa activa</option>
                                                <?php foreach ($empresas as $empresa): ?>
                                                    <option value="<?php echo $empresa['id']; ?>">
                                                        <?php echo htmlspecialchars($empresa['nombre_comercial']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Punto de Emisión</label>
                                            <input type="text" name="punto_emision" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Establecimiento</label>
                                            <input type="text" name="establecimiento" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Factura</label>
                                            <input type="number" name="secuencial_factura" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Nota de Crédito</label>
                                            <input type="number" name="secuencial_nota_credito" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Nota de Débito</label>
                                            <input type="number" name="secuencial_nota_debito" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Comprobante de Retención</label>
                                            <input type="number" name="secuencial_comprobante_retencion" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Liquidación de Compra</label>
                                            <input type="number" name="secuencial_liquidacion_compra" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Guía de Remisión</label>
                                            <input type="number" name="secuencial_guia_remision" class="form-control" min="1" value="1" required>
                                        </div>
                                        <div class="form-group">
                                            <label>IVA (%)</label>
                                            <input type="number" step="0.01" name="iva" class="form-control" min="0" max="100" value="15" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Descripción</label>
                                            <textarea name="descripcion" class="form-control" rows="3" required></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Guardar Punto de Emisión</button>
                                        <a href="punto_emision_lista.php" class="btn btn-secondary">Cancelar</a>
                                    </form>
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
</body>
</html>