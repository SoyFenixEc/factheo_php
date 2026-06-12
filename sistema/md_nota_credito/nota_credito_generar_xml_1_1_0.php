<?php
/**
 * nota_credito_generar_xml_1_1_0.php
 * Genera el XML de Nota de Crédito v1.1.0 para SRI
 * Compatible con firma electrónica
 */
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/../md_facturacion/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) {
    mkdir($ruta_generados, 0755, true);
}

$nc_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $nc_id = (int)$_GET['id'];
}
if (!$nc_id) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'Debe proporcionar un ID de Nota de Crédito.']));
}

function generarXMLNotaCredito($nc_id, $pdo, $ruta_generados) {
    try {
        // Obtener datos
        $sql = "SELECT 
                    f.*,
                    e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa,
                    e.contribuyente_especial, e.obligado_contabilidad,
                    p.establecimiento, p.punto_emision, p.secuencial_factura,
                    c.razon_social AS razon_social_cliente,
                    c.identificacion,
                    c.direccion AS dir_cliente,
                    fp.codigo_sri AS forma_pago_sri
                FROM facturas f
                JOIN empresa e ON f.empresa_id = e.id
                JOIN punto_emision p ON f.punto_emision_id = p.id
                JOIN clientes c ON f.cliente_id = c.id
                JOIN formas_pago fp ON f.forma_pago_id = fp.id
                WHERE f.id = ? AND f.tipo_comprobante_id = '04'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$nc_id]);
        $nc = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$nc) throw new Exception("Nota de Crédito no encontrada con ID: $nc_id");

        // Obtener detalles
        $sql_det = "SELECT df.cantidad, df.precio_unitario, df.subtotal, df.iva, df.total,
                           COALESCE(prod.codigo, '') AS codigo_principal,
                           COALESCE(prod.nombre, 'PRODUCTO') AS descripcion
                    FROM detalle_factura df
                    LEFT JOIN productos prod ON df.producto_id = prod.id
                    WHERE df.factura_id = ?";
        $stmt_det = $pdo->prepare($sql_det);
        $stmt_det->execute([$nc_id]);
        $detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
        if (empty($detalles)) throw new Exception("La Nota de Crédito no tiene productos.");

        // Formatear datos
        $fecha_emision = date('d/m/Y', strtotime($nc['fecha_emision']));
        $secuencial = str_pad($nc['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($nc['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($nc['punto_emision'], 3, '0', STR_PAD_LEFT);
        $tarifa_iva = '15.0';

        $nombre_archivo = "NOTACREDITO_v1.1_{$nc_id}_" . substr($nc['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        // Construir XML con DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        // Raíz: notaCredito
        $root = $dom->createElement('notaCredito');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $dom->appendChild($root);

        // infoTributaria
        $infoTrib = $dom->createElement('infoTributaria');
        $ambiente = $nc['ambiente_id'] == 2 ? '2' : '1';
        $infoTrib->appendChild($dom->createElement('ambiente', $ambiente));
        $infoTrib->appendChild($dom->createElement('tipoEmision', '1'));

        $rs = strtoupper(trim($nc['razon_social']));
        $infoTrib->appendChild($dom->createElement('razonSocial', htmlspecialchars($rs, ENT_NOQUOTES, 'UTF-8')));
        if (!empty($nc['nombre_comercial'])) {
            $infoTrib->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($nc['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        }
        $infoTrib->appendChild($dom->createElement('ruc', $nc['ruc']));
        $infoTrib->appendChild($dom->createElement('claveAcceso', $nc['clave_acceso']));
        $infoTrib->appendChild($dom->createElement('codDoc', '04'));
        $infoTrib->appendChild($dom->createElement('estab', $estab));
        $infoTrib->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $infoTrib->appendChild($dom->createElement('secuencial', $secuencial));
        if (!empty($nc['dir_empresa'])) {
            $infoTrib->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($nc['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        $root->appendChild($infoTrib);

        // infoNotaCredito
        $infoNC = $dom->createElement('infoNotaCredito');
        $infoNC->appendChild($dom->createElement('fechaEmision', $fecha_emision));
        if (!empty($nc['dir_empresa'])) {
            $infoNC->appendChild($dom->createElement('dirEstablecimiento', htmlspecialchars(trim($nc['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        // Tipo identificación: 04=RUC(13dig), 05=Cédula(10dig), 06=Pasaporte, 07=ConsumidorFinal
        $tipoId = '05';
        if (strlen($nc['identificacion']) == 13) $tipoId = '04';
        elseif (strlen($nc['identificacion']) == 10) $tipoId = '05';
        else $tipoId = '06';
        $infoNC->appendChild($dom->createElement('tipoIdentificacionComprador', $tipoId));
        $infoNC->appendChild($dom->createElement('razonSocialComprador', htmlspecialchars(trim($nc['razon_social_cliente']), ENT_NOQUOTES, 'UTF-8')));
        $infoNC->appendChild($dom->createElement('identificacionComprador', $nc['identificacion']));

        if (!empty($nc['contribuyente_especial'])) {
            $infoNC->appendChild($dom->createElement('contribuyenteEspecial', $nc['contribuyente_especial']));
        }
        $infoNC->appendChild($dom->createElement('obligadoContabilidad', $nc['obligado_contabilidad'] ?? 'NO'));


        // Documento modificado
        $infoNC->appendChild($dom->createElement('codDocModificado', '01'));
        $infoNC->appendChild($dom->createElement('numDocModificado', $nc['numDocModificado']));
        $fecha_sustento = date('d/m/Y', strtotime($nc['fechaEmisionDocSustento'] ?: $nc['fecha_emision']));
        $infoNC->appendChild($dom->createElement('fechaEmisionDocSustento', $fecha_sustento));
        $infoNC->appendChild($dom->createElement('totalSinImpuestos', number_format($nc['subtotal2'], 2, '.', '')));
        $valor_mod = number_format($nc['total'], 2, '.', '');
        $infoNC->appendChild($dom->createElement('valorModificacion', $valor_mod));
        $infoNC->appendChild($dom->createElement('moneda', 'DOLAR'));

        // totalConImpuestos
        $totalCI = $dom->createElement('totalConImpuestos');
        $totalImp = $dom->createElement('totalImpuesto');
        $totalImp->appendChild($dom->createElement('codigo', '2'));
        $totalImp->appendChild($dom->createElement('codigoPorcentaje', '4')); // 4=15% según tabla 17
        $totalImp->appendChild($dom->createElement('baseImponible', number_format($nc['subtotal2'], 2, '.', '')));
        $totalImp->appendChild($dom->createElement('valor', number_format($nc['valor_iva'], 2, '.', '')));
        $totalCI->appendChild($totalImp);
        $infoNC->appendChild($totalCI);

        $infoNC->appendChild($dom->createElement('motivo', htmlspecialchars($nc['motivo'] ?? 'DEVOLUCION', ENT_NOQUOTES, 'UTF-8')));
        $root->appendChild($infoNC);

        // detalles
        $detallesNode = $dom->createElement('detalles');
        foreach ($detalles as $det) {
            $detalle = $dom->createElement('detalle');
            if (!empty($det['codigo_principal'])) {
                $detalle->appendChild($dom->createElement('codigoInterno', htmlspecialchars($det['codigo_principal'], ENT_NOQUOTES, 'UTF-8')));
            }
            $detalle->appendChild($dom->createElement('descripcion', htmlspecialchars($det['descripcion'], ENT_NOQUOTES, 'UTF-8')));
            $detalle->appendChild($dom->createElement('cantidad', number_format($det['cantidad'], 6, '.', '')));
            $detalle->appendChild($dom->createElement('precioUnitario', number_format($det['precio_unitario'], 6, '.', '')));
            $detalle->appendChild($dom->createElement('descuento', '0.00'));
            $detalle->appendChild($dom->createElement('precioTotalSinImpuesto', number_format($det['subtotal'], 2, '.', '')));

            $impuestos = $dom->createElement('impuestos');
            $impuesto = $dom->createElement('impuesto');
            $impuesto->appendChild($dom->createElement('codigo', '2'));
            $impuesto->appendChild($dom->createElement('codigoPorcentaje', '4'));
            $impuesto->appendChild($dom->createElement('tarifa', $tarifa_iva));
            $impuesto->appendChild($dom->createElement('baseImponible', number_format($det['subtotal'], 2, '.', '')));
            $impuesto->appendChild($dom->createElement('valor', number_format($det['iva'], 2, '.', '')));
            $impuestos->appendChild($impuesto);
            $detalle->appendChild($impuestos);

            $detallesNode->appendChild($detalle);
        }
        $root->appendChild($detallesNode);

        // Guardar XML
        $dom->save($ruta_completa);

        // Actualizar BD
        $pdo->prepare("UPDATE facturas SET archivo_xml = ?, xml_generado = 1, estado_xml = 'GENERADO', fecha_actualizacion = NOW() WHERE id = ?")
            ->execute([$nombre_archivo, $nc_id]);

        return [
            'ok' => true,
            'archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'clave_acceso' => $nc['clave_acceso'],
            'mensaje' => "XML Nota de Crédito v1.1.0 generado: $nombre_archivo"
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$resultado = generarXMLNotaCredito($nc_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;
