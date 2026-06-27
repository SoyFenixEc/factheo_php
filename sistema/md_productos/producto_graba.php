<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

/**
 * Comprime y redimensiona una imagen, guardando JPG o PNG optimizado
 */
function comprimirImagen($origen, $destino, $tipo, $max_ancho = 300) {
    list($ancho, $alto) = getimagesize($origen);

    // Calcular nuevas dimensiones manteniendo proporción
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
        imagepng($lienzo, $destino, 6); // compresión PNG 0-9
    } else {
        $img = imagecreatefromjpeg($origen);
        imagecopyresampled($lienzo, $img, 0, 0, 0, 0, $nuevo_ancho, $nuevo_alto, $ancho, $alto);
        imagejpeg($lienzo, $destino, 70); // calidad JPEG 70%
    }

    imagedestroy($img);
    imagedestroy($lienzo);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario_id = $_SESSION['usuario_id'];
    $nombre = $_POST['nombre'];
    $codigo = $_POST['codigo'];
    $precio = $_POST['precio'];
    $stock = $_POST['stock'];
    $descripcion = $_POST['descripcion'];
    $bodega_id = $_POST['bodega_id'];
    
    // Validar que la bodega pertenece al usuario
    $sql_check = "SELECT id FROM bodegas WHERE id = :bodega_id AND usuario_id = :usuario_id";
    $stmt_check = $pdo->prepare($sql_check);
    $stmt_check->bindParam(':bodega_id', $bodega_id, PDO::PARAM_INT);
    $stmt_check->bindParam(':usuario_id', $usuario_id, PDO::PARAM_INT);
    $stmt_check->execute();
    
    if ($stmt_check->rowCount() === 0) {
        echo "<script>alert('La bodega seleccionada no te pertenece.'); window.location.href='producto_nuevo.php';</script>";
        exit();
    }

    // Manejo de la foto (solo JPG/PNG, comprimir)
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $img_info = getimagesize($_FILES['foto']['tmp_name']);
        $img_type = $img_info[2] ?? 0;
        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG];

        if (!in_array($img_type, $allowed_types)) {
            echo "<script>alert('Solo se permiten imágenes JPG y PNG.'); window.location.href='producto_nuevo.php';</script>";
            exit;
        }

        $ext = ($img_type == IMAGETYPE_PNG) ? '.png' : '.jpg';
        $foto_name = time() . '_' . md5($_FILES['foto']['name']) . $ext;
        $foto_path = realpath(__DIR__ . '/img/') . '/' . $foto_name;

        comprimirImagen($_FILES['foto']['tmp_name'], $foto_path, $img_type);
        $foto = '../md_productos/img/' . $foto_name;
    }

    try {
        $sql = "INSERT INTO productos (codigo, nombre, descripcion, precio_unitario, stock, bodega_id, usuario_id, foto) 
                VALUES (:codigo, :nombre, :descripcion, :precio, :stock, :bodega_id, :usuario_id, :foto)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':codigo' => $codigo,
            ':nombre' => $nombre,
            ':descripcion' => $descripcion,
            ':precio' => $precio,
            ':stock' => $stock,
            ':bodega_id' => $bodega_id,
            ':usuario_id' => $usuario_id,
            ':foto' => $foto
        ]);
        echo "<script>alert('Producto guardado exitosamente.'); window.location.href='producto_lista.php';</script>";
    } catch (PDOException $e) {
        echo "<script>alert('Error al guardar el producto: " . addslashes($e->getMessage()) . "'); window.location.href='producto_nuevo.php';</script>";
    }
} else {
    echo "<script>alert('Acceso no permitido.'); window.location.href='producto_lista.php';</script>";
}
?>
