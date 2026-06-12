<?php
require('../md_config/conexion.php');

$registrosPorPagina = 10; // Número de registros por página
$paginaActual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1; // Página actual
$offset = ($paginaActual - 1) * $registrosPorPagina;

// Consulta para obtener los registros de la página actual
$sql = "SELECT s.*, u.nombre AS usuario_nombre 
        FROM sesiones s
        INNER JOIN usuarios u ON s.usuario_id = u.id
        ORDER BY s.creado_en DESC
        LIMIT :offset, :registrosPorPagina";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
$stmt->bindParam(':registrosPorPagina', $registrosPorPagina, PDO::PARAM_INT);
$stmt->execute();
$resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Contar el total de registros
$totalRegistrosSql = "SELECT COUNT(*) AS total FROM sesiones";
$totalRegistrosStmt = $pdo->query($totalRegistrosSql);
$totalRegistros = $totalRegistrosStmt->fetch(PDO::FETCH_ASSOC)['total'];
$totalPaginas = ceil($totalRegistros / $registrosPorPagina);

// Enviar datos como JSON
echo json_encode(['registros' => $resultados, 'totalPaginas' => $totalPaginas]);
?>
