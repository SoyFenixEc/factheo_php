<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

// Verificar sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: ../md_autenticacion/login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php');
    ?>
    <!-- Leaflet CSS (igual que en tu checkout) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        /* Estilos copiados de tu checkout pero adaptados */
        .map-container-ips {
            display: flex;
            height: calc(100vh - 70px);
            width: 100%;
            position: relative;
        }
        
        .sidebar-ips {
            width: 380px;
            background: white;
            color: #333;
            display: flex;
            flex-direction: column;
            z-index: 1000;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            border-right: 1px solid #e3e6f0;
        }
        
        .sidebar-header {
            padding: 20px;
            background: #4e73df;
            color: white;
            text-align: center;
        }
        
        .sidebar-header h3 {
            margin: 0;
            font-size: 1.3rem;
        }
        
        .sidebar-header p {
            margin: 5px 0 0;
            font-size: 0.8rem;
            opacity: 0.9;
        }
        
        .search-box {
            padding: 15px;
            background: #f8f9fa;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .locations-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        
        .location-item {
            background: #fff;
            margin-bottom: 10px;
            padding: 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border-left: 3px solid #4e73df;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .location-item:hover {
            background: #f8f9fc;
            transform: translateX(3px);
        }
        
        .location-item.active {
            background: #e8f0fe;
            border-left-color: #1cc88a;
        }
        
        .location-ip {
            font-weight: bold;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .location-city {
            font-size: 0.8rem;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .location-date {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 4px;
        }
        
        .badge-status {
            font-size: 0.7rem;
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            margin-top: 5px;
        }
        
        .badge-success {
            background: #1cc88a;
            color: white;
        }
        
        .badge-danger {
            background: #e74a3b;
            color: white;
        }
        
        #map {
            flex: 1;
            height: 100%;
            width: 100%;
            z-index: 1;
        }
        
        .menu-toggle {
            display: none;
            position: fixed;
            left: 10px;
            top: 80px;
            z-index: 1001;
            background: #4e73df;
            border: none;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        .loading-overlay {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 15px 30px;
            border-radius: 30px;
            z-index: 2000;
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .sidebar-ips {
                position: absolute;
                left: 0;
                top: 0;
                height: 100%;
                transform: translateX(-100%);
                transition: transform 0.3s;
            }
            .sidebar-ips.open {
                transform: translateX(0);
            }
            .menu-toggle {
                display: block;
            }
        }
        
        .custom-popup {
            min-width: 250px;
        }
        
        .custom-popup h6 {
            margin: 0 0 10px 0;
            color: #4e73df;
        }
        
        .btn-notification {
            background: #4e73df;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            width: 100%;
            margin-top: 8px;
        }
        
        .btn-notification:hover {
            background: #224abe;
        }
        
        .stats-badge {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 12px;
            z-index: 1000;
            font-family: monospace;
        }
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

                <div class="container-fluid p-0">
                    <div class="map-container-ips">
                        <button class="menu-toggle" id="menuToggle">
                            <i class="fas fa-bars"></i> Localizaciones
                        </button>
                        
                        <div class="sidebar-ips" id="sidebar">
                            <div class="sidebar-header">
                                <h3><i class="fas fa-map-marker-alt"></i> Ubicaciones de Acceso</h3>
                                <p>IPs distintas - Historial de conexiones</p>
                            </div>
                            <div class="search-box">
                                <input type="text" id="searchInput" placeholder="🔍 Buscar por IP, ciudad o país..." class="form-control form-control-sm">
                            </div>
                            <div class="locations-list" id="locationsList">
                                <div class="text-center p-3">
                                    <i class="fas fa-spinner fa-spin"></i> Cargando ubicaciones...
                                </div>
                            </div>
                        </div>

                        <div id="map"></div>
                        <div class="stats-badge" id="statsBadge">
                            <i class="fas fa-chart-simple"></i> Cargando...
                        </div>
                    </div>
                </div>
            </div>
            
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>

    <?php require('../entorno/script.php'); ?>
    
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    let map;
    let markers = {};
    let locationsData = [];
    
    // Inicializar mapa (IGUAL QUE EN TU CHECKOUT)
    function initMap() {
        map = L.map('map').setView([-0.22985, -78.52495], 5); // Centro en Ecuador
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
        }).addTo(map);
        
        console.log('✅ Mapa inicializado');
    }
    
    // Cargar datos desde PHP
    async function loadLocations() {
        try {
            console.log('📡 Cargando ubicaciones...');
            const response = await fetch('api_geolocalizar_ips.php');
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            
            const data = await response.json();
            console.log(`📊 Datos recibidos: ${data.length} IPs distintas`);
            return data;
        } catch (error) {
            console.error('❌ Error:', error);
            return [];
        }
    }
    
    // Crear icono personalizado (similar a tu estilo)
    function getMarkerIcon(resultado) {
        const color = resultado === 'éxito' ? '#1cc88a' : '#e74a3b';
        return L.divIcon({
            html: `<div style="background-color: ${color}; width: 14px; height: 14px; border-radius: 50%; border: 2px solid white; box-shadow: 0 0 4px rgba(0,0,0,0.5);"></div>`,
            iconSize: [14, 14],
            className: 'custom-marker'
        });
    }
    
    // Agregar marcadores al mapa
    function addMarkersToMap(locations) {
        // Limpiar marcadores existentes
        Object.values(markers).forEach(marker => map.removeLayer(marker));
        markers = {};
        
        let validCount = 0;
        
        locations.forEach(loc => {
            if (loc.lat && loc.lon && !isNaN(loc.lat) && !isNaN(loc.lon) && loc.lat !== 0) {
                const icon = getMarkerIcon(loc.resultado);
                const marker = L.marker([loc.lat, loc.lon], { icon }).addTo(map);
                
                // Popup con información
                const popupContent = `
                    <div class="custom-popup">
                        <h6><i class="fas fa-network-wired"></i> ${loc.ip}</h6>
                        <hr>
                        <p><strong><i class="fas fa-map-pin"></i> Ubicación:</strong><br>
                        ${loc.city || 'Desconocida'}, ${loc.country || 'Desconocido'}</p>
                        <p><strong><i class="fas fa-calendar"></i> Último acceso:</strong><br>
                        ${loc.fecha || 'No disponible'}</p>
                        <p><strong><i class="fas fa-chart-line"></i> Total accesos:</strong><br>
                        ${loc.total_accesos || 1} veces</p>
                        <button class="btn-notification" onclick="verHistorialIP('${loc.ip}')">
                            <i class="fas fa-history"></i> Ver historial completo
                        </button>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                markers[loc.ip] = marker;
                validCount++;
            }
        });
        
        console.log(`📍 Marcadores agregados: ${validCount}`);
        document.getElementById('statsBadge').innerHTML = `<i class="fas fa-map-marker-alt"></i> ${validCount} ubicaciones`;
        
        // Ajustar vista para mostrar todos los marcadores
        if (validCount > 0) {
            const group = L.featureGroup(Object.values(markers));
            map.fitBounds(group.getBounds().pad(0.1));
        }
    }
    
    // Poblar lista lateral
    function populateSidebar(locations) {
        const listContainer = document.getElementById('locationsList');
        const searchInput = document.getElementById('searchInput');
        
        function renderList(filter = '') {
            const filtered = locations.filter(l => 
                l.ip.toLowerCase().includes(filter) || 
                (l.city && l.city.toLowerCase().includes(filter)) ||
                (l.country && l.country.toLowerCase().includes(filter))
            );
            
            if (filtered.length === 0) {
                listContainer.innerHTML = '<div class="text-center p-3 text-muted">🔍 No se encontraron resultados</div>';
                return;
            }
            
            let html = '';
            filtered.forEach(loc => {
                const resultClass = loc.resultado === 'éxito' ? 'badge-success' : 'badge-danger';
                const resultText = loc.resultado === 'éxito' ? '✓ Éxito' : '✗ Fallido';
                
                html += `
                    <div class="location-item" data-ip="${loc.ip}">
                        <div class="location-ip">
                            <i class="fas fa-network-wired"></i> ${loc.ip}
                        </div>
                        <div class="location-city">
                            <i class="fas fa-city"></i> ${loc.city || 'Ubicación desconocida'}, ${loc.country || ''}
                        </div>
                        <div class="location-date">
                            <i class="fas fa-calendar-alt"></i> ${loc.fecha || 'Fecha no disponible'}
                        </div>
                        <div class="badge-status ${resultClass}">
                            ${resultText}
                        </div>
                    </div>
                `;
            });
            listContainer.innerHTML = html;
            
            // Eventos click
            document.querySelectorAll('.location-item').forEach(item => {
                item.addEventListener('click', () => {
                    const ip = item.dataset.ip;
                    const loc = locations.find(l => l.ip === ip);
                    if (loc && loc.lat && loc.lon && map) {
                        map.setView([loc.lat, loc.lon], 12);
                        if (markers[ip]) markers[ip].openPopup();
                        document.querySelectorAll('.location-item').forEach(el => el.classList.remove('active'));
                        item.classList.add('active');
                    }
                });
            });
        }
        
        renderList();
        searchInput.addEventListener('input', (e) => renderList(e.target.value.toLowerCase()));
    }
    
    // Ver historial completo de una IP
    function verHistorialIP(ip) {
        Swal.fire({
            title: `📋 Historial de IP: ${ip}`,
            html: `<div id="historialContent" class="text-left">Cargando historial...</div>`,
            width: '800px',
            showConfirmButton: true,
            confirmButtonText: 'Cerrar',
            didOpen: async () => {
                try {
                    const response = await fetch(`api_geolocalizar_ips.php?ip=${encodeURIComponent(ip)}`);
                    const data = await response.json();
                    const content = document.getElementById('historialContent');
                    
                    if (data && data.length > 0) {
                        let html = `
                            <div style="max-height: 400px; overflow-y: auto;">
                                <table class="table table-sm table-striped">
                                    <thead class="thead-dark">
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Resultado</th>
                                            <th>Usuario ID</th>
                                            <th>Dispositivo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                        `;
                        data.forEach(reg => {
                            html += `
                                <tr>
                                    <td>${reg.fecha || 'N/A'}</td>
                                    <td><span class="badge ${reg.resultado === 'éxito' ? 'badge-success' : 'badge-danger'}">${reg.resultado || 'N/A'}</span></td>
                                    <td>${reg.usuario_id || 'N/A'}</td>
                                    <td><small>${reg.user_agent ? reg.user_agent.substring(0, 50) + '...' : 'N/A'}</small></td>
                                </tr>
                            `;
                        });
                        html += '</tbody></table></div>';
                        content.innerHTML = html;
                    } else {
                        content.innerHTML = '<p class="text-muted text-center">📭 No hay registros para esta IP</p>';
                    }
                } catch (error) {
                    content.innerHTML = '<p class="text-danger text-center">❌ Error cargando el historial</p>';
                }
            }
        });
    }
    
    // Inicializar todo
    async function init() {
        try {
            initMap();
            
            const loadingDiv = document.createElement('div');
            loadingDiv.className = 'loading-overlay';
            loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Geocalizando IPs...';
            document.querySelector('.map-container-ips').appendChild(loadingDiv);
            
            locationsData = await loadLocations();
            loadingDiv.remove();
            
            if (locationsData.length === 0) {
                document.getElementById('locationsList').innerHTML = `
                    <div class="alert alert-warning m-3">
                        <i class="fas fa-exclamation-triangle"></i> No se encontraron registros de IPs<br>
                        <small>La tabla auditoria_login está vacía</small>
                    </div>
                `;
                document.getElementById('statsBadge').innerHTML = '<i class="fas fa-chart-simple"></i> 0 ubicaciones';
            } else {
                populateSidebar(locationsData);
                addMarkersToMap(locationsData);
            }
        } catch (error) {
            console.error('Error en init:', error);
            const loadingDiv = document.querySelector('.loading-overlay');
            if (loadingDiv) loadingDiv.remove();
        }
    }
    
    // Toggle sidebar en móvil
    document.getElementById('menuToggle')?.addEventListener('click', () => {
        document.getElementById('sidebar').classList.toggle('open');
    });
    
    document.addEventListener('click', (e) => {
        if (window.innerWidth <= 768) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('menuToggle');
            if (sidebar?.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
                sidebar.classList.remove('open');
            }
        }
    });
    
    // Iniciar
    init();
    </script>
</body>
</html>