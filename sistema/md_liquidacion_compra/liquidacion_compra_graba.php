<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

try {
    $usuario_id = $_SESSION['usuario_id'];

    // === 1. Recibir datos ===
    $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $forma_pago_id = (int)($_POST['forma_pago_id'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d H:i:s');
    $comentarios = $_POST['comentarios'] ?? null;

    $subtotal1 = round(floatval($_POST['subtotal1'] ?? 0), 2);
    $iva = round(floatval($_POST['iva'] ?? 0), 2);
    $valor_iva = round(floatval($_POST['valor_iva'] ?? 0), 2);
    $total = round(floatval($_POST['total'] ?? 0), 2);

    $subtotal1 = max(0, $subtotal1);
    $valor_iva = max(0, $valor_iva);
    $total = max(0, $total);

    // Productos
    $productos_ids = array_filter(explode(',', $_POST['productos_ids'] ?? ''));
    $productos_cantidades = array_map('floatval', explode(',', $_POST['productos_cantidades'] ?? ''));
    $productos_precios = array_map('floatval', explode(',', $_POST['productos_precios'] ?? ''));
    $productos_subtotales = array_map('floatval', explode(',', $_POST['productos_subtotales'] ?? ''));
    $productos_ivas = array_map('floatval', explode(',', $_POST['productos_ivas'] ?? ''));
    $productos_totales = array_map('floatval', explode(',', $_POST['productos_totales'] ?? ''));

    if (empty($productos_ids) || count($productos_ids) !== count($productos_cantidades)) {
        throw new Exception("Error en los productos seleccionados.");
    }

    // Validar total
    $valor_iva_calculado = array_sum($productos_ivas);
    $total_calculado = round($subtotal1 + $valor_iva_calculado, 2);
    if (abs($total - $total_calculado) > 0.02) {
        throw new Exception("Error en cálculos: total no coincide.");
    }

    // === 2. Validar proveedor ===
    $sql_prov = "SELECT id, nombre, ruc, direccion FROM proveedores WHERE id = :id AND usuario_id = :uid";
    $stmt_prov = $pdo->prepare($sql_prov);
    $stmt_prov->execute([':id' => $proveedor_id, ':uid' => $usuario_id]);
    $proveedor = $stmt_prov->fetch(PDO::FETCH_ASSOC);
    if (!$proveedor) {
        throw new Exception("Proveedor no válido o no te pertenece.");
    }

    // === 3. Validar empresa ===
    $sql_empresa = "SELECT id, ruc, ambiente_id, nombre_comercial, razon_social, direccion, contribuyente_especial, obligado_contabilidad 
                    FROM empresa WHERE id = :id AND usuario_id = :uid AND activa = 1";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([':id' => $empresa_id, ':uid' => $usuario_id]);
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        throw new Exception("Empresa no válida o inactiva.");
    }

    // === 4. Validar punto de emisión ===
    $sql_punto = "SELECT p.establecimiento, p.punto_emision, p.secuencial_liquidacion_compra, p.iva
                  FROM punto_emision p JOIN empresa e ON p.empresa_id = e.id
                  WHERE p.id = :pid AND e.id = :eid AND e.usuario_id = :uid";
    $stmt_punto = $pdo->prepare($sql_punto);
    $stmt_punto->execute([':pid' => $punto_emision_id, ':eid' => $empresa_id, ':uid' => $usuario_id]);
    $punto = $stmt_punto->fetch(PDO::FETCH_ASSOC);
    if (!$punto) {
        throw new Exception("Punto de emisión no válido.");
    }

    // === 5. Validar productos ===
    foreach ($productos_ids as $index => $producto_id) {
        if (empty($producto_id)) continue;
        $sql_prod = "SELECT id FROM productos WHERE id = :id AND usuario_id = :uid";
        $stmt_prod = $pdo->prepare($sql_prod);
        $stmt_prod->execute([':id' => $producto_id, ':uid' => $usuario_id]);
        if (!$stmt_prod->fetch()) {
            throw new Exception("Producto ID $producto_id no válido.");
        }
    }

    // === 6. Generar secuencial ===
    $nuevo_secuencial = (int)$punto['secuencial_liquidacion_compra'] + 1;

    // === 7. Generar clave de acceso (codDoc=03) ===
    function generarClaveAcceso($fecha, $tipo, $ruc, $estab, $ptoEmi, $secuencial) {
        $fechaFmt = date('dmY', strtotime($fecha));
        $estab = str_pad($estab, 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($ptoEmi, 3, '0', STR_PAD_LEFT);
        $secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        $codigoNumerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1';
        $ambiente = '2';
        $clave48 = $fechaFmt . $tipo . $ruc . $ambiente . $estab . $ptoEmi . $secuencial . $codigoNumerico . $tipoEmision;
        if (strlen($clave48) !== 48) throw new Exception("Clave base debe tener 48 dígitos. Tiene: " . strlen($clave48));
        $factores = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        for ($i = 47; $i >= 0; $i--) {
            $digito = intval($clave48[$i]);
            $factor = $factores[(47 - $i) % 6];
            $suma += $digito * $factor;
        }
        $modulo = $suma % 11;
        $digitoVerificador = 11 - $modulo;
        if ($digitoVerificador == 11) $digitoVerificador = 0;
        elseif ($digitoVerificador == 10) $digitoVerificador = 1;
        return $clave48 . $digitoVerificador;
    }

    $clave_acceso = generarClaveAcceso(
        $fecha_emision,
        '03', // Liquidación de Compra
        $empresa['ruc'],
        $punto['establecimiento'],
        $punto['punto_emision'],
        $nuevo_secuencial
    );

    if (strlen($clave_acceso) !== 49) {
        throw new Exception("Clave de acceso debe tener 49 dígitos.");
    }

    // === 8. Iniciar transacción ===
    $pdo->beginTransaction();

    // === 9. Insertar en facturas (tipo_comprobante_id = '03') ===
    $sql_insert = "INSERT INTO facturas (
            empresa_id, proveedor_id, forma_pago_id, punto_emision_id,
            comentarios, fecha_emision, subtotal1, descuento, subtotal2,
            iva, valor_iva, total, clave_acceso, estado_xml, xml_generado,
            ambiente_id, establecimiento, punto_emision, secuencial, usuario_id,
            tipo_comprobante_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, 'PENDIENTE', 0, 2, ?, ?, ?, ?, '03')";
    // Subtotal2 = subtotal1 (sin descuento en liquidación)
    $stmt_insert = $pdo->prepare($sql_insert);
    $stmt_insert->execute([
        $empresa_id, $proveedor_id, $forma_pago_id, $punto_emision_id,
        $comentarios, $fecha_emision, $subtotal1, $subtotal1,
        $iva, $valor_iva, $total, $clave_acceso,
        $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial, $usuario_id
    ]);
    $liqui_id = $pdo->lastInsertId();

    // === 10. Insertar detalles ===
    $sql_detalle = "INSERT INTO detalle_factura (factura_id, producto_id, cantidad, precio_unitario, subtotal, iva, total)
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt_detalle = $pdo->prepare($sql_detalle);

    foreach ($productos_ids as $index => $producto_id) {
        if (empty($producto_id)) continue;
        $cantidad = $productos_cantidades[$index] ?? 1;
        $precio = round($productos_precios[$index], 2);
        $subtotal_item = round($productos_subtotales[$index], 2);
        $iva_item = round($productos_ivas[$index], 2);
        $total_item = round($productos_totales[$index], 2);

        $stmt_detalle->execute([
            $liqui_id, $producto_id, $cantidad, $precio, $subtotal_item, $iva_item, $total_item
        ]);
    }

    // === 11. Actualizar secuencial ===
    $sql_upd_sec = "UPDATE punto_emision SET secuencial_liquidacion_compra = ? WHERE id = ?";
    $stmt_upd_sec = $pdo->prepare($sql_upd_sec);
    $stmt_upd_sec->execute([$nuevo_secuencial, $punto_emision_id]);

    $pdo->commit();

    header("Location: liquidacion_compra_lista.php?msg=success&id=$liqui_id");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Error en liquidacion_compra_graba.php: " . $e->getMessage());
    header("Location: liquidacion_compra_lista.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
