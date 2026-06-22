<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

try {
    $usuario_id = $_SESSION['usuario_id'];

    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d');
    $factura_sustento_id = (int)($_POST['factura_sustento_id'] ?? 0);
    $dir_partida = trim($_POST['dir_partida'] ?? '');
    $ruta = trim($_POST['ruta'] ?? '');
    $razon_social_transportista = trim($_POST['razon_social_transportista'] ?? '');
    $tipo_identificacion_transportista = $_POST['tipo_identificacion_transportista'] ?? '';
    $ruc_transportista = trim($_POST['ruc_transportista'] ?? '');
    $fecha_inicio_transporte = $_POST['fecha_inicio_transporte'] ?? '';
    $fecha_fin_transporte = $_POST['fecha_fin_transporte'] ?? '';
    $placa = trim($_POST['placa'] ?? '');
    $identificacion_destinatario = trim($_POST['identificacion_destinatario'] ?? '');
    $razon_social_destinatario = trim($_POST['razon_social_destinatario'] ?? '');
    $dir_destinatario = trim($_POST['dir_destinatario'] ?? '');
    $motivo_traslado = $_POST['motivo_traslado'] ?? '';
    $doc_aduano_unico = trim($_POST['doc_aduano_unico'] ?? '');
    $cod_estab_destino = trim($_POST['cod_estab_destino'] ?? '');
    $comentarios = trim($_POST['comentarios'] ?? '');
    $num_doc_sustento = trim($_POST['num_doc_sustento'] ?? '');
    $num_aut_doc_sustento = trim($_POST['num_aut_doc_sustento'] ?? '');
    $fecha_emision_doc_sustento = $_POST['fecha_emision_doc_sustento'] ?? '';

    // Validaciones básicas
    if (empty($dir_partida)) throw new Exception("La dirección de partida es obligatoria.");
    if (empty($razon_social_transportista)) throw new Exception("La razón social del transportista es obligatoria.");
    if (empty($ruc_transportista)) throw new Exception("El RUC/Identificación del transportista es obligatorio.");
    if (empty($placa)) throw new Exception("La placa del vehículo es obligatoria.");
    if (empty($identificacion_destinatario)) throw new Exception("La identificación del destinatario es obligatoria.");
    if (empty($razon_social_destinatario)) throw new Exception("La razón social del destinatario es obligatoria.");
    if (empty($dir_destinatario)) throw new Exception("La dirección del destinatario es obligatoria.");
    if (empty($motivo_traslado)) throw new Exception("El motivo de traslado es obligatorio.");

    // Procesar productos
    $productos_ids = array_filter(explode(',', $_POST['productos_ids'] ?? ''));
    $productos_codigos = explode('||', $_POST['productos_codigos'] ?? '');
    $productos_adicionales = explode('||', $_POST['productos_adicionales'] ?? '');
    $productos_descripciones = explode('||', $_POST['productos_descripciones'] ?? '');
    $productos_cantidades = array_map('floatval', explode(',', $_POST['productos_cantidades'] ?? ''));

    if (empty($productos_ids)) throw new Exception("Debe agregar al menos un producto.");

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

    // Generar secuencial para guía de remisión
    $nuevo_secuencial = (int)$punto['secuencial_guia_remision'] + 1;

    // Generar clave de acceso
    function generarClaveAcceso($fecha, $tipo, $ruc, $estab, $ptoEmi, $secuencial, $ambiente) {
        $fechaFmt = date('dmY', strtotime($fecha));
        $estab = str_pad($estab, 3, '0', STR_PAD_LEFT);
        $ptoEmi = str_pad($ptoEmi, 3, '0', STR_PAD_LEFT);
        $sec = str_pad($secuencial, 9, '0', STR_PAD_LEFT);
        $codNum = str_pad(mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        $tipoEmision = '1';
        $c48 = $fechaFmt . $tipo . $ruc . $ambiente . $estab . $ptoEmi . $sec . $codNum . $tipoEmision;
        if (strlen($c48) !== 48) throw new Exception("Clave base debe tener 48 dígitos. Tiene: " . strlen($c48));
        $factores = [2, 3, 4, 5, 6, 7];
        $suma = 0;
        for ($i = 47; $i >= 0; $i--) $suma += intval($c48[$i]) * $factores[(47 - $i) % 6];
        $dv = 11 - ($suma % 11);
        if ($dv == 11) $dv = 0; elseif ($dv == 10) $dv = 1;
        return $c48 . $dv;
    }

    $ambiente = $empresa['ambiente_id'] == 2 ? '2' : '1';
    $clave_acceso = generarClaveAcceso($fecha_emision, '06', $empresa['ruc'], $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial, $ambiente);
    if (strlen($clave_acceso) !== 49) throw new Exception("Clave de acceso debe tener 49 dígitos.");

    // Obtener código doc sustento
    $cod_doc_sustento = '';
    if ($factura_sustento_id > 0) {
        $stmt = $pdo->prepare("SELECT f.tipo_comprobante_id FROM facturas f WHERE f.id = ?");
        $stmt->execute([$factura_sustento_id]);
        $fs = $stmt->fetch(PDO::FETCH_ASSOC);
        $cod_doc_sustento = $fs ? $fs['tipo_comprobante_id'] : '01';
    }

    $pdo->beginTransaction();

    // Insertar guía de remisión
    $sql = "INSERT INTO guias_remision (
        usuario_id, empresa_id, punto_emision_id,
        fecha_emision, dir_partida, ruta,
        razon_social_transportista, tipo_identificacion_transportista, ruc_transportista,
        fecha_inicio_transporte, fecha_fin_transporte, placa,
        identificacion_destinatario, razon_social_destinatario, dir_destinatario,
        motivo_traslado, doc_aduano_unico, cod_estab_destino,
        cod_doc_sustento, num_doc_sustento, num_aut_doc_sustento, fecha_emision_doc_sustento,
        clave_acceso, estado_xml, xml_generado, xml_firmado, autorizado_sri,
        ambiente_id, establecimiento, punto_emision, secuencial,
        comentarios, fecha_actualizacion
    ) VALUES (
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?,
        ?, ?, ?, ?,
        ?, 'PENDIENTE', 0, 0, 0,
        ?, ?, ?, ?,
        ?, NOW()
    )";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $usuario_id, $empresa_id, $punto_emision_id,
        $fecha_emision, $dir_partida, $ruta,
        $razon_social_transportista, $tipo_identificacion_transportista, $ruc_transportista,
        $fecha_inicio_transporte, $fecha_fin_transporte, $placa,
        $identificacion_destinatario, $razon_social_destinatario, $dir_destinatario,
        $motivo_traslado, $doc_aduano_unico, $cod_estab_destino,
        $cod_doc_sustento, $num_doc_sustento, $num_aut_doc_sustento, $fecha_emision_doc_sustento,
        $clave_acceso,
        $ambiente, (int)$punto['establecimiento'], (int)$punto['punto_emision'], $nuevo_secuencial,
        $comentarios
    ]);
    $guia_id = $pdo->lastInsertId();

    // Insertar detalles
    $sql_det = "INSERT INTO detalle_guia_remision (guia_remision_id, producto_id, codigo_interno, codigo_adicional, descripcion, cantidad) VALUES (?, ?, ?, ?, ?, ?)";
    $stmt_det = $pdo->prepare($sql_det);

    $idx = 0;
    foreach ($productos_ids as $pid) {
        if (empty($pid)) continue;
        $cod_int = $productos_codigos[$idx] ?? '';
        $cod_ad = $productos_adicionales[$idx] ?? '';
        $desc = $productos_descripciones[$idx] ?? 'PRODUCTO';
        $cant = $productos_cantidades[$idx] ?? 1;
        $stmt_det->execute([$guia_id, (int)$pid, $cod_int, $cod_ad, $desc, $cant]);
        $idx++;
    }

    // Actualizar secuencial de guía de remisión en punto_emision
    $pdo->prepare("UPDATE punto_emision SET secuencial_guia_remision = ? WHERE id = ?")->execute([$nuevo_secuencial, $punto_emision_id]);

    $pdo->commit();

    header("Location: guia_remision_lista.php?msg=success&id=$guia_id");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollback();
    error_log("Error en guia_remision_graba.php: " . $e->getMessage());
    header("Location: guia_remision_lista.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
