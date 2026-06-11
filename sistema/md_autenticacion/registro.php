<?php
session_start();

// Conexión a la base de datos
require('../md_config/conexion.php');

try {
    // Verificar si el formulario fue enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        // Obtener y sanitizar los datos del formulario
        $nombre_completo = trim($_POST['nombre_completo']);
        $email = trim($_POST['email']);
        $contrasena = $_POST['contrasena'];
        $confirmar_contrasena = $_POST['confirmar_contrasena'];
        
        // Validar que las contraseñas coincidan
        if ($contrasena !== $confirmar_contrasena) {
            error_log("[" . date('Y-m-d H:i:s') . "] Las contraseñas no coinciden para el registro de: $email");
            header("Location: login.php?registro=error&mensaje=contrasenas_no_coinciden");
            exit();
        }
        
        // Validar el formato del correo
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Formato de correo inválido para registro: $email");
            header("Location: login.php?registro=error&mensaje=correo_invalido");
            exit();
        }
        
        // Verificar si el correo ya está registrado
        $sql_check = "SELECT id FROM usuarios WHERE email = :email";
        $stmt_check = $pdo->prepare($sql_check);
        $stmt_check->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt_check->execute();
        
        if ($stmt_check->rowCount() > 0) {
            // El correo ya existe
            error_log("[" . date('Y-m-d H:i:s') . "] Intento de registro con correo ya existente: $email");
            header("Location: login.php?registro=error&mensaje=correo_existente");
            exit();
        }
        
        // Hash de la contraseña
        $contrasena_hash = password_hash($contrasena, PASSWORD_DEFAULT);
        
        // Insertar el nuevo usuario
        $sql_insert = "INSERT INTO usuarios (nombre, email, contrasena, creado_en) 
                       VALUES (:nombre, :email, :contrasena, NOW())";
        $stmt_insert = $pdo->prepare($sql_insert);
        $stmt_insert->bindParam(':nombre', $nombre_completo, PDO::PARAM_STR);
        $stmt_insert->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt_insert->bindParam(':contrasena', $contrasena_hash, PDO::PARAM_STR);
        
        if ($stmt_insert->execute()) {
            // Obtener el ID del usuario recién creado
            $nuevo_usuario_id = $pdo->lastInsertId();
            
            error_log("[" . date('Y-m-d H:i:s') . "] Nuevo usuario registrado con ID: $nuevo_usuario_id, Email: $email");
            
            // Iniciar sesión automáticamente para el nuevo usuario
            $_SESSION['usuario_id'] = $nuevo_usuario_id;
            $_SESSION['usuario_nombre'] = $nombre_completo;
            
            // Redirigir directamente al dashboard (como solicitaste)
            header("Location: ../md_dashboard/dashboard.php");
            exit();
            
        } else {
            // Error al insertar
            error_log("[" . date('Y-m-d H:i:s') . "] Error al insertar nuevo usuario con email: $email");
            header("Location: login.php?registro=error&mensaje=error_insertar");
            exit();
        }
    } else {
        // Si no se accede por POST, redirigir al login
        header("Location: login.php");
        exit();
    }
    
} catch (PDOException $e) {
    // Manejar errores de conexión o SQL
    error_log("[" . date('Y-m-d H:i:s') . "] Error en el archivo registro.php: " . $e->getMessage());
    header("Location: login.php?registro=error&mensaje=error_servidor");
    exit();
}
?>