<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) exit('<p class="text-muted text-center py-3">Escribe al menos 2 caracteres...</p>');

$params = ['%' . $q . '%', '%' . $q . '%'];
$sql = "SELECT id, razon_social, identificacion, id_tipos_identificacion FROM clientes WHERE usuario_id = ? AND (razon_social LIKE ? OR identificacion LIKE ?) ORDER BY razon_social LIMIT 20";
$usuario_id = $_SESSION['usuario_id'];

$stmt = $pdo->prepare($sql);
$stmt->execute([$usuario_id, '%' . $q . '%', '%' . $q . '%']);
$clientes = $stmt->fetchAll();

if (count($clientes) == 0) {
    echo '<p class="text-muted text-center py-3">No se encontraron clientes</p>';
} else {
    foreach ($clientes as $c) {
        echo '<div class="client-search-result" data-id="' . $c['id'] . '" data-nombre="' . htmlspecialchars($c['razon_social']) . '" data-identificacion="' . htmlspecialchars($c['identificacion']) . '">';
        echo '<div class="client-name"><i class="fas fa-user mr-1"></i>' . htmlspecialchars($c['razon_social']) . '</div>';
        echo '<div class="client-id">ID: ' . htmlspecialchars($c['identificacion']) . '</div>';
        echo '</div>';
    }
}
