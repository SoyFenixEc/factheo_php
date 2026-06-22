<?php
require('../md_autenticacion/sesion.php');
$cr_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
require('../md_config/conexion.php');
$stmt = $pdo->prepare("SELECT cr.*, pv.nombre AS prov_nombre 
                        FROM comprobantes_retencion cr 
                        LEFT JOIN proveedores pv ON cr.proveedor_id = pv.id 
                        WHERE cr.id = ?");
$stmt->execute([$cr_id]);
$cr_data = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$cr_data) die("<div class='alert alert-danger'>Comprobante de Retención no encontrado.</div>");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <?php require('../entorno/meta.php'); require('../entorno/title.php'); require('../entorno/link.php'); ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .card { border:1px solid #ddd; border-radius:10px; box-shadow:0 4px 6px rgba(0,0,0,0.1); }
        .btn-lg { font-size:1.2rem; padding:12px 30px; }
        .log { font-size:0.9rem; background:#f8f9fa; border:1px solid #dee2e6; border-radius:5px; padding:10px; margin:10px 0; max-height:300px; overflow-y:auto; }
        .success { color:#28a745; } .error { color:#dc3545; } .processing { color:#007bff; }
    </style>
</head>
<body id="page-top">
    <div id="wrapper">
        <ul class="navbar-nav bg-gradient-primary sidebar sidebar-dark accordion" id="accordionSidebar"><?php require('../entorno/menu.php'); ?></ul>
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
                        <h1 class="h3 mb-0 text-gray-800">⚡ Autorizar Comprobante de Retención</h1>
                        <a href="comprobante_retencion_lista.php" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left"></i> Volver</a>
                    </div>
                    <div class="row">
                        <div class="col-md-12">
                            <div class="card shadow-sm p-4">
                                <p class="text-center text-muted">Proceso completo: Generar XML → Firmar → Enviar al SRI (Pruebas) → Autorizar</p>
                                <input type="hidden" id="cr_id" value="<?= $cr_id ?>">
                                <div class="alert alert-info">
                                    <strong>ID:</strong> <?= $cr_id ?><br>
                                    <strong>Sujeto Retenido:</strong> <?= htmlspecialchars($cr_data['razon_social_sujeto_retenido'] ?? '') ?><br>
                                    <strong>Clave de Acceso:</strong> <?= $cr_data['clave_acceso'] ?? 'No generada' ?><br>
                                    <strong>Estado:</strong> <?= $cr_data['estado_xml'] ?? 'PENDIENTE' ?>
                                </div>
                                <div class="text-center mt-4">
                                    <button type="button" class="btn btn-success btn-lg" onclick="ejecutar()">✅ Iniciar Proceso Completo</button>
                                </div>
                                <div id="resultado" class="mt-4 log"></div>
                                <div class="mt-4">
                                    <h5>Progreso:</h5>
                                    <div class="progress mb-3"><div id="progress-bar" class="progress-bar" style="width:0%"></div></div>
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
    function log(m, t='') { const r=document.getElementById('resultado'); const e=document.createElement('div'); e.className=t; e.innerHTML='<small>'+new Date().toLocaleTimeString()+' - '+m+'</small>'; r.appendChild(e); r.scrollTop=r.scrollHeight; }
    function upd(st,t,m) { const p=(st/t)*100; const bar=document.getElementById('progress-bar'); bar.style.width=p+'%'; bar.setAttribute('aria-valuenow',p); document.getElementById('status').innerHTML='<strong>'+m+'</strong> ('+st+'/'+t+')'; }

    async function ejecutar() {
        const id=document.getElementById('cr_id').value;
        const btn=document.querySelector('button');
        btn.disabled=true; btn.textContent='Procesando...';
        document.getElementById('resultado').innerHTML='';
        upd(0,4,'Iniciando...');

        try {
            // PASO 1: Generar XML v2.0.0
            upd(1,4,'Generando XML v2.0.0...');
            log('Paso 1: Generando XML v2.0.0...','processing');
            const r1=await fetch('comprobante_retencion_generar_xml_2_0_0.php?id='+id);
            const d1=await r1.json();
            if(!d1.ok) throw new Error('Error al generar XML: '+d1.error);
            log('XML generado: '+d1.archivo,'success');

            // PASO 2: Firmar XML (reusa firmar2.php de facturación)
            upd(2,4,'Firmando XML...');
            log('Paso 2: Firmando XML...','processing');
            const r2=await fetch('../md_facturacion/autorizacion/procesos/firmar2.php?id='+id);
            const d2=await r2.json();
            if(!d2.ok) throw new Error('Firmar: '+(d2.error||'Error en firma'));
            log('XML firmado correctamente.','success');

            // PASO 3: Enviar al SRI
            upd(3,4,'Enviando al SRI...');
            log('Paso 3: Enviando al SRI...','processing');
            const r3=await fetch('../md_facturacion/autorizacion/procesos/envio2.php?id='+id);
            const d3=await r3.json();
            if(d3[0]===0) throw new Error('Enviar: WebService SRI Inaccesible');
            if(d3[1]!=='RECIBIDA') throw new Error('Enviar: Error '+(d3[2]||'desconocido'));
            log('Enviado al SRI correctamente.','success');

            // PASO 4: Autorizar
            upd(4,4,'Autorizando en SRI...');
            log('Paso 4: Autorizando en SRI...','processing');
            const r4=await fetch('../md_facturacion/autorizacion/procesos/autoriza2.php?id='+id);
            const d4=await r4.json();
            if(d4[0]===0) throw new Error('Autorizar: WebService SRI Inaccesible');
            if(d4[1]!=='AUTORIZADO') throw new Error('Autorizar: '+(d4[2]||'Error desconocido'));
            log('AUTORIZADO correctamente.','success');
            log('Nro Autorización: '+d4[2],'success');

            document.getElementById('resultado').innerHTML+='<div class="alert alert-success mt-3"><strong>Comprobante de Retención AUTORIZADO</strong><br>Número de autorización: '+d4[2]+'</div>';
            Swal.fire({title:'Proceso completado',text:'Comprobante de Retención autorizado correctamente',icon:'success'});

        } catch(e) {
            log('Error: '+e.message,'error');
            Swal.fire({title:'Error',text:e.message,icon:'error'});
        } finally {
            btn.disabled=false; btn.textContent='Iniciar Proceso Completo';
        }
    }
    </script>
</body>
</html>
