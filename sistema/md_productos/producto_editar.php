<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Verificar que el producto pertenece al usuario actual
    $sql = "SELECT p.*, b.nombre AS bodega_nombre 
            FROM productos p 
            JOIN bodegas b ON p.bodega_id = b.id 
            WHERE p.id = :id AND p.usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $producto = $stmt->fetch();
    
    if (!$producto) {
        echo "<script>alert('Producto no encontrado o no tienes permiso para editarlo.'); window.location.href='producto_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='producto_lista.php';</script>";
    exit;
}

// Obtener las bodegas registradas por el usuario actual
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
    <title>Editar Producto</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Editar Producto</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <form action="producto_actualiza.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id" value="<?php echo $producto['id']; ?>">
                                <div class="form-group">
                                    <label>Nombre del Producto</label>
                                    <input type="text" name="nombre" class="form-control" value="<?php echo htmlspecialchars($producto['nombre']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Código</label>
                                    <input type="text" name="codigo" class="form-control" value="<?php echo htmlspecialchars($producto['codigo']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Precio Unitario</label>
                                    <input type="number" step="0.01" name="precio" class="form-control" value="<?php echo $producto['precio_unitario']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Stock</label>
                                    <input type="number" name="stock" class="form-control" value="<?php echo $producto['stock']; ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Descripción</label>
                                    <textarea name="descripcion" class="form-control" required><?php echo htmlspecialchars($producto['descripcion']); ?></textarea>
                                </div>
                                <div class="form-group">
                                    <label>Foto</label>
                                    <input type="file" name="foto" class="form-control">
                                    <?php if (!empty($producto['foto'])): ?>
                                        <br><img src="<?php echo htmlspecialchars($producto['foto']); ?>" alt="Producto" style="max-width: 100px;">
                                    <?php endif; ?>
                                </div>
                                <div class="form-group">
                                    <label>Bodega</label>
                                    <select name="bodega_id" class="form-control" required>
                                        <option value="">Seleccione una bodega</option>
                                        <?php foreach ($bodegas as $bodega): ?>
                                            <option value="<?php echo $bodega['id']; ?>" <?php echo ($bodega['id'] == $producto['bodega_id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($bodega['nombre']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn btn-primary">Actualizar Producto</button>
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