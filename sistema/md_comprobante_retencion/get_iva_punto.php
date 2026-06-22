<?php
require_once('../md_config/conexion.php');
$pid = (int)($_POST['punto_emision_id'] ?? 0);
if (!$pid) { echo json_encode(['error' => 'No point selected']); exit; }
$stmt = $pdo->prepare("SELECT iva FROM punto_emision WHERE id = ?");
$stmt->execute([$pid]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
echo json_encode($row ?: ['error' => 'Not found']);
