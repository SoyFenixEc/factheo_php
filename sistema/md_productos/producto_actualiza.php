<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

/**
 * Comprime y redimensiona una imagen, guardando JPG o PNG optimizado
 */
function comprimirImagen($origen, $destino, $tipo, $max_ancho = 300) {
    list($ancho, $alto) = getimagesize($origen);
    if ($ancho > $max_ancho) {
        $ratio = $max_ancho / $ancho;
        $nuevo_ancho = $max_ancho;
        $nuevo_alto = intval($alto * $ratio);
    } else {
        $nuevo_ancho = $ancho;
        $nuevo_alto = $alto;
    }
    $lienzo = imagecreatetruecolor($nuevo_ancho, $nuevo_alto);
    if ($tipo == IMAGETYPE_PNG) {
        $img = imagecreatefrompng($origen);
        imagealphablending($lienzo, false);
        imagesavealpha($lienzo, true);
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
        imagepng($lienzo, $destino, 6);
    } elseif ($tipo == IMAGETYPE_WEBP) {
        $img = imagecreatefromwebp($origen);
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
        imagewebp($lienzo, $destino, 70);
    } else {
        $img = imagecreatefromjpeg($origen);
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
        imagejpeg($lienzo, $destino, 70);
    }
    imagedestroy($img);
    imagedestroy($lienzo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $codigo = $_POST['codigo'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $descripcion = $_POST['descripcion'];
    $bodega_id = $_POST['bodega_id'];
    
    // Verificar que el producto pertenece al usuario actual
    $sql_check = "SELECT id FROM productos WHERE id = :id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('No tienes permiso para actualizar este producto.'); window.location.href='producto_lista.php';</script>";
        exit();
    }
    
    // Validar que la bodega pertenece al usuario
    $sql_check_bodega = "SELECT id FROM bodegas WHERE id = :bodega_id AND usuario_id = :usuario_id";
    $stmt_check_bodega = $pdo->prepare($sql_check_bodega);
    $stmt_check_bodega->bindParam(':bodega_id', $bodega_id, PDO::PARAM_INT);
    $stmt_check_bodega->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check_bodega->execute();
    
    if ($stmt_check_bodega->rowCount() === 0) {
        echo "<script>alert('La bodega seleccionada no te pertenece.'); window.location.href='producto_editar.php?id=" . $id . "';</script>";
        exit();
    }

    // Manejo de la foto (solo JPG/PNG, comprimir)
    $foto = null;
    $subio_foto = false;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $img_info = getimagesize($_FILES['foto']['tmp_name']);
        $img_type = $img_info[2] ?? 0;
        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

        if (!in_array($img_type, $allowed_types)) {
            echo "<script>alert('Solo se permiten imágenes JPG, PNG y WEBP.'); window.location.href='producto_editar.php?id=" . $id . "';</script>";
            exit;
        }

        if ($img_type == IMAGETYPE_PNG) {
            $ext = '.png';
        } elseif ($img_type == IMAGETYPE_WEBP) {
            $ext = '.webp';
        } else {
            $ext = '.jpg';
        }
        $foto_name = time() . '_' . md5($_FILES['foto']['name']) . $ext;
        $foto_path = realpath(__DIR__ . '/img/') . '/' . $foto_name;

        comprimirImagen($_FILES['foto']['tmp_name'], $foto_path, $img_type);
        $foto = '../md_productos/img/' . $foto_name;
        $subio_foto = true;
    }

    $sql = "UPDATE productos SET 
            codigo = :codigo, 
            nombre = :nombre, 
            descripcion = :descripcion, 
            precio_unitario = :precio, 
            stock = :stock, 
            bodega_id = :bodega_id";
    if ($subio_foto) {
        $sql .= ", foto = :foto";
    }
    $sql .= " WHERE id = :id AND usuario_id = :usuario_id";

    $params = [
        ':codigo' => $codigo,
        ':nombre' => $nombre,
        ':descripcion' => $descripcion,
        ':precio' => $precio,
        ':stock' => $stock,
        ':bodega_id' => $bodega_id,
        ':id' => $id,
        ':usuario_id' => $usuario_id
    ];
    if ($subio_foto) {
        $params[':foto'] = $foto;
    }

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        echo "<script>alert('Producto actualizado exitosamente.'); window.location.href='producto_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al actualizar el producto: " . addslashes($e->getMessage()) . "'); window.location.href='producto_editar.php?id=" . $id . "';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='producto_lista.php';</script>";
}
?>
