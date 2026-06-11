<?php
require('../md_autenticacion/sesion.php');

$liqui_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

require('../md_config/conexion.php');
$stmt = $pdo->prepare("SELECT f.*, pv.nombre AS proveedor_nombre 
                      FROM facturas f 
                      LEFT JOIN proveedores pv ON f.cliente_id = pv.id 
                      WHERE f.id = ? AND f.tipo_comprobante_id = '03'");
$stmt->execute([$liqui_id]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$factura) {
    die("<div class='alert alert-danger'>Liquidación no encontrada.</div>");
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php
    require('../entorno/meta.php');
    require('../entorno/title.php');
    require('../entorno/link.php');
    require('../md_config/conexion.php');
    require('../entorno/funciones.php');
    ?>
    <script src="../../js/funciones.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card { border: 1px solid #ddd; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .btn-lg { font-size: 1.2rem; padding: 12px 30px; }
        .log { font-size: 0.9rem; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 5px; padding: 10px; margin: 10px 0; max-height: 300px; overflow-y: auto; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .processing { color: #007bff; }
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
                    <button id="sidebarToggleTop" class="btn btn-link d-md-none rounded-circle mr-3"><i class="fa fa-bars"></i></button>
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
                        <h1 class="h3 mb-0 text-gray-800">Liquidación de Compra Electrónica</h1>
                        <a href="liquidacion_compra_lista.php" class="btn btn-sm btn-secondary shadow-sm"><i class="fas fa-arrow-left"></i> Volver</a>
                    </div>
                    <div class="row">
                      <div class="col-md-12">
                        <div class="card shadow-sm p-4">
                            <h2 class="text-center mb-4">⚡ Botón Único - Liquidación Electrónica</h2>
                            <p class="text-center text-muted">Proceso: Generar XML → Firmar → Enviar al SRI → Autorizar</p>
                            <?php
                            echo "<input type='hidden' id='liqui_id' value='$liqui_id'>";
                            echo "<div class='alert alert-info'>";
                            echo "<strong>ID:</strong> $liqui_id<br>";
                            echo "<strong>Proveedor:</strong> " . htmlspecialchars($factura['proveedor_nombre'] ?? '') . "<br>";
                            echo "<strong>Clave:</strong> " . ($factura['clave_acceso'] ?? 'No generada') . "<br>";
                            echo "<strong>Estado:</strong> " . ($factura['estado_xml'] ?? 'PENDIENTE') . "<br>";
                            echo "</div>";
                            ?>
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-success btn-lg" onclick="ejecutar()">✅ Iniciar Proceso Completo</button>
                            </div>
                            <div id="resultado" class="mt-4 log"></div>
                            <div class="mt-4">
                                <h5>Progreso:</h5>
                                <div class="progress mb-3">
                                    <div id="progress-bar" class="progress-bar" style="width:0%"></div>
                                </div>
                                <div id="status" class="text-center"></div>
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
    function log(m, t = '') {
        const el = document.getElementById('resultado');
        const d = document.createElement('div'); d.className = t;
        d.innerHTML = `<small>${new Date().toLocaleTimeString()} - ${m}</small>`;
        el.appendChild(d); el.scrollTop = el.scrollHeight;
    }
    function updateProgress(step, total, msg) {
        const pct = (step/total)*100;
        document.getElementById('progress-bar').style.width = `${pct}%`;
        document.getElementById('status').innerHTML = `<strong>${msg}</strong> (${step}/${total})`;
    }
    function showAlert(title, text, icon) {
        if (typeof Swal !== 'undefined') Swal.fire({title, text, icon});
    }
    async function ejecutar() {
        const id = document.getElementById('liqui_id').value;
        const btn = document.querySelector('button'); btn.disabled = true; btn.textContent = 'Procesando...';
        document.getElementById('resultado').innerHTML = '';
        updateProgress(0, 4, 'Iniciando...');

        try {
            // PASO 1: Generar XML
            updateProgress(1, 4, 'Generando XML...');
            log("🔹 Paso 1: Generando XML...", 'processing');
            const r1 = await fetch(`liquidacion_compra_generar_xml_1_1_0.php?id=${id}`);
            const d1 = await r1.json();
            if (!d1.ok) throw new Error("Error XML: " + d1.error);
            log("✅ XML generado.", 'success');

            // PASO 2: Firmar
            updateProgress(2, 4, 'Firmando XML...');
            log("🔹 Paso 2: Firmando XML...", 'processing');
            const r2 = await fetch(`../md_facturacion/autorizacion/procesos/firmar2.php?id=${id}`);
            const d2 = await r2.json();
            if (!d2.ok) throw new Error("Firmar: " + (d2.error || "Error firma"));
            log("✅ XML firmado.", 'success');

            // PASO 3: Enviar al SRI
            updateProgress(3, 4, 'Enviando al SRI...');
            log("🔹 Paso 3: Enviando al SRI...", 'processing');
            const r3 = await fetch(`../md_facturacion/autorizacion/procesos/envio2.php?id=${id}`);
            const d3 = await r3.json();
            if (d3[0] === 0) throw new Error("Enviar: WebService SRI Inaccesible");
            if (d3[1] !== 'RECIBIDA') throw new Error("Enviar: " + (d3[2] || 'Error'));
            log("✅ Enviado correctamente.", 'success');

            // PASO 4: Autorizar
            updateProgress(4, 4, 'Autorizando...');
            log("🔹 Paso 4: Autorizando en el SRI...", 'processing');
            const r4 = await fetch(`../md_facturacion/autorizacion/procesos/autoriza2.php?id=${id}`);
            const d4 = await r4.json();
            if (d4[0] === 0) throw new Error("Autorizar: WebService SRI Inaccesible");
            if (d4[1] !== 'AUTORIZADO') throw new Error("Autorizar: " + (d4[2] || 'Error'));
            log("✅ Autorizado: " + d4[2], 'success');

            document.getElementById('resultado').innerHTML += `
                <div class="alert alert-success mt-3">
                    <strong>✅ Liquidación autorizada</strong><br>
                    <a href="pdf_liquidacion.php?id=${id}" class="btn btn-sm btn-primary" target="_blank">📄 Generar PDF</a>
                </div>`;
            showAlert("¡Completado!", "Liquidación autorizada correctamente", "success");
        } catch (e) {
            log(`❌ Error: ${e.message}`, 'error');
            showAlert("Error", e.message, "error");
        } finally {
            btn.disabled = false; btn.textContent = '✅ Iniciar Proceso Completo';
        }
    }
    </script>
</body>
</html>
