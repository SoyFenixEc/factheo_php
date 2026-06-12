<?php
// Configuración de la sesión
session_start();

// Conexión a la base de datos
require('../md_config/conexion.php');

try {
    // Verificar si el formulario fue enviado
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $contrasena = $_POST['contrasena'];
        $recuerdame = isset($_POST['recuerdame']) ? $_POST['recuerdame'] : 0;

        // Registrar los valores para depuración
        error_log("[" . date('Y-m-d H:i:s') . "] Iniciando validación para el usuario: $email");

        // Validar el formato del correo
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log("[" . date('Y-m-d H:i:s') . "] Formato de correo inválido para: $email");
            header("Location: login.php?error=correo_invalido");
            exit();
        }

        // Buscar el usuario en la base de datos
        $sql = "SELECT * FROM usuarios WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':email', $email, PDO::PARAM_STR);
        $stmt->execute();
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            error_log("[" . date('Y-m-d H:i:s') . "] Usuario encontrado: ID={$usuario['id']}, Email={$usuario['email']}");
            if (password_verify($contrasena, $usuario['contrasena'])) {
                error_log("[" . date('Y-m-d H:i:s') . "] Contraseña verificada para el usuario ID={$usuario['id']}");

                // Registrar intento exitoso en auditoría
                $sql_auditoria = "INSERT INTO auditoria_login (usuario_id, ip_address, user_agent, resultado) 
                                  VALUES (:usuario_id, :ip_address, :user_agent, 'exitoso')";
                $stmt_auditoria = $pdo->prepare($sql_auditoria);
                $stmt_auditoria->execute([
                    ':usuario_id' => $usuario['id'],
                    ':ip_address' => $_SERVER['REMOTE_ADDR'],
                    ':user_agent' => $_SERVER['HTTP_USER_AGENT']
                ]);

                // Iniciar sesión
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];

                // Si seleccionó "Recuérdame"
                if ($recuerdame == '1') {
                    try {
                        // Generar un token único
                        $token = bin2hex(random_bytes(32));
                        $ip_address = $_SERVER['REMOTE_ADDR'];
                        $user_agent = $_SERVER['HTTP_USER_AGENT'];
                        $expiracion = date('Y-m-d H:i:s', time() + (86400 * 30)); // 30 días

                        // Depuración de los valores que se intentan insertar
                        error_log("[" . date('Y-m-d H:i:s') . "] Intentando insertar en 'sesiones': usuario_id={$usuario['id']}, token=$token, ip_address=$ip_address, user_agent=$user_agent, expiracion=$expiracion");

                        // Insertar el token en la tabla `sesiones`
                        $sql_sesion = "INSERT INTO sesiones (usuario_id, token, ip_address, user_agent, expiracion) 
                                       VALUES (:usuario_id, :token, :ip_address, :user_agent, :expiracion)";
                        $stmt_sesion = $pdo->prepare($sql_sesion);
                        $stmt_sesion->execute([
                            ':usuario_id' => $usuario['id'],
                            ':token' => $token,
                            ':ip_address' => $ip_address,
                            ':user_agent' => $user_agent,
                            ':expiracion' => $expiracion
                        ]);

                        // Verificar inserción
                        if ($stmt_sesion->rowCount() > 0) {
                            error_log("[" . date('Y-m-d H:i:s') . "] Inserción exitosa en 'sesiones': Usuario ID={$usuario['id']}, Token=$token");
                        } else {
                            throw new Exception("No se pudo insertar en la tabla 'sesiones'.");
                        }

                        // Configurar la cookie con el token
                        setcookie('sesion_token', $token, time() + (86400 * 30), "/", "", true, true);
                    } catch (Exception $e) {
                        // Registrar el error en el log
                        error_log("[" . date('Y-m-d H:i:s') . "] Error al insertar en la tabla 'sesiones': " . $e->getMessage());
                        header("Location: login.php?error=error_sesion");
                        exit();
                    }
                }

                // Redirigir al dashboard
                header("Location: ../md_dashboard/dashboard.php");
                exit();
            } else {
                // Contraseña incorrecta
                error_log("[" . date('Y-m-d H:i:s') . "] Contraseña incorrecta para Usuario ID={$usuario['id']}");
                header("Location: login.php?error=contrasena_incorrecta");
                exit();
            }
        } else {
            // Usuario no existe
            error_log("[" . date('Y-m-d H:i:s') . "] Usuario no encontrado para el correo: $email");
            header("Location: login.php?error=usuario_no_existe");
            exit();
        }
    } else {
        // Si no es POST, redirigir al login
        header("Location: login.php");
        exit();
    }
} catch (PDOException $e) {
    // Manejar errores de conexión o SQL
    error_log("[" . date('Y-m-d H:i:s') . "] Error en el archivo valida.php: " . $e->getMessage());
    header("Location: login.php?error=error_servidor");
    exit();
}
?>