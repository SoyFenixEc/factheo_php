<?php
require_once('../md_config/conexion.php');
$empresa_id = $_POST['empresa_id'] ?? 0;
if (!$empresa_id) { echo json_encode(['error' => 'No company selected']); exit; }
$stmt = $pdo->prepare("SELECT id, establecimiento, punto_emision, secuencial_factura FROM punto_emision WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($data ?: ['error' => 'No points of emission']);
