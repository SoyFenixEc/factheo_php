<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar si se ha recibido el ID de la forma de pago
if (isset($_GET['id'])) {
    $id = $_GET['id'];

    // Obtener la forma de pago a editar
    $sql = "SELECT * FROM formas_pago WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $forma_pago = $stmt->fetch();

    if (!$forma_pago) {
        echo "<script>alert('Forma de pago no encontrada'); window.location.href='formas_pago_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='formas_pago_lista.php';</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Forma de Pago</title>
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
                    <h1 class="h3 mb-4 text-gray-800">Editar Forma de Pago</h1>

                    <!-- Formulario para editar forma de pago -->
                    <form action="formas_pago_actualiza.php" method="POST">
                        <input type="hidden" name="id" value="<?php echo $forma_pago['id']; ?>">
                        <div class="form-group">
                            <label for="nombre">Nombre</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo $forma_pago['nombre']; ?>" required>
                        </div>

                        <div class="form-group">
                            <label for="descripcion">Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="3"><?php echo $forma_pago['descripcion']; ?></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Actualizar Forma de Pago</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>
</body>
</html>
