<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// ===== FUNCIÓN DE VALIDACIÓN =====
function validarIdentificacion($identificacion, $id_tipo, $pdo) {
    // Obtener reglas del tipo de identificación
    $stmt = $pdo->prepare("SELECT codigo, nombre, longitud_min, longitud_max FROM tipos_identificacion WHERE id = ? AND activo = 1");
    $stmt->execute([$id_tipo]);
    $tipo = $stmt->fetch();

    if (!$tipo) {
        return "Tipo de identificación no válido.";
    }

    $iden = preg_replace('/[^a-zA-Z0-9]/', '', $identificacion); // limpiar guiones/espacios
    $len = strlen($iden);
    $codigo = $tipo['codigo'];

    // Validar longitud mínima y máxima
    if ($tipo['longitud_min'] && $len < $tipo['longitud_min']) {
        return "La identificación para {$tipo['nombre']} debe tener al menos {$tipo['longitud_min']} caracteres.";
    }
    if ($tipo['longitud_max'] && $len > $tipo['longitud_max']) {
        return "La identificación para {$tipo['nombre']} debe tener máximo {$tipo['longitud_max']} caracteres.";
    }

    // Validaciones específicas según código SRI
    switch ($codigo) {
        case '04': // RUC
            if (!ctype_digit($iden)) {
                return "El RUC debe contener solo números (13 dígitos).";
            }
            if ($len !== 13) {
                return "El RUC debe tener exactamente 13 dígitos.";
            }
            // Validar dígito verificador del RUC
            if (!validarRucEcuador($iden)) {
                return "El RUC ingresado no es válido (dígito verificador incorrecto).";
            }
            break;

        case '05': // Cédula
            if (!ctype_digit($iden)) {
                return "La cédula debe contener solo números (10 dígitos).";
            }
            if ($len !== 10) {
                return "La cédula debe tener exactamente 10 dígitos.";
            }
            if (!validarCedulaEcuador($iden)) {
                return "La cédula ingresada no es válida (dígito verificador incorrecto).";
            }
            break;

        case '06': // Pasaporte
            // Sin validación específica, solo alfanumérico
            if (!preg_match('/^[a-zA-Z0-9]+$/', $iden)) {
                return "El pasaporte debe contener solo letras y números.";
            }
            break;

        case '07': // Venta a Consumidor Final
            if ($iden !== '9999999999999') {
                return "Para Consumidor Final la identificación debe ser 9999999999999.";
            }
            break;

        case '08': // Identificación del Exterior
            if (empty($iden)) {
                return "La identificación del exterior no puede estar vacía.";
            }
            break;

        case '99': // Placa (desactivada)
            return "Este tipo de identificación no está disponible.";
            break;
    }

    return ''; // sin errores
}

function validarCedulaEcuador($cedula) {
    // Algoritmo Módulo 10
    $cedula = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($cedula) !== 10) return false;
    if ($cedula[2] >= 6) return false; // tercer dígito debe ser < 6

    $coeficientes = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    $total = 0;
    for ($i = 0; $i < 9; $i++) {
        $valor = intval($cedula[$i]) * $coeficientes[$i];
        $total += ($valor >= 10) ? $valor - 9 : $valor;
    }
    $digitoVerificador = (10 - ($total % 10)) % 10;
    return $digitoVerificador === intval($cedula[9]);
}

function validarRucEcuador($ruc) {
    $ruc = preg_replace('/[^0-9]/', '', $ruc);
    if (strlen($ruc) !== 13) return false;

    // Los 3 últimos dígitos deben ser 001 para personas naturales
    // Para sociedades: depende del tipo de RUC
    $tipo_contribuyente = $ruc[2];
    
    if ($tipo_contribuyente <= 5) {
        // Persona natural: validar cédula (primeros 10 dígitos)
        $cedula = substr($ruc, 0, 10);
        if (!validarCedulaEcuador($cedula)) return false;
        if (substr($ruc, 10, 3) !== '001') return false;
    } elseif ($tipo_contribuyente == '6') {
        // Sociedad pública
        $coeficientes = [3, 2, 7, 6, 5, 4, 3, 2];
        $total = 0;
        for ($i = 0; $i < 8; $i++) {
            $total += intval($ruc[$i]) * $coeficientes[$i];
        }
        $residuo = $total % 11;
        $digitoVerificador = ($residuo === 0) ? 0 : 11 - $residuo;
        return $digitoVerificador === intval($ruc[8]) && substr($ruc, 9, 4) === '0001';
    } elseif ($tipo_contribuyente == '9') {
        // Sociedad privada
        $coeficientes = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $total = 0;
        for ($i = 0; $i < 9; $i++) {
            $total += intval($ruc[$i]) * $coeficientes[$i];
        }
        $residuo = $total % 11;
        $digitoVerificador = ($residuo === 0) ? 0 : 11 - $residuo;
        return $digitoVerificador === intval($ruc[9]) && substr($ruc, 10, 3) === '001';
    }

    return false;
}

// ===== PROCESAR POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $razon_social = trim($_POST['razon_social']);
    $identificacion = trim($_POST['identificacion']);
    $id_tipos_identificacion = (int)$_POST['id_tipos_identificacion'];
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Validar identificación
    $error = validarIdentificacion($identificacion, $id_tipos_identificacion, $pdo);
    if ($error) {
        echo "<script>alert('$error'); window.location.href='cliente_nuevo.php';</script>";
        exit;
    }

    // Limpiar identificación: quitar guiones, espacios, puntos
    $identificacion = preg_replace('/[^a-zA-Z0-9]/', '', $identificacion);

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
        echo "<script>alert('Error al guardar el cliente: " . addslashes($e->getMessage()) . "'); window.location.href='cliente_nuevo.php';</script>";
    }
    exit;
}

// ===== OBTENER TIPOS DE IDENTIFICACIÓN =====
$stmt_tipos = $pdo->query("SELECT id, nombre FROM tipos_identificacion WHERE activo = 1 AND aplica_cliente = 1 ORDER BY id");
$tipos_identificacion = $stmt_tipos->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); ?>
    <title>Nuevo Cliente</title>
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
                        <h1 class="h3 mb-0 text-gray-800">Nuevo Cliente</h1>
                    </div>
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card shadow mb-4">
                                <div class="card-header py-3">
                                    <h6 class="m-0 font-weight-bold text-primary">Datos del Cliente</h6>
                                </div>
                                <div class="card-body">
                                    <form method="POST" id="formCliente">
                                        <div class="form-group">
                                            <label>Razón Social / Nombre Completo</label>
                                            <input type="text" name="razon_social" class="form-control" required maxlength="255">
                                        </div>
                                        <div class="form-group">
                                            <label>Tipo de Identificación</label>
                                            <select name="id_tipos_identificacion" id="id_tipos_identificacion" class="form-control" required>
                                                <option value="">Seleccionar...</option>
                                                <?php foreach ($tipos_identificacion as $tipo): ?>
                                                    <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['nombre']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="form-group">
                                            <label>Identificación</label>
                                            <input type="text" name="identificacion" id="identificacion" class="form-control" required maxlength="20">
                                            <div id="val_hint" class="validation-hint"></div>
                                        </div>
                                        <div class="form-group">
                                            <label>Dirección</label>
                                            <input type="text" name="direccion" class="form-control" maxlength="255">
                                        </div>
                                        <div class="form-group">
                                            <label>Teléfono</label>
                                            <input type="text" name="telefono" class="form-control" maxlength="20">
                                        </div>
                                        <div class="form-group">
                                            <label>Email</label>
                                            <input type="email" name="email" class="form-control" maxlength="100">
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
    <script>
    // Validación en tiempo real del lado del cliente
    document.addEventListener('DOMContentLoaded', function() {
        const tipoSelect = document.getElementById('id_tipos_identificacion');
        const idenInput = document.getElementById('identificacion');
        const hint = document.getElementById('val_hint');

        function validarIdentificacion() {
            const tipoId = tipoSelect.value;
            const iden = idenInput.value.replace(/[^a-zA-Z0-9]/g, '');
            
            if (!tipoId || !iden) {
                hint.className = 'validation-hint';
                hint.textContent = '';
                return;
            }

            // Buscar el texto del option seleccionado para saber el nombre del tipo
            const option = tipoSelect.options[tipoSelect.selectedIndex];
            const tipoNombre = option ? option.textContent : '';

            <?php
            // Pasar datos de los tipos de identificación al JS
            $json_tipos = json_encode($tipos_identificacion);
            echo "const tipos = $json_tipos;";
            ?>

            const tipo = tipos.find(t => t.id == tipoId);
            if (!tipo) return;

            // Validar solo caracteres permitidos
            if (tipoId === '1' || tipoId === '2') { // RUC (1) o Cédula (2)
                if (!/^\d+$/.test(iden)) {
                    hint.className = 'validation-hint error';
                    hint.textContent = '⚠ Solo se permiten números';
                    return;
                }
            }

            // Mostrar longitud esperada
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

        tipoSelect.addEventListener('change', function() {
            idenInput.value = '';
            hint.className = 'validation-hint';
            hint.textContent = '';
        });
        idenInput.addEventListener('input', validarIdentificacion);
    });
    </script>
</body>
</html>
