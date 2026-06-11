<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php'); 

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // Verificar que el punto de emisión pertenece al usuario actual y obtener sus datos
    $sql = "SELECT pe.*, e.nombre_comercial, e.activa 
            FROM punto_emision pe
            JOIN empresa e ON pe.empresa_id = e.id
            WHERE pe.id = :id AND e.usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $punto = $stmt->fetch();

    if (!$punto) {
        echo "<script>alert('Punto de emisión no encontrado o no tienes permiso para editarlo.'); window.location.href='punto_emision_lista.php';</script>";
        exit;
    }

    // Verificar que la empresa esté activa
    if ($punto['activa'] != '1') {
        echo "<script>alert('No puedes editar este punto de emisión porque su empresa está inactiva.'); window.location.href='punto_emision_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='punto_emision_lista.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Punto de Emisión</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Editar Punto de Emisión</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Datos del Punto de Emisión</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="punto_emision_actualiza.php">
                                        <input type="hidden" name="id" value="<?php echo $punto['id']; ?>">
                                        <div class="form-group">
                                            <label>Empresa</label>
                                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($punto['nombre_comercial']); ?>" readonly>
                                            <input type="hidden" name="empresa_id" value="<?php echo $punto['empresa_id']; ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Punto de Emisión</label>
                                            <input type="text" name="punto_emision" class="form-control" value="<?php echo htmlspecialchars($punto['punto_emision']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Establecimiento</label>
                                            <input type="text" name="establecimiento" class="form-control" value="<?php echo htmlspecialchars($punto['establecimiento']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Factura</label>
                                            <input type="number" name="secuencial_factura" class="form-control" min="1" value="<?php echo $punto['secuencial_factura']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Nota de Crédito</label>
                                            <input type="number" name="secuencial_nota_credito" class="form-control" min="1" value="<?php echo $punto['secuencial_nota_credito']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Nota de Débito</label>
                                            <input type="number" name="secuencial_nota_debito" class="form-control" min="1" value="<?php echo $punto['secuencial_nota_debito']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Comprobante de Retención</label>
                                            <input type="number" name="secuencial_comprobante_retencion" class="form-control" min="1" value="<?php echo $punto['secuencial_comprobante_retencion']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Liquidación de Compra</label>
                                            <input type="number" name="secuencial_liquidacion_compra" class="form-control" min="1" value="<?php echo $punto['secuencial_liquidacion_compra']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Secuencial Guía de Remisión</label>
                                            <input type="number" name="secuencial_guia_remision" class="form-control" min="1" value="<?php echo $punto['secuencial_guia_remision']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>IVA (%)</label>
                                            <input type="number" step="0.01" name="iva" class="form-control" min="0" max="100" value="<?php echo $punto['iva']; ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Descripción</label>
                                            <textarea name="descripcion" class="form-control" rows="3" required><?php echo htmlspecialchars($punto['descripcion']); ?></textarea>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Actualizar Punto de Emisión</button>
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