<?php
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/../md_facturacion/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) mkdir($ruta_generados, 0755, true);

$nd_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$nd_id) { http_response_code(400); die(json_encode(['ok' => false, 'error' => 'ID requerido.'])); }

function generarXMLNotaDebito($nd_id, $pdo, $ruta_generados) {
    try {
        $sql = "SELECT f.*, e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa,
                       e.contribuyente_especial, e.obligado_contabilidad,
                       p.establecimiento, p.punto_emision, p.secuencial_factura,
                       c.razon_social AS cli_rs, c.identificacion, c.direccion AS dir_cliente,
                       fp.codigo_sri AS forma_pago_sri
                FROM facturas f JOIN empresa e ON f.empresa_id = e.id
                JOIN punto_emision p ON f.punto_emision_id = p.id
                JOIN clientes c ON f.cliente_id = c.id
                JOIN formas_pago fp ON f.forma_pago_id = fp.id
                WHERE f.id = ? AND f.tipo_comprobante_id = '05'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nd_id]);
        $nd = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$nd) throw new Exception("Nota de Débito no encontrada: $nd_id");

        // Detalles (productos)
        $stmt_d = $pdo->prepare("SELECT df.*, COALESCE(p.codigo,'') AS codigo_principal, COALESCE(p.nombre,'PRODUCTO') AS descripcion FROM detalle_factura df LEFT JOIN productos p ON df.producto_id = p.id WHERE df.factura_id = ?");
        $stmt_d->execute([$nd_id]);
        $detalles = $stmt_d->fetchAll(PDO::FETCH_ASSOC);
        if (empty($detalles)) throw new Exception("Sin productos.");

        $fecha_emision = date('d/m/Y', strtotime($nd['fecha_emision']));
        $secuencial = str_pad($nd['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($nd['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($nd['punto_emision'], 3, '0', STR_PAD_LEFT);
        $tarifa_iva = '15.00'; // 2 decimales

        $nombre_archivo = "NOTADEBITO_v1.0_{$nd_id}_" . substr($nd['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;

        $root = $dom->createElement('notaDebito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.0.0');
        $dom->appendChild($root);

        // infoTributaria
        $it = $dom->createElement('infoTributaria');
        $it->appendChild($dom->createElement('ambiente', $nd['ambiente_id'] == 2 ? '2' : '1'));
        $it->appendChild($dom->createElement('tipoEmision', '1'));
        $it->appendChild($dom->createElement('razonSocial', htmlspecialchars(strtoupper(trim($nd['razon_social'])), ENT_NOQUOTES, 'UTF-8')));
        if (!empty($nd['nombre_comercial']))
            $it->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($nd['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        $it->appendChild($dom->createElement('ruc', $nd['ruc']));
        $it->appendChild($dom->createElement('claveAcceso', $nd['clave_acceso']));
        $it->appendChild($dom->createElement('codDoc', '05'));
        $it->appendChild($dom->createElement('estab', $estab));
        $it->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $it->appendChild($dom->createElement('secuencial', $secuencial));
        if (!empty($nd['dir_empresa']))
            $it->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($nd['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        $root->appendChild($it);

        // infoNotaDebito
        $ind = $dom->createElement('infoNotaDebito');
        $ind->appendChild($dom->createElement('fechaEmision', $fecha_emision));
        if (!empty($nd['dir_empresa']))
            $ind->appendChild($dom->createElement('dirEstablecimiento', htmlspecialchars(trim($nd['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));

        $tipoId = '05';
        if (strlen($nd['identificacion']) == 13) $tipoId = '04';
        elseif (strlen($nd['identificacion']) == 10) $tipoId = '05';
        else $tipoId = '06';
        $ind->appendChild($dom->createElement('tipoIdentificacionComprador', $tipoId));
        $ind->appendChild($dom->createElement('razonSocialComprador', htmlspecialchars(trim($nd['cli_rs']), ENT_NOQUOTES, 'UTF-8')));
        $ind->appendChild($dom->createElement('identificacionComprador', $nd['identificacion']));
        if (!empty($nd['contribuyente_especial']))
            $ind->appendChild($dom->createElement('contribuyenteEspecial', $nd['contribuyente_especial']));
        $ind->appendChild($dom->createElement('obligadoContabilidad', $nd['obligado_contabilidad'] ?? 'NO'));

        // Documento modificado
        $ind->appendChild($dom->createElement('codDocModificado', '01'));
        $ind->appendChild($dom->createElement('numDocModificado', $nd['numDocModificado']));
        $fecha_sustento = date('d/m/Y', strtotime($nd['fechaEmisionDocSustento'] ?: $nd['fecha_emision']));
        $ind->appendChild($dom->createElement('fechaEmisionDocSustento', $fecha_sustento));

        $ind->appendChild($dom->createElement('totalSinImpuestos', number_format($nd['subtotal2'], 2, '.', '')));

        // impuestos (inside infoNotaDebito for ND)
        $impuestos = $dom->createElement('impuestos');
        $impuesto = $dom->createElement('impuesto');
        $impuesto->appendChild($dom->createElement('codigo', '2'));
        $impuesto->appendChild($dom->createElement('codigoPorcentaje', '4'));
        $impuesto->appendChild($dom->createElement('tarifa', $tarifa_iva));
        $impuesto->appendChild($dom->createElement('baseImponible', number_format($nd['subtotal2'], 2, '.', '')));
        $impuesto->appendChild($dom->createElement('valor', number_format($nd['valor_iva'], 2, '.', '')));
        $impuestos->appendChild($impuesto);
        $ind->appendChild($impuestos);

        $ind->appendChild($dom->createElement('valorTotal', number_format($nd['total'], 2, '.', '')));

        // pagos
        $pagos = $dom->createElement('pagos');
        $pago = $dom->createElement('pago');
        $pago->appendChild($dom->createElement('formaPago', '01'));
        $pago->appendChild($dom->createElement('total', number_format($nd['total'], 2, '.', '')));
        $pagos->appendChild($pago);
        $ind->appendChild($pagos);

        $root->appendChild($ind);

        // motivos (instead of detalles)
        $motivos = $dom->createElement('motivos');
        foreach ($detalles as $det) {
            $motivo = $dom->createElement('motivo');
            $motivo->appendChild($dom->createElement('razon', htmlspecialchars($det['descripcion'], ENT_NOQUOTES, 'UTF-8')));
            $motivo->appendChild($dom->createElement('valor', number_format($det['subtotal'], 2, '.', '')));
            $motivos->appendChild($motivo);
        }
        $root->appendChild($motivos);

        // infoAdicional
        $infoAdic = $dom->createElement('infoAdicional');
        $ca = $dom->createElement('campoAdicional', htmlspecialchars($nd['motivo'] ?? '', ENT_NOQUOTES, 'UTF-8'));
        $ca->setAttribute('nombre', 'Motivo');
        $infoAdic->appendChild($ca);
        $root->appendChild($infoAdic);

        $dom->save($ruta_completa);

        $pdo->prepare("UPDATE facturas SET archivo_xml = ?, xml_generado = 1, estado_xml = 'GENERADO', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$nombre_archivo, $nd_id]);

        return ['ok' => true, 'archivo' => $nombre_archivo, 'ruta' => $ruta_completa, 'clave_acceso' => $nd['clave_acceso'],
                'mensaje' => "XML Nota de Débito v1.1.0 generado: $nombre_archivo"];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$resultado = generarXMLNotaDebito($nd_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;
