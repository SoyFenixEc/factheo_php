<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Verificar que la bodega existe Y pertenece al usuario
    $sql = "SELECT * FROM bodegas WHERE id = :id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $bodega = $stmt->fetch();

    if (!$bodega) {
        echo "<script>alert('Bodega no encontrada o no tienes permiso para editarla.'); window.location.href='bodega_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='bodega_lista.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Bodega</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Editar Bodega</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <form action="bodega_actualiza.php" method="POST">
                                <input type="hidden" name="id" value="<?php echo $bodega['id']; ?>">
                                <div class="form-group">
                                    <label>Nombre de la Bodega</label>
                                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($bodega['nombre']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($bodega['direccion']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($bodega['telefono']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Correo Electrónico</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($bodega['email']); ?>" required>
                                </div>
                                <button type="submit" class="btn btn-primary">Actualizar Bodega</button>
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