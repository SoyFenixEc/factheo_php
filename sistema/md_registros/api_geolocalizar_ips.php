<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../md_config/conexion.php');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function geolocalizarIP($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return ['lat' => null, 'lon' => null, 'city' => 'IP Privada', 'country' => 'Local'];
    }
    
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (isset($_SESSION['geo_cache'][$ip])) {
        return $_SESSION['geo_cache'][$ip];
    }
    
    $url = "http://ip-api.com/json/{$ip}?fields=status,country,city,lat,lon";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 3);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $data = json_decode($response, true);
        if ($data && isset($data['status']) && $data['status'] === 'success') {
            $result = [
                'lat' => $data['lat'],
                'lon' => $data['lon'],
                'city' => $data['city'],
                'country' => $data['country']
            ];
            $_SESSION['geo_cache'][$ip] = $result;
            return $result;
        }
    }
    
    return ['lat' => null, 'lon' => null, 'city' => 'Desconocida', 'country' => 'Desconocido'];
}

// Historial por IP específica
if (isset($_GET['ip']) && !empty($_GET['ip'])) {
    $ip_especifica = $_GET['ip'];
    $sql_historial = "SELECT id, usuario_id, ip_address, user_agent, resultado, fecha 
                      FROM auditoria_login 
                      WHERE ip_address = ? 
                      ORDER BY fecha DESC 
                      LIMIT 50";
    $stmt_hist = $pdo->prepare($sql_historial);
    $stmt_hist->execute([$ip_especifica]);
    $historial = $stmt_hist->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($historial);
    exit;
}

// Obtener IPs DISTINTAS
$sql = "SELECT 
            ip_address, 
            MAX(fecha) as ultima_fecha,
            COUNT(*) as total_accesos,
            (SELECT resultado FROM auditoria_login a2 
             WHERE a2.ip_address = a1.ip_address 
             ORDER BY fecha DESC LIMIT 1) as ultimo_resultado
        FROM auditoria_login a1
        WHERE ip_address IS NOT NULL 
        AND ip_address != ''
        AND ip_address != '0.0.0.0'
        GROUP BY ip_address
        ORDER BY ultima_fecha DESC
        LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$ips_unicas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$resultados = [];

foreach ($ips_unicas as $ip_data) {
    $ip = $ip_data['ip_address'];
    $geo = geolocalizarIP($ip);
    
    $resultados[] = [
        'ip' => $ip,
        'lat' => $geo['lat'],
        'lon' => $geo['lon'],
        'city' => $geo['city'],
        'country' => $geo['country'],
        'resultado' => $ip_data['ultimo_resultado'] ?? 'desconocido',
        'fecha' => $ip_data['ultima_fecha'],
        'total_accesos' => $ip_data['total_accesos']
    ];
}

if (rand(1, 100) === 1) {
    $_SESSION['geo_cache'] = [];
}

echo json_encode($resultados);
?>