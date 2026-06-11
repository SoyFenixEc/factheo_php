<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

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

    // Manejo de la foto
    $foto = null;
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $foto_name = time() . '_' . $_FILES['foto']['name'];
        $foto_tmp = $_FILES['foto']['tmp_name'];
        $foto_path = '../md_productos/img/' . $foto_name;
        if (move_uploaded_file($foto_tmp, $foto_path)) {
            $foto = $foto_path;
        }
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