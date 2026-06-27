<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$usuario_id = $_SESSION['usuario_id'];

if (isset($_GET['id'])) {
    $id = $_GET['id'];
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

// Obtener tipos de identificación activos
$stmt_tipos = $pdo->query("SELECT id, nombre FROM tipos_identificacion WHERE activo = 1 AND aplica_cliente = 1 ORDER BY id");
$tipos_identificacion = $stmt_tipos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Editar Cliente</title>
    <?php require('../entorno/link.php'); ?>
    <style>
        .validation-hint { font-size: 0.85rem; margin-top: 4px; }
        .validation-hint.ok { color: #28a745; }
        .validation-hint.error { color: #dc3545; }
    </style>
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
                                    <form method="POST" action="cliente_actualiza.php" id="formCliente">
                                        <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
                                        <div class="form-group">
                                            <label>Razón Social / Nombre Completo</label>
                                            <input type="text" name="razon_social" class="form-control" value="<?php echo htmlspecialchars($cliente['razon_social']); ?>" required maxlength="255">
                                        </div>
                                        <div class="form-group">
                                            <label>Tipo de Identificación</label>
                                            <select name="id_tipos_identificacion" id="id_tipos_identificacion" class="form-control" required>
                                                <option value="">Seleccionar...</option>
                                                <?php foreach ($tipos_identificacion as $tipo): ?>
                                                    <option value="<?php echo $tipo['id']; ?>" <?php echo $cliente['id_tipos_identificacion'] == $tipo['id'] ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($tipo['nombre']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Identificación</label>
                                            <input type="text" name="identificacion" id="identificacion" class="form-control" 
                                                   value="<?php echo htmlspecialchars($cliente['identificacion']); ?>" required maxlength="20">
                                            <div id="val_hint" class="validation-hint"></div>
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" name="direccion" class="form-control" value="<?php echo htmlspecialchars($cliente['direccion']); ?>" maxlength="255">
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" value="<?php echo htmlspecialchars($cliente['telefono']); ?>" maxlength="20">
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($cliente['email']); ?>" maxlength="100">
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
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const tipoSelect = document.getElementById('id_tipos_identificacion');
        const idenInput = document.getElementById('identificacion');
        const hint = document.getElementById('val_hint');

        <?php
        $json_tipos = json_encode($tipos_identificacion);
        echo "const tipos = $json_tipos;";
        ?>

        function validarIdentificacion() {
            const tipoId = tipoSelect.value;
            const iden = idenInput.value.replace(/[^a-zA-Z0-9]/g, '');

            if (!tipoId || !iden) {
                hint.className = 'validation-hint';
                hint.textContent = '';
                return;
            }

            const tipo = tipos.find(t => t.id == tipoId);
            if (!tipo) return;

            if ((tipoId === '1' || tipoId === '2') && !/^\d+$/.test(iden)) {
                hint.className = 'validation-hint error';
                hint.textContent = '⚠ Solo se permiten números';
                return;
            }

            const expectedLen = tipoId === '1' ? 13 : (tipoId === '2' ? 10 : '');
            if (expectedLen) {
                if (iden.length < expectedLen) {
                    hint.className = 'validation-hint';
                    hint.textContent = `⏳ ${iden.length}/${expectedLen} dígitos`;
                } else if (iden.length === expectedLen) {
                    hint.className = 'validation-hint ok';
                    hint.textContent = '✅ Formato correcto';
                } else {
                    hint.className = 'validation-hint error';
                    hint.textContent = `⚠ Demasiados dígitos (máx ${expectedLen})`;
                }
            } else {
                hint.className = 'validation-hint';
                hint.textContent = '📝 Sin validación de formato específica';
            }
        }

        tipoSelect.addEventListener('change', validarIdentificacion);
        idenInput.addEventListener('input', validarIdentificacion);
        // Ejecutar validación inicial si ya hay datos
        validarIdentificacion();
    });
    </script>
</body>
</html>
