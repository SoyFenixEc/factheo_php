<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $usuario_id = $_SESSION['usuario_id'];

    // Verificar que el punto de emisión pertenece a una empresa del usuario
    $sql = "
        SELECT pe.iva
        FROM punto_emision pe
        JOIN empresa e ON pe.empresa_id = e.id
        WHERE pe.id = :punto_emision_id AND e.usuario_id = :usuario_id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':punto_emision_id' => $punto_emision_id, ':usuario_id' => $usuario_id]);
    $punto = $stmt->fetch();

    if ($punto) {
        echo json_encode(['iva' => $punto['iva']]);
    } else {
        echo json_encode(['iva' => 0]);
    }
} else {
    echo json_encode(['iva' => 0]);
}
?> 