<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $razon_social = $_POST['razon_social'];
    $identificacion = $_POST['identificacion'];
    $id_tipos_identificacion = $_POST['id_tipos_identificacion'];
    $direccion = $_POST['direccion'];
    $telefono = $_POST['telefono'];
    $email = $_POST['email'];

    try {
        $sql = "INSERT INTO clientes (razon_social, identificacion, direccion, telefono, email, id_tipos_identificacion, usuario_id) 
                VALUES (:razon_social, :identificacion, :direccion, :telefono, :email, :id_tipos_identificacion, :usuario_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':razon_social' => $razon_social,
            ':identificacion' => $identificacion,
            ':direccion' => $direccion,
            ':telefono' => $telefono,
            ':email' => $email,
            ':id_tipos_identificacion' => $id_tipos_identificacion,
            ':usuario_id' => $usuario_id
        ]);
        echo "<script>alert('Cliente guardado exitosamente.'); window.location.href='cliente_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al guardar el cliente: " . $e->getMessage() . "'); window.location.href='cliente_nuevo.php';</script>";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Nuevo Cliente</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Nuevo Cliente</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Datos del Cliente</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="form-group">
                                            <label>Razón Social / Nombre Completo</label>
                                            <input type="text" name="razon_social" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Tipo de Identificación</label>
                                            <select name="id_tipos_identificacion" class="form-control" required>
                                                <option value="">Seleccionar...</option>
                                                <option value="1">Cédula</option>
                                                <option value="2">RUC</option>
                                                <option value="3">Pasaporte</option>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Identificación</label>
                                            <input type="number" name="identificacion" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" name="direccion" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" required>
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                        <button type="submit" class="btn btn-primary">Guardar Cliente</button>
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