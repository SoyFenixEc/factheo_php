<?php
/**
 * facturacion_generar_xml_1_1_0.php
 * Genera el XML de una factura electrónica v1.1.0 con DOMDocument
 * CORREGIDO: usa secuencial de la factura, tipo identificación desde BD,
 * ambientes dinámicos, y validación de redondeo exacto SRI
 */
require_once('../md_config/conexion.php');

$ruta_generados = __DIR__ . '/autorizacion/comprobantes/generados/';
if (!is_dir($ruta_generados)) {
    mkdir($ruta_generados, 0755, true);
}

$factura_id = null;
if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $factura_id = (int)$_GET['id'];
}
if (!$factura_id) {
    http_response_code(400);
    die(json_encode(['ok' => false, 'error' => 'Debe proporcionar un ID de factura válido.']));
}

function generarXMLFacturaSRI_1_1_0_Corregido($factura_id, $pdo, $ruta_generados) {
    try {
        // Obtener datos de la factura
        $sql = "
            SELECT 
                f.*,
                e.razon_social, e.nombre_comercial, e.ruc, e.direccion AS dir_empresa, 
                e.contribuyente_especial, e.obligado_contabilidad,
                p.establecimiento, p.punto_emision,
                c.razon_social AS razon_social_cliente, 
                c.identificacion, 
                c.direccion AS dir_cliente,
                c.id_tipos_identificacion,
                ti.codigo AS tipo_ident_codigo_sri,
                fp.codigo_sri AS forma_pago_sri
            FROM facturas f
            JOIN empresa e ON f.empresa_id = e.id
            JOIN punto_emision p ON f.punto_emision_id = p.id
            JOIN clientes c ON f.cliente_id = c.id
            JOIN tipos_identificacion ti ON c.id_tipos_identificacion = ti.id
            JOIN formas_pago fp ON f.forma_pago_id = fp.id
            WHERE f.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$factura_id]);
        $factura = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$factura) {
            throw new Exception("Factura no encontrada con ID: $factura_id");
        }

        // Obtener detalles
        $sql_detalle = "
            SELECT 
                df.cantidad, 
                df.precio_unitario, 
                df.subtotal,
                df.iva,
                df.total,
                COALESCE(prod.codigo, '') AS codigo_principal,
                COALESCE(prod.nombre, 'PRODUCTO') AS descripcion
            FROM detalle_factura df
            LEFT JOIN productos prod ON df.producto_id = prod.id
            WHERE df.factura_id = ?
        ";
        $stmt_det = $pdo->prepare($sql_detalle);
        $stmt_det->execute([$factura_id]);
        $detalles = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
        if (empty($detalles)) {
            throw new Exception("La factura no tiene productos registrados.");
        }

        // Formatear datos
        $fecha_emision = date('d/m/Y', strtotime($factura['fecha_emision']));
        // CORREGIDO: usar f.secuencial (el asignado a la factura), NO p.secuencial_factura (contador actual)
        $secuencial = str_pad($factura['secuencial'], 9, '0', STR_PAD_LEFT);
        $estab = str_pad($factura['establecimiento'], 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($factura['punto_emision'], 3, '0', STR_PAD_LEFT);

        // IVA dinámico según la tasa guardada en la factura
        $porcentaje_iva = floatval($factura['iva']);
        if ($porcentaje_iva <= 0) {
            $codigo_porcentaje_iva = '0'; // 0%
            $tarifa_iva = '0.0';
        } elseif ($porcentaje_iva <= 5) {
            $codigo_porcentaje_iva = '5'; // 5%
            $tarifa_iva = number_format($porcentaje_iva, 1, '.', '');
        } elseif ($porcentaje_iva <= 12) {
            $codigo_porcentaje_iva = '2'; // 12%
            $tarifa_iva = number_format($porcentaje_iva, 1, '.', '');
        } elseif ($porcentaje_iva <= 14) {
            $codigo_porcentaje_iva = '3'; // 14%
            $tarifa_iva = number_format($porcentaje_iva, 1, '.', '');
        } else {
            $codigo_porcentaje_iva = '4'; // 15%
            $tarifa_iva = number_format($porcentaje_iva, 1, '.', '');
        }

        // Nombre del archivo
        $nombre_archivo = "FACTURA_v1.1_{$factura_id}_" . substr($factura['clave_acceso'], 0, 14) . ".xml";
        $ruta_completa = $ruta_generados . $nombre_archivo;

        // ================================
        // USO DE DOMDocument PARA CONTROL TOTAL
        // ================================
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = false; // No añadir saltos extra
        $dom->preserveWhiteSpace = true;

        // Nodo raíz: <factura id="comprobante" version="1.1.0">
        $facturaNode = $dom->createElement('factura');
        $facturaNode->setAttribute('id', 'comprobante');
        $facturaNode->setAttribute('version', '1.1.0');
        $dom->appendChild($facturaNode);

        // infoTributaria
        $infoTributaria = $dom->createElement('infoTributaria');
        // CORREGIDO: usar ambiente_id dinámico de la factura
        $ambiente = $factura['ambiente_id'] ?? '2';
        $infoTributaria->appendChild($dom->createElement('ambiente', $ambiente));
        $infoTributaria->appendChild($dom->createElement('tipoEmision', '1'));

        // Formato correcto: APELLIDO NOMBRE
        $nombres = explode(' ', trim($factura['razon_social']));
        if (count($nombres) >= 4) {
            $apellido1 = $nombres[0];
            $apellido2 = $nombres[1];
            $nombre1 = $nombres[2];
            $nombre2 = $nombres[3] ?? '';
            $razonSocialFormateada = strtoupper("$apellido1 $apellido2 $nombre1 $nombre2");
        } else {
            $razonSocialFormateada = strtoupper(trim($factura['razon_social']));
        }
        $infoTributaria->appendChild($dom->createElement('razonSocial', htmlspecialchars($razonSocialFormateada, ENT_NOQUOTES, 'UTF-8')));

        if (!empty($factura['nombre_comercial'])) {
            $infoTributaria->appendChild($dom->createElement('nombreComercial', htmlspecialchars(trim($factura['nombre_comercial']), ENT_NOQUOTES, 'UTF-8')));
        }
        $infoTributaria->appendChild($dom->createElement('ruc', $factura['ruc']));
        $infoTributaria->appendChild($dom->createElement('claveAcceso', $factura['clave_acceso']));
        // CORREGIDO: codDoc dinámico desde tipo_comprobante_id
        $codDoc = !empty($factura['tipo_comprobante_id']) ? $factura['tipo_comprobante_id'] : '01';
        $infoTributaria->appendChild($dom->createElement('codDoc', $codDoc));
        $infoTributaria->appendChild($dom->createElement('estab', $estab));
        $infoTributaria->appendChild($dom->createElement('ptoEmi', $ptoEmi));
        $infoTributaria->appendChild($dom->createElement('secuencial', $secuencial));
        if (!empty($factura['dir_empresa'])) {
            $infoTributaria->appendChild($dom->createElement('dirMatriz', htmlspecialchars(trim($factura['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        $facturaNode->appendChild($infoTributaria);

        // infoFactura
        $infoFactura = $dom->createElement('infoFactura');
        $infoFactura->appendChild($dom->createElement('fechaEmision', $fecha_emision));
        if (!empty($factura['dir_empresa'])) {
            $infoFactura->appendChild($dom->createElement('dirEstablecimiento', htmlspecialchars(trim($factura['dir_empresa']), ENT_NOQUOTES, 'UTF-8')));
        }
        if (!empty($factura['contribuyente_especial'])) {
            $infoFactura->appendChild($dom->createElement('contribuyenteEspecial', $factura['contribuyente_especial']));
        }
        $infoFactura->appendChild($dom->createElement('obligadoContabilidad', $factura['obligado_contabilidad']));
        // CORREGIDO: usar el código SRI real desde tipos_identificacion
        $codIdentificacion = $factura['tipo_ident_codigo_sri'] ?? '05';
        $infoFactura->appendChild($dom->createElement('tipoIdentificacionComprador', $codIdentificacion));
        $infoFactura->appendChild($dom->createElement('razonSocialComprador', htmlspecialchars(trim($factura['razon_social_cliente']), ENT_NOQUOTES, 'UTF-8')));
        $infoFactura->appendChild($dom->createElement('identificacionComprador', $factura['identificacion']));
        if (!empty($factura['dir_cliente'])) {
            $infoFactura->appendChild($dom->createElement('direccionComprador', htmlspecialchars(trim($factura['dir_cliente']), ENT_NOQUOTES, 'UTF-8')));
        }

        // Calcular totales desde los detalles (para que coincida con sum(lineas))
        $sum_subtotales_lineas = array_sum(array_map(function($d) { return round($d['subtotal'], 2); }, $detalles));
        $sum_ivas_lineas = array_sum(array_map(function($d) { return round($d['iva'], 2); }, $detalles));
        $sum_totales_lineas = array_sum(array_map(function($d) { return round($d['total'], 2); }, $detalles));

        $totalSinImpuestos = number_format($sum_subtotales_lineas, 2, '.', '');
        $totalDescuento = number_format($factura['descuento'], 2, '.', '');
        $baseImponibleIva = number_format($sum_subtotales_lineas, 2, '.', '');
        $valorIva = number_format($sum_ivas_lineas, 2, '.', '');
        $importeTotal = number_format($sum_totales_lineas - $factura['descuento'], 2, '.', '');

        $infoFactura->appendChild($dom->createElement('totalSinImpuestos', $totalSinImpuestos));
        $infoFactura->appendChild($dom->createElement('totalDescuento', $totalDescuento));

        // totalConImpuestos
        $totalConImpuestos = $dom->createElement('totalConImpuestos');
        $totalImpuesto = $dom->createElement('totalImpuesto');
        $totalImpuesto->appendChild($dom->createElement('codigo', '2'));
        $totalImpuesto->appendChild($dom->createElement('codigoPorcentaje', $codigo_porcentaje_iva));
        $totalImpuesto->appendChild($dom->createElement('baseImponible', $baseImponibleIva));
        $totalImpuesto->appendChild($dom->createElement('valor', $valorIva));
        $totalConImpuestos->appendChild($totalImpuesto);
        $infoFactura->appendChild($totalConImpuestos);

        $infoFactura->appendChild($dom->createElement('propina', '0.00'));
        $infoFactura->appendChild($dom->createElement('importeTotal', $importeTotal));
        $infoFactura->appendChild($dom->createElement('moneda', 'DOLAR'));

        // pagos
        $pagos = $dom->createElement('pagos');
        $pago = $dom->createElement('pago');
        $pago->appendChild($dom->createElement('formaPago', $factura['forma_pago_sri'] ?? '01'));
        $pago->appendChild($dom->createElement('total', number_format($factura['total'], 2, '.', '')));
        $pagos->appendChild($pago);
        $infoFactura->appendChild($pagos);

        $facturaNode->appendChild($infoFactura);

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
            $detalle->appendChild($dom->createElement('descuento', number_format(0, 2, '.', '')));
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
        $facturaNode->appendChild($detallesNode);

        // Guardar XML sin formato adicional
        $dom->save($ruta_completa);

        // Actualizar BD
        $stmt_update = $pdo->prepare("
            UPDATE facturas 
            SET archivo_xml = ?, 
                xml_generado = 1, 
                estado_xml = 'GENERADO', 
                fecha_actualizacion = NOW() 
            WHERE id = ?
        ");
        $stmt_update->execute([$nombre_archivo, $factura_id]);

        return [
            'ok' => true,
            'archivo' => $nombre_archivo,
            'ruta' => $ruta_completa,
            'clave_acceso' => $factura['clave_acceso'],
            'mensaje' => "XML v1.1.0 generado correctamente con DOMDocument: $nombre_archivo"
        ];
    } catch (Exception $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage()
        ];
    }
}

$resultado = generarXMLFacturaSRI_1_1_0_Corregido($factura_id, $pdo, $ruta_generados);
header('Content-Type: application/json');
echo json_encode($resultado, JSON_PRETTY_PRINT);
exit;