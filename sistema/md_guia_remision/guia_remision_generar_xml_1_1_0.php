<?php
/**
 * guia_remision_generar_xml_1_1_0.php
 * Genera el XML de Guía de Remisión v1.1.0 para SRI
 * Compatible con firma electrónica XAdES_BES
 */
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/../md_facturacion/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) {
    mkdir($ruta_generados, 0755, true);
}

$guia_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $guia_id = (int)$_GET['id'];
}
if (!$guia_id) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'Debe proporcionar un ID de Guía de Remisión.']));
}

function generarXMLGuiaRemision($guia_id, $pdo, $ruta_generados) {
    try {
        // Obtener datos de la guía
        $sql = "SELECT 
                    g.*,
                    e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa,
                    e.contribuyente_especial, e.obligado_contabilidad,
                    p.establecimiento, p.punto_emision, p.secuencial_guia_remision
                FROM guias_remision g
                JOIN empresa e ON g.empresa_id = e.id
                JOIN punto_emision p ON g.punto_emision_id = p.id
                WHERE g.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$guia_id]);
        $guia = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$guia) throw new Exception("Guía de Remisión no encontrada con ID: $guia_id");

        // Obtener detalles
        $sql_det = "SELECT d.codigo_interno, d.codigo_adicional, d.descripcion, d.cantidad,
                           COALESCE(prod.codigo, d.codigo_interno, '') AS cod_principal
                    FROM detalle_guia_remision d
                    LEFT JOIN productos prod ON d.producto_id = prod.id
                    WHERE d.guia_remision_id = ?";
        $stmt_det = $pdo->prepare($sql_det);
        $stmt_det->execute([$guia_id]);
        $detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
        if (empty($detalles)) throw new Exception("La Guía de Remisión no tiene productos.");

        // Formatear datos
        $secuencial = str_pad($guia['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($guia['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($guia['punto_emision'], 3, '0', STR_PAD_LEFT);

        $nombre_archivo = "GUIA_v1.1_{$guia_id}_" . substr($guia['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        // Construir XML con DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        // Raíz: guiaRemision
        $root = $dom->createElement('guiaRemision');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $dom->appendChild($root);

        // ========== infoTributaria ==========
        $infoTrib = $dom->createElement('infoTributaria');
        $ambiente = $guia['ambiente_id'] == 2 ? '2' : '1';
        $infoTrib->appendChild($dom->createElement('ambiente', $ambiente));
        $infoTrib->appendChild($dom->createElement('tipoEmision', '1'));

        $rs = strtoupper(trim($guia['razon_social']));
        $infoTrib->appendChild($dom->createElement('razonSocial', htmlspecialchars($rs, ENT_NOQUOTES, 'UTF-8')));
        if (!empty($guia['nombre_comercial'])) {
            $infoTrib->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($guia['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        }
        $infoTrib->appendChild($dom->createElement('ruc', $guia['ruc']));
        $infoTrib->appendChild($dom->createElement('claveAcceso', $guia['clave_acceso']));
        $infoTrib->appendChild($dom->createElement('codDoc', '06'));
        $infoTrib->appendChild($dom->createElement('estab', $estab));
        $infoTrib->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $infoTrib->appendChild($dom->createElement('secuencial', $secuencial));
        if (!empty($guia['dir_empresa'])) {
            $infoTrib->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($guia['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        $root->appendChild($infoTrib);

        // ========== infoGuiaRemision ==========
        $infoGR = $dom->createElement('infoGuiaRemision');

        // Dirección de partida
        $infoGR->appendChild($dom->createElement('dirPartida', htmlspecialchars(trim($guia['dir_partida']), ENT_NOQUOTES, 'UTF-8')));

        // Razón social transportista
        $infoGR->appendChild($dom->createElement('razonSocialTransportista', htmlspecialchars(trim($guia['razon_social_transportista']), ENT_NOQUOTES, 'UTF-8')));

        // Tipo identificación transportista
        $tipoIdTrans = $guia['tipo_identificacion_transportista'];
        if (empty($tipoIdTrans)) {
            $tipoIdTrans = '05';
            if (strlen($guia['ruc_transportista']) == 13) $tipoIdTrans = '04';
            elseif (strlen($guia['ruc_transportista']) == 10) $tipoIdTrans = '05';
        }
        $infoGR->appendChild($dom->createElement('tipoIdentificacionTransportista', $tipoIdTrans));

        // RUC transportista
        $infoGR->appendChild($dom->createElement('rucTransportista', $guia['ruc_transportista']));

        // Fechas de transporte en formato dd/mm/YYYY
        $fechaIni = date('d/m/Y', strtotime($guia['fecha_inicio_transporte']));
        $fechaFin = date('d/m/Y', strtotime($guia['fecha_fin_transporte']));
        $infoGR->appendChild($dom->createElement('fechaIniTransporte', $fechaIni));
        $infoGR->appendChild($dom->createElement('fechaFinTransporte', $fechaFin));

        // Placa
        $infoGR->appendChild($dom->createElement('placa', strtoupper(trim($guia['placa']))));

        $root->appendChild($infoGR);

        // ========== destinatarios ==========
        $destinatarios = $dom->createElement('destinatarios');
        $destinatario = $dom->createElement('destinatario');

        // Identificación destinatario
        $destinatario->appendChild($dom->createElement('identificacionDestinatario', $guia['identificacion_destinatario']));

        // Razón social destinatario
        $destinatario->appendChild($dom->createElement('razonSocialDestinatario', htmlspecialchars(trim($guia['razon_social_destinatario']), ENT_NOQUOTES, 'UTF-8')));

        // Dirección destinatario
        $destinatario->appendChild($dom->createElement('dirDestinatario', htmlspecialchars(trim($guia['dir_destinatario']), ENT_NOQUOTES, 'UTF-8')));

        // Motivo de traslado
        $destinatario->appendChild($dom->createElement('motivoTraslado', htmlspecialchars(trim($guia['motivo_traslado']), ENT_NOQUOTES, 'UTF-8')));

        // Documento aduanero único (opcional)
        if (!empty($guia['doc_aduano_unico'])) {
            $destinatario->appendChild($dom->createElement('docAduaneroUnico', htmlspecialchars(trim($guia['doc_aduano_unico']), ENT_NOQUOTES, 'UTF-8')));
        }

        // Código establecimiento destino (opcional)
        if (!empty($guia['cod_estab_destino'])) {
            $destinatario->appendChild($dom->createElement('codEstabDestino', str_pad($guia['cod_estab_destino'], 3, '0', STR_PAD_LEFT)));
        }

        // Ruta (opcional)
        if (!empty($guia['ruta'])) {
            $destinatario->appendChild($dom->createElement('ruta', htmlspecialchars(trim($guia['ruta']), ENT_NOQUOTES, 'UTF-8')));
        }

        // Documento sustento
        if (!empty($guia['cod_doc_sustento'])) {
            $destinatario->appendChild($dom->createElement('codDocSustento', $guia['cod_doc_sustento']));
        }
        if (!empty($guia['num_doc_sustento'])) {
            $destinatario->appendChild($dom->createElement('numDocSustento', $guia['num_doc_sustento']));
        }
        if (!empty($guia['num_aut_doc_sustento'])) {
            $destinatario->appendChild($dom->createElement('numAutDocSustento', $guia['num_aut_doc_sustento']));
        }
        if (!empty($guia['fecha_emision_doc_sustento'])) {
            $fechaDoc = date('d/m/Y', strtotime($guia['fecha_emision_doc_sustento']));
            $destinatario->appendChild($dom->createElement('fechaEmisionDocSustento', $fechaDoc));
        }

        // ========== detalles del destinatario ==========
        $detallesNode = $dom->createElement('detalles');
        foreach ($detalles as $det) {
            $detalle = $dom->createElement('detalle');

            // codigoInterno
            $codInt = !empty($det['codigo_interno']) ? $det['codigo_interno'] : $det['cod_principal'];
            if (!empty($codInt)) {
                $detalle->appendChild($dom->createElement('codigoInterno', htmlspecialchars($codInt, ENT_NOQUOTES, 'UTF-8')));
            }

            // codigoAdicional (opcional)
            if (!empty($det['codigo_adicional'])) {
                $detalle->appendChild($dom->createElement('codigoAdicional', htmlspecialchars($det['codigo_adicional'], ENT_NOQUOTES, 'UTF-8')));
            }

            // descripcion
            $detalle->appendChild($dom->createElement('descripcion', htmlspecialchars($det['descripcion'], ENT_NOQUOTES, 'UTF-8')));

            // cantidad
            $detalle->appendChild($dom->createElement('cantidad', number_format($det['cantidad'], 6, '.', '')));

            $detallesNode->appendChild($detalle);
        }
        $destinatario->appendChild($detallesNode);
        $destinatarios->appendChild($destinatario);
        $root->appendChild($destinatarios);

        // ========== infoAdicional (opcional) ==========
        // Se puede agregar info adicional si se desea
        if (!empty($guia['comentarios'])) {
            $infoAd = $dom->createElement('infoAdicional');
            $campoAd = $dom->createElement('campoAdicional', htmlspecialchars(trim($guia['comentarios']), ENT_NOQUOTES, 'UTF-8'));
            $campoAd->setAttribute('nombre', 'Comentarios');
            $infoAd->appendChild($campoAd);
            $root->appendChild($infoAd);
        }

        // Guardar XML
        $dom->save($ruta_completa);

        // Actualizar BD
        $pdo->prepare("UPDATE guias_remision SET archivo_xml = ?, xml_generado = 1, estado_xml = 'GENERADO', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$nombre_archivo, $guia_id]);

        return [
            'ok' => true,
            'archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'clave_acceso' => $guia['clave_acceso'],
            'mensaje' => "XML Guía de Remisión v1.1.0 generado: $nombre_archivo"
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$resultado = generarXMLGuiaRemision($guia_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;
