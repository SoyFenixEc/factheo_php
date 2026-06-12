<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Verificar que el cliente existe Y pertenece al usuario actual
    $sql = "SELECT * FROM clientes WHERE id = :id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $cliente = $stmt->fetch();

    if (!$cliente) {
        echo "<script>alert('Cliente no encontrado o no tienes permiso para editarlo.'); window.location.href='cliente_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='cliente_lista.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Cliente</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Editar Cliente</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Datos del Cliente</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" action="cliente_actualiza.php">
                                        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                        <div class="form-group">
                                            <label>Razón Social / Nombre Completo</label>
                                            <input type="text" name="razon_social" class="form-control" value="<?php echo htmlspecialchars($cliente['razon_social']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Tipo de Identificación</label>
                                            <select name="id_tipos_identificacion" class="form-control" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="1" <?php echo $cliente['id_tipos_identificacion'] == 1 ? 'selected' : ''; ?>>Cédula</option>
                                                <option value="2" <?php echo $cliente['id_tipos_identificacion'] == 2 ? 'selected' : ''; ?>>RUC</option>
                                                <option value="3" <?php echo $cliente['id_tipos_identificacion'] == 3 ? 'selected' : ''; ?>>Pasaporte</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Identificación</label>
                                            <input type="text" name="identificacion" class="form-control" value="<?php echo htmlspecialchars($cliente['identificacion']); ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($cliente['direccion']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente['telefono']); ?>">
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($cliente['email']); ?>">
                                        </div>
                                        <button type="submit" class="btn btn-primary">Actualizar Cliente</button>
                                        <a href="cliente_lista.php" class="btn btn-secondary">Cancelar</a>
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