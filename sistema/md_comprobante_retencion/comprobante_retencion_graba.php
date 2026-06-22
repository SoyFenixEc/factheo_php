<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

try {
    $usuario_id = $_SESSION['usuario_id'];

    $empresa_id = (int)($_POST['empresa_id'] ?? 0);
    $proveedor_id = (int)($_POST['proveedor_id'] ?? 0);
    $punto_emision_id = (int)($_POST['punto_emision_id'] ?? 0);
    $fecha_emision = $_POST['fecha_emision'] ?? date('Y-m-d H:i:s');
    $periodo_fiscal = $_POST['periodo_fiscal'] ?? date('m/Y');
    // Convertir periodo de YYYY-MM a mm/aaaa si viene de input month
    if (preg_match('/^\d{4}-\d{2}$/', $periodo_fiscal)) {
        $parts = explode('-', $periodo_fiscal);
        $periodo_fiscal = $parts[1] . '/' . $parts[0];
    }
    $comentarios = $_POST['comentarios'] ?? '';

    $tipo_identificacion_sujeto_retenido = $_POST['tipo_identificacion_sujeto_retenido'] ?? '05';
    $razon_social_sujeto_retenido = $_POST['razon_social_sujeto_retenido'] ?? '';
    $identificacion_sujeto_retenido = $_POST['identificacion_sujeto_retenido'] ?? '';
    $total_retenido = max(0, round(floatval($_POST['total_retenido'] ?? 0), 2));

    $docs_json = $_POST['docs_json'] ?? '';
    $docs = json_decode($docs_json, true);
    if (empty($docs) || !is_array($docs)) throw new Exception("Debe ingresar al menos un documento sustento con retenciones.");

    // Obtener empresa
    $stmt = $pdo->prepare("SELECT * FROM empresa WHERE id = ? AND usuario_id = ? AND activa = 1");
    $stmt->execute([$empresa_id, $usuario_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$empresa) throw new Exception("Empresa no válida o no activa.");

    // Obtener punto de emisión
    $stmt = $pdo->prepare("SELECT * FROM punto_emision WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$punto_emision_id, $empresa_id]);
    $punto = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$punto) throw new Exception("Punto de emisión no válido.");

    // Generar secuencial
    $nuevo_secuencial = (int)$punto['secuencial_comprobante_retencion'] + 1;

    // Generar clave de acceso (tipo 07 para comprobante de retención)
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

    $clave_acceso = generarClaveAcceso($fecha_emision, '07', $empresa['ruc'], $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial);
    if (strlen($clave_acceso) !== 49) throw new Exception("Clave de acceso debe tener 49 dígitos.");

    $pdo->beginTransaction();

    // Insertar comprobante_retencion
    $sql = "INSERT INTO comprobantes_retencion (
        usuario_id, empresa_id, proveedor_id, punto_emision_id,
        fecha_emision, tipo_identificacion_sujeto_retenido, razon_social_sujeto_retenido,
        identificacion_sujeto_retenido, periodo_fiscal,
        dir_establecimiento, contribuyente_especial, obligado_contabilidad,
        total_retenido, comentarios,
        estado_xml, clave_acceso, xml_generado, xml_firmado, autorizado_sri,
        ambiente_id, establecimiento, punto_emision, secuencial,
        fecha_actualizacion
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'PENDIENTE', ?, 0, 0, 0, ?, ?, ?, ?, NOW())";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $usuario_id, $empresa_id, $proveedor_id, $punto_emision_id,
        $fecha_emision, $tipo_identificacion_sujeto_retenido, $razon_social_sujeto_retenido,
        $identificacion_sujeto_retenido, $periodo_fiscal,
        $empresa['direccion'], $empresa['contribuyente_especial'] ?? null, $empresa['obligado_contabilidad'] ?? 'NO',
        $total_retenido, $comentarios,
        $clave_acceso,
        $empresa['ambiente_id'] ?? 1, $punto['establecimiento'], $punto['punto_emision'], $nuevo_secuencial
    ]);
    $comprobante_id = $pdo->lastInsertId();

    // Insertar detalles (detalle_retencion)
    $sql_det = "INSERT INTO detalle_retencion (
        comprobante_retencion_id,
        cod_sustento, cod_doc_nr, num_documento,
        fecha_emision_doc_sustento, num_aut_documento, fecha_autorizacion_doc_sustento,
        monto_total, pago_loc_ext, total_sin_impuestos,
        cod_impuesto_doc_sustento, codigo_porcentaje, base_imponible, tarifa, valor_impuesto,
        codigo_retencion, codigo_impuesto_retencion, base_imponible_retencion, porcentaje_retener, valor_retenido
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_det = $pdo->prepare($sql_det);

    foreach ($docs as $doc) {
        $codSustento = $doc['cod_sustento'] ?? '01';
        $codDocNR = $doc['cod_doc_nr'] ?? '01';
        $numDocumento = $doc['num_documento'] ?? '';
        $fechaEmisionDoc = $doc['fecha_emision_doc_sustento'] ?? null;
        $numAutDoc = $doc['num_aut_documento'] ?? '';
        $fechaAutDoc = $doc['fecha_autorizacion_doc_sustento'] ?? null;
        $montoTotal = max(0, round(floatval($doc['monto_total'] ?? 0), 2));
        $pagoLocExt = $doc['pago_loc_ext'] ?? '01';
        $totalSinImp = max(0, round(floatval($doc['total_sin_impuestos'] ?? 0), 2));
        $codImpDoc = $doc['cod_impuesto_doc_sustento'] ?? '2';
        $codPorcentaje = $doc['codigo_porcentaje'] ?? '0';
        $baseImp = max(0, round(floatval($doc['base_imponible'] ?? 0), 2));
        $tarifa = max(0, round(floatval($doc['tarifa'] ?? 0), 2));
        $valorImp = max(0, round(floatval($doc['valor_impuesto'] ?? 0), 2));

        $retenciones = $doc['retenciones'] ?? [];

        if (empty($retenciones)) {
            // Insertar al menos un registro aunque no tenga retenciones
            $stmt_det->execute([
                $comprobante_id,
                $codSustento, $codDocNR, $numDocumento,
                $fechaEmisionDoc, $numAutDoc, $fechaAutDoc,
                $montoTotal, $pagoLocExt, $totalSinImp,
                $codImpDoc, $codPorcentaje, $baseImp, $tarifa, $valorImp,
                0, '', 0, 0, 0
            ]);
        } else {
            foreach ($retenciones as $ret) {
                $codImpRet = $ret['codigo_impuesto_retencion'] ?? '';
                $codigoRet = (int)($ret['codigo_retencion'] ?? 0);
                $porcRet = max(0, round(floatval($ret['porcentaje_retener'] ?? 0), 2));
                $baseRet = max(0, round(floatval($ret['base_imponible_retencion'] ?? 0), 2));
                $valRet = max(0, round(floatval($ret['valor_retenido'] ?? 0), 2));

                $stmt_det->execute([
                    $comprobante_id,
                    $codSustento, $codDocNR, $numDocumento,
                    $fechaEmisionDoc, $numAutDoc, $fechaAutDoc,
                    $montoTotal, $pagoLocExt, $totalSinImp,
                    $codImpDoc, $codPorcentaje, $baseImp, $tarifa, $valorImp,
                    $codigoRet, $codImpRet, $baseRet, $porcRet, $valRet
                ]);
            }
        }
    }

    // Actualizar secuencial en punto_emision
    $pdo->prepare("UPDATE punto_emision SET secuencial_comprobante_retencion = ? WHERE id = ?")
        ->execute([$nuevo_secuencial, $punto_emision_id]);

    $pdo->commit();

    header("Location: comprobante_retencion_lista.php?msg=success&id=$comprobante_id");
    exit;

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollback();
    error_log("Error en comprobante_retencion_graba.php: " . $e->getMessage());
    header("Location: comprobante_retencion_nueva.php?msg=error&error=" . urlencode($e->getMessage()));
    exit;
}
