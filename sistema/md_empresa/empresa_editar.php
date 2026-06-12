<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    // Verificar que la empresa pertenece al usuario actual
    $sql = "SELECT * FROM empresa WHERE id = :id AND usuario_id = :usuario_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt->execute();
    $empresa = $stmt->fetch();

    if (!$empresa) {
        echo "<script>alert('Empresa no encontrada o no tienes permiso para editarla.'); window.location.href='empresa_lista.php';</script>";
        exit;
    }
} else {
    echo "<script>alert('ID no proporcionado'); window.location.href='empresa_lista.php';</script>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Empresa</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Editar Empresa</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-6">
                            <form action="empresa_actualiza.php" method="POST" enctype="multipart/form-data">
                                <input type="hidden" name="id" value="<?php echo $empresa['id']; ?>">
                                <div class="form-group">
                                    <label>Nombre Comercial</label>
                                    <input type="text" name="nombre_comercial" class="form-control" value="<?php echo htmlspecialchars($empresa['nombre_comercial']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Razón Social</label>
                                    <input type="text" name="razon_social" class="form-control" value="<?php echo htmlspecialchars($empresa['razon_social']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>RUC</label>
                                    <input type="text" name="ruc" class="form-control" value="<?php echo htmlspecialchars($empresa['ruc']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Obligado llevar contabilidad</label>
                                    <input type="text" name="obligado_contabilidad" class="form-control" value="<?php echo htmlspecialchars($empresa['obligado_contabilidad']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($empresa['direccion']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Teléfono</label>
                                    <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($empresa['telefono']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Correo Electrónico</label>
                                    <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($empresa['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Certificado Digital (.p12)</label>
                                    <input type="file" name="certificado" class="form-control">
                                    <small>Dejar en blanco si no desea cambiar el certificado.</small>
                                </div>
                                <div class="form-group">
                                    <label>Clave del Certificado</label>
                                    <input type="password" name="clave_certificado" class="form-control" value="<?php echo htmlspecialchars($empresa['clave_certificado']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label>Logo de la Empresa</label>
                                    <input type="file" name="logo" class="form-control">
                                    <small>Dejar en blanco si no desea cambiar el logo.</small>
                                    <br>
                                    <?php if (!empty($empresa['logo'])): ?>
                                        <img src="logos/<?php echo htmlspecialchars($empresa['logo']); ?>" alt="Logo Empresa" style="max-width: 200px; margin-top: 10px;">
                                    <?php else: ?>
                                        <div class="text-muted" style="margin-top: 10px;">No hay logo asignado</div>
                                    <?php endif; ?>
                                </div>
                                <button type="submit" class="btn btn-primary">Actualizar Empresa</button>
                                <a href="empresa_lista.php" class="btn btn-secondary">Cancelar</a>
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