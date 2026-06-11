<?php
require('../md_autenticacion/sesion.php');
require('../md_config/conexion.php');

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

    $sql = "UPDATE productos SET 
            codigo = :codigo, 
            nombre = :nombre, 
            descripcion = :descripcion, 
            precio_unitario = :precio, 
            stock = :stock, 
            bodega_id = :bodega_id";
    if ($foto) {
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
    if ($foto) {
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