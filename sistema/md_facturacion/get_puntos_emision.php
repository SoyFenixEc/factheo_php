<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar que la empresa pertenece al usuario y está activa
    $sql_empresa = "SELECT id FROM empresa WHERE id = :empresa_id AND usuario_id = :usuario_id AND activa = 1";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([':empresa_id' => $empresa_id, ':usuario_id' => $usuario_id]);
    $empresa = $stmt_empresa->fetch();

    if (!$empresa) {
        echo json_encode(['error' => 'Empresa no válida o inactiva']);
        exit;
    }

    // Obtener puntos de emisión de la empresa
    $sql = "SELECT id, establecimiento, punto_emision, secuencial_factura FROM punto_emision WHERE empresa_id = :empresa_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':empresa_id' => $empresa_id]);
    $puntos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($puntos);
} else {
    echo json_encode(['error' => 'Método no permitido']);
}
?>