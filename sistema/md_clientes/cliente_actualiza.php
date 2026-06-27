<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Función de validación (misma que en cliente_nuevo.php)
function validarIdentificacion($identificacion, $id_tipo, $pdo) {
    $stmt = $pdo->prepare("SELECT codigo, nombre, longitud_min, longitud_max FROM tipos_identificacion WHERE id = ? AND activo = 1");
    $stmt->execute([$id_tipo]);
    $tipo = $stmt->fetch();
    if (!$tipo) return "Tipo de identificación no válido.";

    $iden = preg_replace('/[^a-zA-Z0-9]/', '', $identificacion);
    $len = strlen($iden);
    $codigo = $tipo['codigo'];

    if ($tipo['longitud_min'] && $len < $tipo['longitud_min'])
        return "La identificación para {$tipo['nombre']} debe tener al menos {$tipo['longitud_min']} caracteres.";
    if ($tipo['longitud_max'] && $len > $tipo['longitud_max'])
        return "La identificación para {$tipo['nombre']} debe tener máximo {$tipo['longitud_max']} caracteres.";

    switch ($codigo) {
        case '04':
            if (!ctype_digit($iden)) return "El RUC debe contener solo números (13 dígitos).";
            if ($len !== 13) return "El RUC debe tener exactamente 13 dígitos.";
            if (!validarRucEcuador($iden)) return "El RUC ingresado no es válido.";
            break;
        case '05':
            if (!ctype_digit($iden)) return "La cédula debe contener solo números (10 dígitos).";
            if ($len !== 10) return "La cédula debe tener exactamente 10 dígitos.";
            if (!validarCedulaEcuador($iden)) return "La cédula ingresada no es válida.";
            break;
        case '06':
            if (!preg_match('/^[a-zA-Z0-9]+$/', $iden)) return "El pasaporte debe contener solo letras y números.";
            break;
        case '07':
            if ($iden !== '9999999999999') return "Para Consumidor Final la identificación debe ser 9999999999999.";
            break;
        case '08':
            if (empty($iden)) return "La identificación del exterior no puede estar vacía.";
            break;
        case '99':
            return "Este tipo de identificación no está disponible.";
    }
    return '';
}

function validarCedulaEcuador($cedula) {
    $cedula = preg_replace('/[^0-9]/', '', $cedula);
    if (strlen($cedula) !== 10) return false;
    if ($cedula[2] >= 6) return false;
    $coef = [2, 1, 2, 1, 2, 1, 2, 1, 2];
    $total = 0;
    for ($i = 0; $i < 9; $i++) {
        $v = intval($cedula[$i]) * $coef[$i];
        $total += ($v >= 10) ? $v - 9 : $v;
    }
    return ((10 - ($total % 10)) % 10) === intval($cedula[9]);
}

function validarRucEcuador($ruc) {
    $ruc = preg_replace('/[^0-9]/', '', $ruc);
    if (strlen($ruc) !== 13) return false;
    $tipo = $ruc[2];
    if ($tipo <= 5) {
        return validarCedulaEcuador(substr($ruc, 0, 10)) && substr($ruc, 10, 3) === '001';
    } elseif ($tipo === '6') {
        $coef = [3, 2, 7, 6, 5, 4, 3, 2];
        $total = 0;
        for ($i = 0; $i < 8; $i++) $total += intval($ruc[$i]) * $coef[$i];
        $dv = ($total % 11 === 0) ? 0 : 11 - ($total % 11);
        return $dv === intval($ruc[8]) && substr($ruc, 9, 4) === '0001';
    } elseif ($tipo === '9') {
        $coef = [4, 3, 2, 7, 6, 5, 4, 3, 2];
        $total = 0;
        for ($i = 0; $i < 9; $i++) $total += intval($ruc[$i]) * $coef[$i];
        $dv = ($total % 11 === 0) ? 0 : 11 - ($total % 11);
        return $dv === intval($ruc[9]) && substr($ruc, 10, 3) === '001';
    }
    return false;
}

// ===== PROCESAR POST =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = (int)$_POST['id'];
    $razon_social = trim($_POST['razon_social']);
    $identificacion = trim($_POST['identificacion']);
    $id_tipos_identificacion = (int)$_POST['id_tipos_identificacion'];
    $direccion = trim($_POST['direccion'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Verificar que el cliente pertenece al usuario
    $sql_check = "SELECT id FROM clientes WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();

    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar este cliente.'); window.location.href='cliente_lista.php';</script>";
        exit();
    }

    // Validar identificación
    $error = validarIdentificacion($identificacion, $id_tipos_identificacion, $pdo);
    if ($error) {
        echo "<script>alert('$error'); window.location.href='cliente_editar.php?id=$id';</script>";
        exit;
    }

    $identificacion = preg_replace('/[^a-zA-Z0-9]/', '', $identificacion);

    // Actualizar el cliente
    $sql_update = "UPDATE clientes SET 
                    razon_social = :razon_social, 
                    identificacion = :identificacion, 
                    id_tipos_identificacion = :id_tipos_identificacion,
                    direccion = :direccion, 
                    telefono = :telefono, 
                    email = :email 
                   WHERE id = :id";
    $params = [
        ':razon_social' => $razon_social,
        ':identificacion' => $identificacion,
        ':id_tipos_identificacion' => $id_tipos_identificacion,
        ':direccion' => $direccion,
        ':telefono' => $telefono,
        ':email' => $email,
        ':id' => $id
    ];

    try {
        $stmt = $pdo->prepare($sql_update);
        $stmt->execute($params);
        echo "<script>alert('Cliente actualizado exitosamente.'); window.location.href='cliente_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar el cliente: " . addslashes($e->getMessage()) . "'); window.location.href='cliente_editar.php?id=$id';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='cliente_lista.php';</script>";
}
?>
