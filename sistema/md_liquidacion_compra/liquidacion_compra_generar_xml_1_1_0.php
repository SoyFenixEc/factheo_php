<?php
/**
 * liquidacion_compra_generar_xml_1_1_0.php
 * Genera XML de Liquidación de Compra v1.1.0 con DOMDocument
 */
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/../md_facturacion/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) {
    mkdir($ruta_generados, 0755, true);
}

$liqui_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $liqui_id = (int)$_GET['id'];
}
if (!$liqui_id) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'ID de liquidación requerido.']));
}

function generarXMLLiquidacionSRI_1_1_0($liqui_id, $pdo, $ruta_generados) {
    try {
        // Obtener datos
        $sql = "
            SELECT 
                f.*,
                e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa, 
                e.contribuyente_especial, e.obligado_contabilidad,
                p.establecimiento, p.punto_emision, p.secuencial_liquidacion_compra,
                pv.nombre AS proveedor_nombre, 
                pv.ruc AS proveedor_ruc,
                pv.direccion AS proveedor_direccion,
                fp.codigo_sri AS forma_pago_sri
            FROM facturas f
            JOIN empresa e ON f.empresa_id = e.id
            JOIN punto_emision p ON f.punto_emision_id = p.id
            JOIN proveedores pv ON f.proveedor_id = pv.id
            JOIN formas_pago fp ON f.forma_pago_id = fp.id
            WHERE f.id = ? AND f.tipo_comprobante_id = '03'
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$liqui_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$factura) {
            throw new Exception("Liquidación no encontrada con ID: $liqui_id");
        }

        // Obtener detalles
        $sql_det = "
            SELECT 
                df.cantidad, df.precio_unitario, df.subtotal, df.iva, df.total,
                COALESCE(prod.codigo, '') AS codigo_principal,
                COALESCE(prod.nombre, 'PRODUCTO') AS descripcion
            FROM detalle_factura df
            LEFT JOIN productos prod ON df.producto_id = prod.id
            WHERE df.factura_id = ?
        ";
        $stmt_det = $pdo->prepare($sql_det);
        $stmt_det->execute([$liqui_id]);
        $detalles = $stmt_det->fetchAll();
        if (empty($detalles)) {
            throw new Exception("La liquidación no tiene productos registrados.");
        }

        // Formatear
        $fecha_emision = date('d/m/Y', strtotime($factura['fecha_emision']));
        $secuencial = str_pad($factura['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($factura['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($factura['punto_emision'], 3, '0', STR_PAD_LEFT);

        // Identificación del proveedor
        $id_len = strlen(trim($factura['proveedor_ruc']));
        if ($id_len == 13) {
            $tipoIdentificacion = '04'; // RUC
        } elseif ($id_len == 10) {
            $tipoIdentificacion = '05'; // Cédula
        } else {
            $tipoIdentificacion = '07'; // Otros
        }

        // IVA
        $codigo_porcentaje_iva = '2'; // IVA 12%
        $tarifa_iva = '15.0';
        // Determinar según el IVA configurado
        $iva_valor = floatval($factura['iva']);
        if ($iva_valor <= 5) {
            $codigo_porcentaje_iva = '0'; // No objeto
            $tarifa_iva = '0';
        } elseif ($iva_valor <= 12) {
            $codigo_porcentaje_iva = '2'; // 12%
            $tarifa_iva = '12.0';
        } elseif ($iva_valor <= 14) {
            $codigo_porcentaje_iva = '3'; // 14%
            $tarifa_iva = '14.0';
        } else {
            $codigo_porcentaje_iva = '4'; // 15%
            $tarifa_iva = '15.0';
        }

        // Nombre archivo
        $nombre_archivo = "LIQUIDACION_v1.1_{$liqui_id}_" . substr($factura['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        // DOMDocument
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        // Raíz
        $root = $dom->createElement('liquidacionCompra');
        $root->setAttribute('id', 'comprobante');
        $root->setAttribute('version', '1.1.0');
        $dom->appendChild($root);

        // infoTributaria
        $infoTrib = $dom->createElement('infoTributaria');
        $infoTrib->appendChild($dom->createElement('ambiente', '2'));
        $infoTrib->appendChild($dom->createElement('tipoEmision', '1'));

        $razonSocial = strtoupper(trim($factura['razon_social']));
        $infoTrib->appendChild($dom->createElement('razonSocial', htmlspecialchars($razonSocial, ENT_NOQUOTES, 'UTF-8')));
        if (!empty($factura['nombre_comercial'])) {
            $infoTrib->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($factura['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        }
        $infoTrib->appendChild($dom->createElement('ruc', $factura['ruc']));
        $infoTrib->appendChild($dom->createElement('claveAcceso', $factura['clave_acceso']));
        $infoTrib->appendChild($dom->createElement('codDoc', '03'));
        $infoTrib->appendChild($dom->createElement('estab', $estab));
        $infoTrib->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $infoTrib->appendChild($dom->createElement('secuencial', $secuencial));
        $infoTrib->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($factura['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        $root->appendChild($infoTrib);

        // infoLiquidacionCompra
        $infoLiq = $dom->createElement('infoLiquidacionCompra');
        $infoLiq->appendChild($dom->createElement('fechaEmision', $fecha_emision));
        $infoLiq->appendChild($dom->createElement('dirEstablecimiento', htmlspecialchars(trim($factura['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        if (!empty($factura['contribuyente_especial'])) {
            $infoLiq->appendChild($dom->createElement('contribuyenteEspecial', $factura['contribuyente_especial']));
        }
        $infoLiq->appendChild($dom->createElement('obligadoContabilidad', $factura['obligado_contabilidad']));
        $infoLiq->appendChild($dom->createElement('tipoIdentificacionProveedor', $tipoIdentificacion));
        $infoLiq->appendChild($dom->createElement('razonSocialProveedor', htmlspecialchars(trim($factura['proveedor_nombre']), ENT_NOQUOTES, 'UTF-8')));
        $infoLiq->appendChild($dom->createElement('identificacionProveedor', $factura['proveedor_ruc']));
        if (!empty($factura['proveedor_direccion'])) {
            $infoLiq->appendChild($dom->createElement('direccionProveedor', htmlspecialchars(trim($factura['proveedor_direccion']), ENT_NOQUOTES, 'UTF-8')));
        }

        $subtotalSinImpuestos = number_format($factura['subtotal1'], 2, '.', '');
        $infoLiq->appendChild($dom->createElement('totalSinImpuestos', $subtotalSinImpuestos));
        $infoLiq->appendChild($dom->createElement('totalDescuento', '0.00'));

        // codDocReembolso
        $infoLiq->appendChild($dom->createElement('codDocReembolso', '00'));

        // totalConImpuestos
        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $totalImpuesto = $dom->createElement('totalImpuesto');
        $totalImpuesto->appendChild($dom->createElement('codigo', '2'));
        $totalImpuesto->appendChild($dom->createElement('codigoPorcentaje', $codigo_porcentaje_iva));
        $baseImponible = number_format($factura['subtotal1'], 2, '.', '');
        $totalImpuesto->appendChild($dom->createElement('baseImponible', $baseImponible));
        $totalImpuesto->appendChild($dom->createElement('tarifa', $tarifa_iva));
        $totalImpuesto->appendChild($dom->createElement('valor', number_format($factura['valor_iva'], 2, '.', '')));
        $totalConImpuestos->appendChild($totalImpuesto);
        $infoLiq->appendChild($totalConImpuestos);

        $infoLiq->appendChild($dom->createElement('moneda', 'DOLAR'));

        // pagos
        $pagos = $dom->createElement('pagos');
        $pago = $dom->createElement('pago');
        $pago->appendChild($dom->createElement('formaPago', $factura['forma_pago_sri'] ?? '01'));
        $pago->appendChild($dom->createElement('total', number_format($factura['total'], 2, '.', '')));
        $pago->appendChild($dom->createElement('plazo', '0'));
        $pago->appendChild($dom->createElement('unidadTiempo', 'dias'));
        $pagos->appendChild($pago);
        $infoLiq->appendChild($pagos);

        $root->appendChild($infoLiq);

        // detalles
        $detallesNode = $dom->createElement('detalles');
        foreach ($detalles as $det) {
            $detalle = $dom->createElement('detalle');
            if (!empty($det['codigo_principal'])) {
                $detalle->appendChild($dom->createElement('codigoPrincipal', htmlspecialchars($det['codigo_principal'], ENT_NOQUOTES, 'UTF-8')));
            }
            $detalle->appendChild($dom->createElement('descripcion', htmlspecialchars($det['descripcion'], ENT_NOQUOTES, 'UTF-8')));
            $detalle->appendChild($dom->createElement('cantidad', number_format($det['cantidad'], 6, '.', '')));
            $detalle->appendChild($dom->createElement('precioUnitario', number_format($det['precio_unitario'], 6, '.', '')));
            $detalle->appendChild($dom->createElement('precioTotalSinImpuesto', number_format($det['subtotal'], 2, '.', '')));

            $impuestos = $dom->createElement('impuestos');
            $impuesto = $dom->createElement('impuesto');
            $impuesto->appendChild($dom->createElement('codigo', '2'));
            $impuesto->appendChild($dom->createElement('codigoPorcentaje', $codigo_porcentaje_iva));
            $impuesto->appendChild($dom->createElement('tarifa', $tarifa_iva));
            $impuesto->appendChild($dom->createElement('baseImponible', number_format($det['subtotal'], 2, '.', '')));
            $impuesto->appendChild($dom->createElement('valor', number_format($det['iva'], 2, '.', '')));
            $impuestos->appendChild($impuesto);
            $detalle->appendChild($impuestos);

            $detallesNode->appendChild($detalle);
        }
        $root->appendChild($detallesNode);

        // infoAdicional (opcional)
        $infoAdic = $dom->createElement('infoAdicional');
        $campo1 = $dom->createElement('campoAdicional', 'LIQUIDACIÓN DE COMPRA');
        $campo1->setAttribute('nombre', 'Tipo Documento');
        $infoAdic->appendChild($campo1);
        if (!empty($factura['comentarios'])) {
            $campo2 = $dom->createElement('campoAdicional', htmlspecialchars($factura['comentarios'], ENT_NOQUOTES, 'UTF-8'));
            $campo2->setAttribute('nombre', 'Observación');
            $infoAdic->appendChild($campo2);
        }
        $root->appendChild($infoAdic);

        // Guardar
        $dom->save($ruta_completa);

        // Actualizar BD
        $stmt_upd = $pdo->prepare("UPDATE facturas SET archivo_xml = ?, xml_generado = 1, estado_xml = 'GENERADO', fecha_actualizacion = NOW() WHERE id = ?");
        $stmt_upd->execute([$nombre_archivo, $liqui_id]);

        return [
            'ok' => true,
            'archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'clave_acceso' => $factura['clave_acceso'],
            'mensaje' => "XML Liquidación v1.1.0 generado: $nombre_archivo"
        ];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

$resultado = generarXMLLiquidacionSRI_1_1_0($liqui_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;
