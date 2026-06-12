<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

try {
    $usuario_id = $_SESSION['usuario_id'];

    $factura_sustento_id = (int)($_POST['factura_sustento_id'] ?? 0);
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $forma_pago_id = (int)($_POST['forma_pago_id'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d H:i:s');
    $motivo = $_POST['motivo'] ?? '';
    $motivo_descripcion = $_POST['motivo_descripcion'] ?? '';
    $comentarios = $_POST['comentarios'] ?? '';

    $subtotal1 = max(0, round(floatval($_POST['subtotal1'] ?? 0), 2));
    $iva = round(floatval($_POST['iva'] ?? 0), 2);
    $valor_iva = max(0, round(floatval($_POST['valor_iva'] ?? 0), 2));
    $total = max(0, round(floatval($_POST['total'] ?? 0), 2));

    $productos_ids = array_filter(explode(',', $_POST['productos_ids'] ?? ''));
    $productos_cantidades = array_map('floatval', explode(',', $_POST['productos_cantidades'] ?? ''));
    $productos_precios = array_map('floatval', explode(',', $_POST['productos_precios'] ?? ''));
    $productos_subtotales = array_map('floatval', explode(',', $_POST['productos_subtotales'] ?? ''));
    $productos_ivas = array_map('floatval', explode(',', $_POST['productos_ivas'] ?? ''));
    $productos_totales = array_map('floatval', explode(',', $_POST['productos_totales'] ?? ''));

    if (empty($productos_ids)) throw new Exception("Debe agregar al menos un producto.");

    // Obtener factura sustento
    $stmt = $pdo->prepare("SELECT f.id, f.clave_acceso, f.establecimiento, f.punto_emision, f.secuencial,
                                  f.cliente_id, f.empresa_id, f.tipo_comprobante_id,
                                  e.ruc, e.ambiente_id
                           FROM facturas f
                           JOIN empresa e ON f.empresa_id = e.id
                           WHERE f.id = ? AND e.usuario_id = ?");
    $stmt->execute([$factura_sustento_id, $usuario_id]);
    $factura_sustento = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$factura_sustento) throw new Exception("Factura soporte no encontrada.");

    // Obtener empresa
    $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = ? AND usuario_id = ? AND activa = 1");
    $stmt->execute([$empresa_id, $usuario_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) throw new Exception("Empresa no válida.");

    // Obtener punto de emisión
    $stmt = $pdo->prepare("SELECT * FROM punto_emision WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$punto_emision_id, $empresa_id]);
    $punto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$punto) throw new Exception("Punto de emisión no válido.");

    // Generar secuencial (usar secuencial_factura o crear campo aparte)
    $nuevo_secuencial = (int)$punto['secuencial_factura'] + 1;

    // Generar clave de acceso (modo pruebas = ambiente 1)
    function generarClaveAcceso($fecha, $tipo, $ruc, $estab, $ptoEmi, $secuencial) {
        $fechaFmt = date('dmY', strtotime($fecha));
        $estab = str_pad($estab, 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($ptoEmi, 3, '0', STR_PAD_LEFT);
        $sec = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        $codNum = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1';
        $ambiente = '1'; // MODO PRUEBAS
        $c48 = $fechaFmt . $tipo . $ruc . $ambiente . $estab . $ptoEmi . $sec . $codNum . $tipoEmision;
        if (strlen($c48) !== 48) throw new Exception("Clave base debe tener 48 dígitos. Tiene: " . strlen($c48));
        $factores = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        for ($i = 47; $i >= 0; $i--) $suma += intval($c48[$i]) * $factores[(47 - $i) % 6];
        $dv = 11 - ($suma % 11);
        if ($dv == 11) $dv = 0; elseif ($dv == 10) $dv = 1;
        return $c48 . $dv;
    }

    $estab_str = str_pad($punto['establecimiento'], 3, '0', STR_PAD_LEFT);
    $pto_str = str_pad($punto['punto_emision'], 3, '0', STR_PAD_LEFT);
    $num_modificado = $estab_str . '-' . $pto_str . '-' . str_pad($factura_sustento['secuencial'], 9, '0', STR_PAD_LEFT);

    $clave_acceso = generarClaveAcceso($fecha_emision, '05', $empresa['ruc'], $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial);
    if (strlen($clave_acceso) !== 49) throw new Exception("Clave de acceso debe tener 49 dígitos.");

    $pdo->beginTransaction();

    // Insertar factura como nota de crédito
    $sql = "INSERT INTO facturas (
        empresa_id, cliente_id, forma_pago_id, punto_emision_id,
        comentarios, fecha_emision, subtotal1, descuento, subtotal2,
        iva, valor_iva, total, clave_acceso, estado_xml, xml_generado,
        ambiente_id, establecimiento, punto_emision, secuencial, usuario_id,
        tipo_comprobante_id, codDocModificado, numDocModificado, fechaEmisionDocSustento, motivo
    ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'PENDIENTE', 0, 1, ?, ?, ?, ?, '03', ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $empresa_id, $factura_sustento['cliente_id'], $forma_pago_id, $punto_emision_id,
        $comentarios, $fecha_emision, $subtotal1, $subtotal1,
        $iva, $valor_iva, $total, $clave_acceso,
        $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial, $usuario_id,
        $factura_sustento['tipo_comprobante_id'], $num_modificado, $fecha_emision, $motivo_descripcion
    ]);
    $factura_id = $pdo->lastInsertId();

    // Insertar detalles
    $sql_det = "INSERT INTO detalle_factura (factura_id, producto_id, cantidad, precio_unitario, subtotal, iva, total) VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_det = $pdo->prepare($sql_det);
    $sql_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
    $stmt_stock = $pdo->prepare($sql_stock);

    foreach ($productos_ids as $idx => $pid) {
        if (empty($pid)) continue;
        $cant = $productos_cantidades[$idx] ?? 1;
        $precio = round($productos_precios[$idx], 2);
        $sub = round($productos_subtotales[$idx], 2);
        $iva_i = round($productos_ivas[$idx], 2);
        $tot_i = round($productos_totales[$idx], 2);
        $stmt_det->execute([$factura_id, $pid, $cant, $precio, $sub, $iva_i, $tot_i]);
        $stmt_stock->execute([$cant, $pid]);
    }

    // Actualizar secuencial
    $pdo->prepare("UPDATE punto_emision SET secuencial_factura = ? WHERE id = ?")->execute([$nuevo_secuencial, $punto_emision_id]);

    $pdo->commit();

    header("Location: nota_debito_lista.php?msg=success&id=$factura_id");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollback();
    error_log("Error en nota_debito_graba.php: " . $e->getMessage());
    header("Location: nota_debito_lista.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
