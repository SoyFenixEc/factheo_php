<?php
require('../md_autenticacion/sesion.php');

// Obtener el ID de factura de la URL
$factura_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Obtener información de la factura
require('../md_config/conexion.php');
$stmt = $pdo->prepare("SELECT f.*, c.razon_social 
                      FROM facturas f 
                      LEFT JOIN clientes c ON f.cliente_id = c.id 
                      WHERE f.id = ?");
$stmt->execute([$factura_id]);
$factura = $stmt->fetch(PDO::FETCH_ASSOC);

// Si no hay factura, mostrar error
if (!$factura) {
    die("<div class='alert alert-danger'>Factura no encontrada.</div>");
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
    require('../entorno/combo_box.php');
    ?>
    <script src="../../js/funciones.js"></script>
    <!-- Cargar SweetAlert2 directamente -->
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
                <div id="dynamic-content" class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Facturación Electrónica</h1>
						
                        <a href="facturacion_lista.php" class="d-none d-sm-inline-block btn btn-sm btn-secondary shadow-sm">
                            <i class="fas fa-arrow-left fa-sm text-white-50"></i> Volver a Facturas</a>
						
                    </div>
                    <div class="row">
					

<!-------------------------------------------------------------------->
                      <div class="col-md-12">
                        <div class="card shadow-sm p-4">
                            <h2 class="text-center mb-4">⚡ Botón Único - Facturación Electrónica</h2>
                            <p class="text-center text-muted">Proceso completo: Generar XML → Firmar → Enviar al SRI → Autorizar</p>

                            <?php
                            echo "<input type='hidden' id='factura_id' value='$factura_id'>";
                            echo "<div class='alert alert-info'>";
                            echo "<strong>ID Factura:</strong> $factura_id<br>";
                            echo "<strong>Cliente:</strong> " . htmlspecialchars($factura['razon_social'] ?? '') . "<br>";
                            echo "<strong>Clave de Acceso:</strong> " . ($factura['clave_acceso'] ?? 'No generada') . "<br>";
                            echo "<strong>Estado:</strong> " . ($factura['estado_xml'] ?? 'PENDIENTE') . "<br>";
                            echo "</div>";
                            ?>

                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-success btn-lg" onclick="ejecutarFacturacion()">
                                    ✅ Iniciar Proceso Completo
                                </button>
                            </div>

                            <div id="resultado" class="mt-4 log"></div>
                            
                            <div class="mt-4">
                                <h5>Progreso:</h5>
                                <div class="progress mb-3">
                                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                                <div id="status" class="text-center"></div>
                            </div>
                        </div>
                      </div>
<!-------------------------------------------------------------------->
                      
					
                    </div>
                </div>
            </div>
            <?php require('../entorno/footer.php'); ?>
        </div>
    </div>
    <?php require('../entorno/script.php'); ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script>
	// Verificar si SweetAlert2 está disponible, si no, cargarlo dinámicamente
	function ensureSweetAlert(callback) {
		if (typeof Swal === 'undefined') {
			// SweetAlert2 no está cargado, cargarlo dinámicamente
			var script = document.createElement('script');
			script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
			script.onload = callback;
			document.head.appendChild(script);
		} else {
			callback();
		}
	}

	function log(mensaje, tipo = '') {
		const resultado = document.getElementById('resultado');
		const entry = document.createElement('div');
		entry.className = tipo;
		entry.innerHTML = `<small>${new Date().toLocaleTimeString()} - ${mensaje}</small>`;
		resultado.appendChild(entry);
		resultado.scrollTop = resultado.scrollHeight;
	}

	function updateProgress(step, totalSteps, message) {
		const percentage = (step / totalSteps) * 100;
		document.getElementById('progress-bar').style.width = `${percentage}%`;
		document.getElementById('progress-bar').setAttribute('aria-valuenow', percentage);
		document.getElementById('status').innerHTML = `<strong>${message}</strong> (${step}/${totalSteps})`;
	}

	function showAlert(title, text, icon) {
		ensureSweetAlert(function() {
			Swal.fire({
				title: title,
				text: text,
				icon: icon
			});
		});
	}

	async function ejecutarFacturacion() {
		const id = document.getElementById('factura_id').value;
		const btn = document.querySelector('button');
		btn.disabled = true;
		btn.textContent = 'Procesando...';
		
		// Limpiar resultados anteriores
		document.getElementById('resultado').innerHTML = '';
		updateProgress(0, 4, 'Iniciando proceso...');

		try {
			// --- PASO 1: Generar XML ---
			updateProgress(1, 4, 'Generando XML...');
			log("🔹 Paso 1: Generando XML...", 'processing');
			
			const res1 = await fetch(`facturacion_generar_xml_1_1_0.php?id=${id}`);
			const data1 = await res1.json();
			
			if (!data1.ok) {
				throw new Error("Error al generar XML: " + data1.error);
			}
			
			log("✅ XML generado correctamente.", 'success');
			log(`Archivo: ${data1.archivo}`, 'processing');

			// --- PASO 2: Firmar XML ---
			updateProgress(2, 4, 'Firmando XML...');
			log("🔹 Paso 2: Firmando XML...", 'processing');
			
			// Usando firmar2.php que devuelve formato JSON {ok: true, mensaje: ...}
			const res2 = await fetch(`autorizacion/procesos/firmar2.php?id=${id}`);
			const data2 = await res2.json();
			
			if (!data2.ok) {
				throw new Error("Firmar: " + (data2.error || "Error en el proceso de firma"));
			}
			
			log("✅ XML firmado correctamente.", 'success');
			log(`Detalle: ${data2.mensaje}`, 'processing');

			// --- PASO 3: Enviar al SRI ---
			updateProgress(3, 4, 'Enviando al SRI...');
			log("🔹 Paso 3: Enviando al SRI...", 'processing');
			
			// Usando envio2.php que espera el parámetro id
			const res3 = await fetch(`autorizacion/procesos/envio2.php?id=${id}`);
			const data3 = await res3.json();
			
			// Manejar formato de respuesta para envio2.php (formato array)
			if (data3[0] === 0) {
				throw new Error("Enviar: WebService SRI Inaccesible");
			}
			
			if (data3[1] !== 'RECIBIDA') {
				throw new Error("Enviar: Error " + (data3[2] || 'desconocido'));
			}
			
			log("✅ Enviado correctamente.", 'success');
			if (data3[2]) {
				log(`Detalle: ${data3[2]}`, 'processing');
			}

			// --- PASO 4: Autorizar ---
			updateProgress(4, 4, 'Autorizando...');
			log("🔹 Paso 4: Autorizando en el SRI...", 'processing');
			
			// Usando autoriza2.php que espera el parámetro id
			const res4 = await fetch(`autorizacion/procesos/autoriza2.php?id=${id}`);
			const data4 = await res4.json();
			
			// Manejar formato de respuesta para autoriza2.php (formato array)
			if (data4[0] === 0) {
				throw new Error("Autorizar: WebService SRI Inaccesible");
			}
			
			if (data4[1] !== 'AUTORIZADO') {
				throw new Error("Autorizar: Error " + (data4[2] || 'desconocido'));
			}
			
			log("✅ Autorizado correctamente.", 'success');
			log(`Número de autorización: ${data4[2]}`, 'processing');

			// Mostrar éxito final
			document.getElementById('resultado').innerHTML += `
				<div class="alert alert-success mt-3">
					<strong>✅ Factura autorizada correctamente</strong><br>
					<a href="autorizacion/procesos/pdf.php?id=${id}" class="btn btn-sm btn-primary me-2" target="_blank">Generar PDF</a>
					<a href="autorizacion/comprobantes/autorizados/fac_${data4[2]}.xml" class="btn btn-sm btn-outline-success" download>Descargar XML</a>
				</div>`;
			
			// Mostrar SweetAlert de éxito
			showAlert("¡Proceso completado!", "Factura autorizada correctamente", "success");
			
		} catch (error) {
			log(`❌ Error: ${error.message}`, 'error');
			console.error(error);
			
			// Mostrar SweetAlert de error
			showAlert("Error en el proceso", error.message, "error");
		} finally {
			btn.disabled = false;
			btn.textContent = '✅ Iniciar Proceso Completo';
		}
	}
	</script>
</body>
</html>