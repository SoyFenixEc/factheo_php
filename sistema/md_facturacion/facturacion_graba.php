<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

try {
    $usuario_id = $_SESSION['usuario_id'];

    // === 1. Recibir y limpiar datos ===
    $cliente_id = (int)($_POST['cliente_id'] ?? 0);
    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $forma_pago_id = (int)($_POST['forma_pago_id'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d H:i:s');
    $comentarios = $_POST['comentarios'] ?? null;

    // Convertir a DECIMAL(12,2) y evitar -0.00
    $subtotal1 = round(floatval($_POST['subtotal1'] ?? 0), 2);
    $descuento = round(floatval($_POST['descuento'] ?? 0), 2);
    $subtotal2 = round(floatval($_POST['subtotal2'] ?? 0), 2);
    $iva = round(floatval($_POST['iva'] ?? 0), 2);
    $valor_iva = round(floatval($_POST['valor_iva'] ?? 0), 2);
    $total = round(floatval($_POST['total'] ?? 0), 2);

    // Evitar -0.00
    $subtotal1 = max(0, $subtotal1);
    $descuento = max(0, $descuento);
    $subtotal2 = max(0, $subtotal2);
    $valor_iva = max(0, $valor_iva);
    $total = max(0, $total);

    // Productos con IVA individual
    $productos_ids = array_filter(explode(',', $_POST['productos_ids'] ?? ''));
    $productos_cantidades = array_map('floatval', explode(',', $_POST['productos_cantidades'] ?? ''));
    $productos_precios = array_map('floatval', explode(',', $_POST['productos_precios'] ?? ''));
    $productos_subtotales = array_map('floatval', explode(',', $_POST['productos_subtotales'] ?? ''));
    $productos_ivas = array_map('floatval', explode(',', $_POST['productos_ivas'] ?? ''));
    $productos_totales = array_map('floatval', explode(',', $_POST['productos_totales'] ?? ''));
    $productos_iva_porcentajes = array_map('floatval', explode(',', $_POST['productos_iva_porcentajes'] ?? ''));

    if (empty($productos_ids) || count($productos_ids) !== count($productos_cantidades)) {
        throw new Exception("Error en los productos seleccionados.");
    }

    // Validar cálculos - Sumar IVA de cada producto
    $valor_iva_calculado = array_sum($productos_ivas);
    $total_calculado = round($subtotal2 + $valor_iva_calculado, 2);
    if (abs($total - $total_calculado) > 0.02) {
        throw new Exception("Error en cálculos: total no coincide. Total: $total, Calculado: $total_calculado. IVA calculado: $valor_iva_calculado");
    }

    // === 2. Validar que el cliente pertenece al usuario actual ===
    $sql_cliente = "SELECT id FROM clientes WHERE id = :cliente_id AND usuario_id = :usuario_id";
    $stmt_cliente = $pdo->prepare($sql_cliente);
    $stmt_cliente->execute([':cliente_id' => $cliente_id, ':usuario_id' => $usuario_id]);
    if (!$stmt_cliente->fetch()) {
        throw new Exception("Cliente no válido o no te pertenece.");
    }

    // === 3. Validar que la empresa pertenece al usuario actual y está activa ===
    $sql_empresa = "SELECT id, ruc, ambiente_id, nombre_comercial, razon_social, direccion, contribuyente_especial, obligado_contabilidad 
                    FROM empresa 
                    WHERE id = :empresa_id AND usuario_id = :usuario_id AND activa = 1";
    $stmt_empresa = $pdo->prepare($sql_empresa);
    $stmt_empresa->execute([':empresa_id' => $empresa_id, ':usuario_id' => $usuario_id]);
    $empresa = $stmt_empresa->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) {
        throw new Exception("Empresa no válida, no te pertenece o no está activa.");
    }

    // === 4. Validar que el punto de emisión pertenece a la empresa del usuario actual ===
    $sql_punto = "
        SELECT 
            p.establecimiento, 
            p.punto_emision, 
            p.secuencial_factura,
            p.iva
        FROM punto_emision p
        JOIN empresa e ON p.empresa_id = e.id
        WHERE p.id = :punto_emision_id AND e.id = :empresa_id AND e.usuario_id = :usuario_id
    ";
    $stmt_punto = $pdo->prepare($sql_punto);
    $stmt_punto->execute([
        ':punto_emision_id' => $punto_emision_id,
        ':empresa_id' => $empresa_id,
        ':usuario_id' => $usuario_id
    ]);
    $punto = $stmt_punto->fetch(PDO::FETCH_ASSOC);
    if (!$punto) {
        throw new Exception("Punto de emisión no válido o no te pertenece.");
    }

    // === 5. Validar que los productos pertenecen al usuario actual ===
    foreach ($productos_ids as $index => $producto_id) {
        if (empty($producto_id)) continue;
        $sql_producto = "SELECT id, stock FROM productos WHERE id = :producto_id AND usuario_id = :usuario_id";
        $stmt_producto = $pdo->prepare($sql_producto);
        $stmt_producto->execute([':producto_id' => $producto_id, ':usuario_id' => $usuario_id]);
        $producto = $stmt_producto->fetch();
        if (!$producto) {
            throw new Exception("Producto ID $producto_id no válido o no te pertenece.");
        }
        $cantidad = $productos_cantidades[$index] ?? 1;
        if ($cantidad > $producto['stock']) {
            throw new Exception("Stock insuficiente para el producto ID $producto_id.");
        }
    }

    // === 6. Generar nuevo secuencial ===
    $nuevo_secuencial = (int)$punto['secuencial_factura'] + 1;

    // === 7. Generar clave de acceso (producción) ===
    /*
	function generarClaveAcceso($fecha, $tipo, $ruc, $estab, $ptoEmi, $secuencial) {
        $fechaFmt = date('dmY', strtotime($fecha));
        $estab = str_pad($estab, 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($ptoEmi, 3, '0', STR_PAD_LEFT);
        $secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        $codigoNumerico = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1'; // Emisión normal
        $ambiente = '2';   // Producción
        $clave = $fechaFmt . $tipo . $ruc . $ambiente . $estab . $ptoEmi . $secuencial . $codigoNumerico . $tipoEmision;
        if (strlen($clave) !== 48) {
            throw new Exception("Clave base debe tener 48 dígitos");
        }
        $coef = [2, 3, 4, 5, 6, 7];
        $sum = 0;
        for ($i = strlen($clave) - 1; $i >= 0; $i--) {
            $pos = (strlen($clave) - 1 - $i) % 6;
            $sum += (int)$clave[$i] * $coef[$pos];
        }
        $res = $sum % 11;
        $dv = $res == 0 ? 0 : (11 - $res);
        if ($dv == 10 || $dv == 11) $dv = 0;
        return $clave . $dv;
    } */
	
	
	function generarClaveAcceso($fecha, $tipo, $ruc, $estab, $ptoEmi, $secuencial) {
		// 1. Formatear componentes
		$fechaFmt = date('dmY', strtotime($fecha));
		$estab = str_pad($estab, 3, '0', STR_PAD_LEFT);
		$ptoEmi = str_pad($ptoEmi, 3, '0', STR_PAD_LEFT);
		$secuencial = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
		$codigoNumerico = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
		$tipoEmision = '1';
		$ambiente = '2';
		
		// 2. Construir clave de 48 dígitos
		$clave48 = $fechaFmt . $tipo . $ruc . $ambiente . $estab . $ptoEmi . $secuencial . $codigoNumerico . $tipoEmision;
		
		if (strlen($clave48) !== 48) {
			throw new Exception("Clave base debe tener 48 dígitos. Tiene: " . strlen($clave48));
		}
		
		// 3. Calcular dígito verificador (Algoritmo oficial SRI)
		$factores = [2, 3, 4, 5, 6, 7];
		$suma = 0;
		
		for ($i = 47; $i >= 0; $i--) {
			$digito = intval($clave48[$i]);
			$factor = $factores[(47 - $i) % 6];
			$suma += $digito * $factor;
		}
		
		$modulo = $suma % 11;
		$digitoVerificador = 11 - $modulo;
		
		if ($digitoVerificador == 11) {
			$digitoVerificador = 0;
		} elseif ($digitoVerificador == 10) {
			$digitoVerificador = 1;
		}
		
		// 4. Retornar clave completa de 49 dígitos
		return $clave48 . $digitoVerificador;
	}

    $tipo_comprobante = '01'; // Siempre factura en este módulo

    $clave_acceso = generarClaveAcceso(
        $fecha_emision,
        $tipo_comprobante,
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

    // === 9. Insertar factura ===
    $sql_factura = "
        INSERT INTO facturas (
            empresa_id, cliente_id, forma_pago_id, punto_emision_id,
            comentarios, fecha_emision, subtotal1, descuento, subtotal2,
            iva, valor_iva, total, clave_acceso, estado_xml, xml_generado,
            ambiente_id, establecimiento, punto_emision, secuencial, usuario_id,
            tipo_comprobante_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', 0, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_factura = $pdo->prepare($sql_factura);
    $stmt_factura->execute([
        $empresa_id, $cliente_id, $forma_pago_id, $punto_emision_id,
        $comentarios, $fecha_emision, $subtotal1, $descuento, $subtotal2,
        $iva, $valor_iva, $total, $clave_acceso,
        $empresa['ambiente_id'], $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial, $usuario_id,
        $tipo_comprobante
    ]);
    $factura_id = $pdo->lastInsertId();

    // === 10. Insertar detalles con IVA individual ===
    $sql_detalle = "
        INSERT INTO detalle_factura (factura_id, producto_id, cantidad, precio_unitario, subtotal, iva, iva_porcentaje, total)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $stmt_detalle = $pdo->prepare($sql_detalle);
    $sql_update_stock = "UPDATE productos SET stock = stock - ? WHERE id = ?";
    $stmt_update_stock = $pdo->prepare($sql_update_stock);

    foreach ($productos_ids as $index => $producto_id) {
        if (empty($producto_id)) continue;
        $cantidad = $productos_cantidades[$index] ?? 1;
        $precio = round($productos_precios[$index], 2);
        $subtotal = round($productos_subtotales[$index], 2);
        $iva_item = round($productos_ivas[$index], 2);
        $iva_porcentaje_item = round($productos_iva_porcentajes[$index] ?? 0, 2);
        $total_item = round($productos_totales[$index], 2);

        $stmt_detalle->execute([
            $factura_id, $producto_id, $cantidad, $precio, $subtotal, $iva_item, $iva_porcentaje_item, $total_item
        ]);

        $stmt_update_stock->execute([$cantidad, $producto_id]);
    }

    // === 11. Actualizar secuencial ===
    $sql_update_sec = "UPDATE punto_emision SET secuencial_factura = ? WHERE id = ?";
    $stmt_update_sec = $pdo->prepare($sql_update_sec);
    $stmt_update_sec->execute([$nuevo_secuencial, $punto_emision_id]);

    // === 12. Confirmar ===
    $pdo->commit();

    header("Location: ../md_facturacion/facturacion_lista.php?msg=success&factura_id=$factura_id");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollback();
    }
    error_log("Error en facturacion_graba.php: " . $e->getMessage());
    header("Location: ../md_facturacion/facturacion_lista.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
?>