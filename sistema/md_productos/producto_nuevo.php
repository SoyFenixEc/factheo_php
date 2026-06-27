<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener las bodegas registradas por el usuario actual
$usuario_id = $_SESSION['usuario_id'];
$sql_bodegas = "SELECT id, nombre FROM bodegas WHERE usuario_id = :usuario_id";
$stmt_bodegas = $pdo->prepare($sql_bodegas);
$stmt_bodegas->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
$stmt_bodegas->execute();
$bodegas = $stmt_bodegas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Nuevo Producto</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Nuevo Producto</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <form action="producto_graba.php" method="POST" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label>Nombre del Producto</label>
                                    <input type="text" name="nombre" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Código</label>
                                    <input type="text" name="codigo" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Precio Unitario</label>
                                    <input type="number" step="0.01" name="precio" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Stock</label>
                                    <input type="number" name="stock" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>Descripción</label>
                                    <textarea name="descripcion" class="form-control" required></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Foto <small class="text-muted">(opcional)</small></label>
                                    <input type="file" name="foto" class="form-control" accept=".jpg,.jpeg,.png,image/jpeg,image/png">
                                </div>

                                <div class="form-group">
                                    <label>Bodega</label>
                                    <select name="bodega_id" class="form-control" required>
                                        <option value="">Seleccione una bodega</option>
                                        <?php foreach ($bodegas as $bodega): ?>
                                            <option value="<?php echo $bodega['id']; ?>"><?php echo $bodega['nombre']; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Guardar Producto</button>
                                <a href="producto_lista.php" class="btn btn-secondary">Cancelar</a>
                            </form>
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