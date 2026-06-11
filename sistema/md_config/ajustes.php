<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Obtener las empresas registradas
$sql_empresas = "SELECT id, nombre_comercial FROM empresa";
$stmt_empresas = $pdo->prepare($sql_empresas);
$stmt_empresas->execute();
$empresas = $stmt_empresas->fetchAll();

// Obtener los ajustes actuales
$sql = "SELECT * FROM ajustes LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$ajustes = $stmt->fetch();

// Si no existen ajustes, insertar valores predeterminados
if (!$ajustes) {
    // Insertar valores predeterminados en la tabla ajustes
    $sql_insert = "INSERT INTO ajustes (iva, api_url, empresa_id, estado_facturacion) 
                   VALUES (12, 'https://api.sri.gob.ec', 1, 1)";
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute();
    
    // Recargar los ajustes después de la inserción
    $stmt->execute();
    $ajustes = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $iva = $_POST['iva'];
    $api_url = $_POST['api_url'];
    $empresa_id = $_POST['empresa_id'];
    $estado_facturacion = isset($_POST['estado_facturacion']) ? 1 : 0;

    // Actualizar los ajustes
    $sql_update = "UPDATE ajustes SET iva = :iva, api_url = :api_url, empresa_id = :empresa_id, estado_facturacion = :estado_facturacion WHERE id = 1";
    $stmt_update = $pdo->prepare($sql_update);
    $stmt_update->execute([
        ':iva' => $iva,
        ':api_url' => $api_url,
        ':empresa_id' => $empresa_id,
        ':estado_facturacion' => $estado_facturacion
    ]);

    // Verificar si la actualización fue exitosa
    if ($stmt_update->rowCount()) {
        echo "<script>alert('Ajustes actualizados exitosamente'); window.location.href = 'ajustes.php';</script>";
    } else {
        echo "<script>alert('No se realizaron cambios o hubo un error'); window.location.href = 'ajustes.php';</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Ajustes de Facturación</title>
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
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Ajustes de Facturación</h1>
                    </div>
                    <form action="ajustes.php" method="POST">
                        <div class="form-group">
                            <label>IVA (%)</label>
                            <input type="number" step="0.01" name="iva" class="form-control" value="<?php echo $ajustes['iva']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>URL de la API del SRI</label>
                            <input type="text" name="api_url" class="form-control" value="<?php echo $ajustes['api_url']; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Seleccionar Empresa</label>
                            <select name="empresa_id" class="form-control" required>
                                <option value="">Seleccione una empresa</option>
                                <?php foreach ($empresas as $empresa): ?>
                                    <option value="<?php echo $empresa['id']; ?>" <?php echo ($empresa['id'] == $ajustes['empresa_id']) ? 'selected' : ''; ?>><?php echo $empresa['nombre_comercial']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Habilitar Facturación Electrónica</label>
                            <input type="checkbox" name="estado_facturacion" <?php echo $ajustes['estado_facturacion'] ? 'checked' : ''; ?>>
                        </div>
                        <button type="submit" class="btn btn-primary">Actualizar Ajustes</button>
                    </form>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>
</body>
</html>
