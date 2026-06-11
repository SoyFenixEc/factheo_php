<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar si el formulario ha sido enviado
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];

    try {
        // Insertar la nueva forma de pago en la base de datos
        $sql = "INSERT INTO formas_pago (nombre, descripcion) VALUES (:nombre, :descripcion)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);

        // Redirigir a la lista de formas de pago con un mensaje de éxito
        header("Location: formas_pago_lista.php?mensaje=Forma de pago agregada con éxito.");
        exit;

    } catch (PDOException $e) {
        echo "Error al agregar la forma de pago: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Agregar Forma de Pago</title>
    <?php require('../entorno/link.php'); ?>
</head>
<body>
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
                        <?php require('../entorno/nav_user_dropdown.php'); ?>
                    </ul>
                </nav>

                <div id="dynamic-content" class="container-fluid">
                    <h1 class="h3 mb-4 text-gray-800">Agregar Nueva Forma de Pago</h1>

                    <!-- Formulario de nueva forma de pago -->
                    <form action="formas_pago_nueva.php" method="POST">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" required>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Agregar Forma de Pago</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>
</body>
</html>
