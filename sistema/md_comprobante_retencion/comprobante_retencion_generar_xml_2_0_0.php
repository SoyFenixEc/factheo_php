<?php
/**
 * comprobante_retencion_generar_xml_2_0_0.php
 * Genera el XML de Comprobante de Retención v2.0.0 para SRI
 * Basado en Ficha Técnica v2.31 - Comprobante Retención v2.0.0
 * Compatible con firma electrónica XAdES_BES
 */
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/../md_facturacion/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) {
    mkdir($ruta_generados, 0755, true);
}

$cr_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $cr_id = (int)$_GET['id'];
}
if (!$cr_id) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'Debe proporcionar un ID de Comprobante de Retención.']));
}

function generarXMLComprobanteRetencion($cr_id, $pdo, $ruta_generados) {
    try {
        // Obtener datos del comprobante de retención
        $sql = "SELECT 
                    cr.*,
                    e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa,
                    e.contribuyente_especial, e.obligado_contabilidad,
                    p.establecimiento, p.punto_emision
                FROM comprobantes_retencion cr
                JOIN empresa e ON cr.empresa_id = e.id
                JOIN punto_emision p ON cr.punto_emision_id = p.id
                WHERE cr.id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$cr_id]);
        $cr = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$cr) throw new Exception("Comprobante de Retención no encontrado con ID: $cr_id");

        // Obtener detalles
        $sql_det = "SELECT * FROM detalle_retencion WHERE comprobante_retencion_id = ? ORDER BY id";
        $stmt_det = $pdo->prepare($sql_det);
        $stmt_det->execute([$cr_id]);
        $detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
        if (empty($detalles)) throw new Exception("El comprobante no tiene detalles registrados.");

        // Formatear datos
        $fecha_emision = date('d/m/Y', strtotime($cr['fecha_emision']));
        $secuencial = str_pad($cr['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($cr['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($cr['punto_emision'], 3, '0', STR_PAD_LEFT);

        $nombre_archivo = "RETENCION_v2.0.0_{$cr_id}_" . substr($cr['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        // Construir XML con DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        // Raíz: comprobanteRetencion
        $root = $dom->createElement('comprobanteRetencion');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '2.0.0');
        $dom->appendChild($root);

        // ==========================================
        // infoTributaria
        // ==========================================
        $infoTrib = $dom->createElement('infoTributaria');
        $ambiente = $cr['ambiente_id'] == 2 ? '2' : '1';
        $infoTrib->appendChild($dom->createElement('ambiente', $ambiente));
        $infoTrib->appendChild($dom->createElement('tipoEmision', '1'));

        $rs = strtoupper(trim($cr['razon_social']));
        $infoTrib->appendChild($dom->createElement('razonSocial', htmlspecialchars($rs, ENT_NOQUOTES, 'UTF-8')));
        if (!empty($cr['nombre_comercial'])) {
            $infoTrib->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($cr['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        }
        $infoTrib->appendChild($dom->createElement('ruc', $cr['ruc']));
        $infoTrib->appendChild($dom->createElement('claveAcceso', $cr['clave_acceso']));
        $infoTrib->appendChild($dom->createElement('codDoc', '07'));
        $infoTrib->appendChild($dom->createElement('estab', $estab));
        $infoTrib->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $infoTrib->appendChild($dom->createElement('secuencial', $secuencial));
        if (!empty($cr['dir_empresa'])) {
            $infoTrib->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($cr['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        $root->appendChild($infoTrib);

        // ==========================================
        // infoCompRetencion
        // ==========================================
        $infoCR = $dom->createElement('infoCompRetencion');
        $infoCR->appendChild($dom->createElement('fechaEmision', $fecha_emision));
        if (!empty($cr['dir_establecimiento'])) {
            $infoCR->appendChild($dom->createElement('dirEstablecimiento', htmlspecialchars(trim($cr['dir_establecimiento']), ENT_NOQUOTES, 'UTF-8')));
        }
        if (!empty($cr['contribuyente_especial'])) {
            $infoCR->appendChild($dom->createElement('contribuyenteEspecial', $cr['contribuyente_especial']));
        }
        $infoCR->appendChild($dom->createElement('obligadoContabilidad', $cr['obligado_contabilidad'] ?? 'NO'));
        $infoCR->appendChild($dom->createElement('tipoIdentificacionSujetoRetenido', $cr['tipo_identificacion_sujeto_retenido']));
        $infoCR->appendChild($dom->createElement('razonSocialSujetoRetenido', htmlspecialchars(trim($cr['razon_social_sujeto_retenido']), ENT_NOQUOTES, 'UTF-8')));
        $infoCR->appendChild($dom->createElement('identificacionSujetoRetenido', $cr['identificacion_sujeto_retenido']));
        $infoCR->appendChild($dom->createElement('periodoFiscal', $cr['periodo_fiscal']));
        $root->appendChild($infoCR);

        // ==========================================
        // docsSustento
        // ==========================================
        $docsSustento = $dom->createElement('docsSustento');

        // Agrupar detalles por num_documento (cada docSustento puede tener varias retenciones)
        $docsAgrupados = [];
        foreach ($detalles as $det) {
            $key = $det['num_documento'];
            if (!isset($docsAgrupados[$key])) {
                $docsAgrupados[$key] = [
                    'datos' => $det,
                    'retenciones' => []
                ];
            }
            if ($det['codigo_retencion'] > 0) {
                $docsAgrupados[$key]['retenciones'][] = $det;
            }
        }

        foreach ($docsAgrupados as $key => $doc) {
            $dd = $doc['datos'];
            $docSustento = $dom->createElement('docSustento');

            // codSustento: catálogo de sustento tributario
            $docSustento->appendChild($dom->createElement('codSustento', $dd['cod_sustento']));

            // codDocNR: tipo de comprobante del documento sustento
            $docSustento->appendChild($dom->createElement('codDocNR', $dd['cod_doc_nr']));

            // numDocumento: número del documento sustento
            $docSustento->appendChild($dom->createElement('numDocumento', $dd['num_documento']));

            // fechaEmisionDocSustento
            $fecDoc = date('d/m/Y', strtotime($dd['fecha_emision_doc_sustento']));
            $docSustento->appendChild($dom->createElement('fechaEmisionDocSustento', $fecDoc));

            // numAutDocumento: número de autorización del documento sustento
            $docSustento->appendChild($dom->createElement('numAutDocumento', $dd['num_aut_documento']));

            // fechaAutorizacionDocSustento
            if (!empty($dd['fecha_autorizacion_doc_sustento'])) {
                $fecAut = date('d/m/Y', strtotime($dd['fecha_autorizacion_doc_sustento']));
                $docSustento->appendChild($dom->createElement('fechaAutorizacionDocSustento', $fecAut));
            }

            // montoTotal del documento sustento
            $docSustento->appendChild($dom->createElement('montoTotal', number_format($dd['monto_total'], 2, '.', '')));

            // pagoLocExt: 01=local, 02=exterior
            $docSustento->appendChild($dom->createElement('pagoLocExt', $dd['pago_loc_ext']));

            // totalSinImpuestos
            $docSustento->appendChild($dom->createElement('totalSinImpuestos', number_format($dd['total_sin_impuestos'], 2, '.', '')));

            // --- impuestosDocSustento ---
            // Agrupar impuestos del documento (evitar duplicados por misma combinación)
            $impKey = $dd['cod_impuesto_doc_sustento'] . '_' . $dd['codigo_porcentaje'];
            $impuestosMap = [];
            $impuestosMap[$impKey] = [
                'codImpuestoDocSustento' => $dd['cod_impuesto_doc_sustento'],
                'codigoPorcentaje' => $dd['codigo_porcentaje'],
                'baseImponible' => $dd['base_imponible'],
                'tarifa' => $dd['tarifa'],
                'valorImpuesto' => $dd['valor_impuesto']
            ];

            // Solo agregar si hay datos significativos
            $impuestosDocSustento = $dom->createElement('impuestosDocSustento');
            foreach ($impuestosMap as $imp) {
                if (floatval($imp['baseImponible']) > 0 || floatval($imp['valorImpuesto']) > 0) {
                    $impuesto = $dom->createElement('impuestoDocSustento');
                    $impuesto->appendChild($dom->createElement('codImpuestoDocSustento', $imp['codImpuestoDocSustento']));
                    $impuesto->appendChild($dom->createElement('codigoPorcentaje', $imp['codigoPorcentaje']));
                    $impuesto->appendChild($dom->createElement('baseImponible', number_format($imp['baseImponible'], 2, '.', '')));
                    $impuesto->appendChild($dom->createElement('tarifa', number_format($imp['tarifa'], 2, '.', '')));
                    $impuesto->appendChild($dom->createElement('valorImpuesto', number_format($imp['valorImpuesto'], 2, '.', '')));
                    $impuestosDocSustento->appendChild($impuesto);
                }
            }
            $docSustento->appendChild($impuestosDocSustento);

            // --- retenciones ---
            // Cada docSustento puede tener múltiples retenciones (Renta, IVA, ISD)
            if (!empty($doc['retenciones'])) {
                $retencionesNode = $dom->createElement('retenciones');
                foreach ($doc['retenciones'] as $ret) {
                    // Obtener el código de retención del catálogo
                    $st = $pdo->prepare("SELECT codigo_retencion, nombre, porcentaje, codigo_impuesto FROM impuestos_retencion WHERE id = ?");
                    $st->execute([$ret['codigo_retencion']]);
                    $ir = $st->fetch(PDO::FETCH_ASSOC);
                    $codRet = $ir['codigo_retencion'] ?? $ret['codigo_retencion'];
                    $porcRet = $ir['porcentaje'] ?? $ret['porcentaje_retener'];

                    $retencion = $dom->createElement('retencion');

                    // codigo: código del impuesto (1=Renta, 2=IVA, 6=ISD)
                    $retencion->appendChild($dom->createElement('codigo', $ret['codigo_impuesto_retencion']));

                    // codigoRetencion: código del porcentaje específico (ej: 304, 201, etc.)
                    $retencion->appendChild($dom->createElement('codigoRetencion', $codRet));

                    // baseImponible: base sobre la que se aplica la retención
                    $retencion->appendChild($dom->createElement('baseImponible', number_format($ret['base_imponible_retencion'], 2, '.', '')));

                    // porcentajeRetener: el porcentaje aplicado
                    $retencion->appendChild($dom->createElement('porcentajeRetener', number_format($porcRet, 2, '.', '')));

                    // valorRetenido: el monto retenido calculado
                    $retencion->appendChild($dom->createElement('valorRetenido', number_format($ret['valor_retenido'], 2, '.', '')));

                    // codDocSustento: (opcional) referencia al documento sustento
                    $retencion->appendChild($dom->createElement('codDocSustento', $dd['cod_doc_nr']));

                    // numDocSustento: (opcional) número del doc sustento
                    $retencion->appendChild($dom->createElement('numDocSustento', $dd['num_documento']));

                    // fechaEmisionDocSustento: (opcional) fecha del doc sustento
                    $retencion->appendChild($dom->createElement('fechaEmisionDocSustento', $fecDoc));

                    $retencionesNode->appendChild($retencion);
                }
                $docSustento->appendChild($retencionesNode);
            }

            $docsSustento->appendChild($docSustento);
        }
        $root->appendChild($docsSustento);

        // ==========================================
        // infoAdicional (opcional)
        // ==========================================
        $infoAdicional = $dom->createElement('infoAdicional');

        // Campo: Proveedor
        $campo1 = $dom->createElement('campoAdicional', htmlspecialchars(trim($cr['razon_social_sujeto_retenido']), ENT_NOQUOTES, 'UTF-8'));
        $campo1->setAttribute('nombre', 'Proveedor');
        $infoAdicional->appendChild($campo1);

        if (!empty($cr['comentarios'])) {
            $campo2 = $dom->createElement('campoAdicional', htmlspecialchars(trim($cr['comentarios']), ENT_NOQUOTES, 'UTF-8'));
            $campo2->setAttribute('nombre', 'Observaciones');
            $infoAdicional->appendChild($campo2);
        }

        $campo3 = $dom->createElement('campoAdicional', $cr['periodo_fiscal']);
        $campo3->setAttribute('nombre', 'Periodo Fiscal');
        $infoAdicional->appendChild($campo3);

        $root->appendChild($infoAdicional);

        // Guardar XML
        $dom->save($ruta_completa);

        // Actualizar BD
        $pdo->prepare("UPDATE comprobantes_retencion SET archivo_xml = ?, xml_generado = 1, estado_xml = 'GENERADO', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$nombre_archivo, $cr_id]);

        return [
            'ok' => true,
            'archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'clave_acceso' => $cr['clave_acceso'],
            'mensaje' => "XML Comprobante de Retención v2.0.0 generado: $nombre_archivo"
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$resultado = generarXMLComprobanteRetencion($cr_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;
