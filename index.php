12345
<?php
session_start();

// Si ya hay sesión activa, redirigir al dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: sistema/md_dashboard/dashboard.php");
    exit();
}

// HTTPS redirect desactivado para desarrollo local
require('sistema/md_config/constants.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Iniciar Sesión / Registrarse</title>
    <?php
    require('sistema/entorno/meta.php');
    require('sistema/entorno/title.php');
    require('sistema/entorno/link.php');
    ?>
    <style>
        body.bg-gradient-light {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        .container {
            width: 100%;
            max-width: 1200px;
        }
        .bg-login-image {
            background: url('img/flayer_login.png');
            background-size: cover;
            background-position: center;
            width: 600px;
            height: 800px;
        }
        .logo-system {
            background: url('img/logo.png');
            background-size: cover;
            background-position: center;
            width: 200px;
            height: 100px;
            margin: 0 auto 10px;
            display: block;
        }
        @media (max-width: 991.98px) {
            .card {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
            }
        }
        .nav-tabs {
            border: none;
            justify-content: center;
            margin-bottom: 2rem;
        }
        .nav-tabs .nav-link {
            color: #6e707e;
            font-weight: 600;
            border: none;
            border-bottom: 3px solid transparent;
            padding: 0.5rem 1.5rem;
            margin: 0 0.5rem;
            border-radius: 0;
        }
        .nav-tabs .nav-link.active {
            color: #4e73df;
            border-bottom: 3px solid #4e73df;
            background-color: transparent;
        }
        .tab-content {
            padding: 0 1rem;
        }
        .tab-pane {
            animation: fadeIn 0.3s ease-in;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .form-container {
            max-width: 400px;
            margin: 0 auto;
        }
    </style>
</head>
<body class="bg-gradient-light">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-12 col-md-9">
                <div class="card o-hidden border-0 shadow-lg">
                    <div class="card-body p-0">
                        <div class="row">
                            <div class="col-lg-6 d-none d-lg-block bg-login-image"></div>
                            <div class="col-lg-6">
                                <div class="p-5">
                                    <div alt="Logo del Sistema" class="logo-system"></div>
                                    
                                    <ul class="nav nav-tabs" id="authTabs" role="tablist">
                                        <li class="nav-item">
                                            <a class="nav-link active" id="login-tab" data-toggle="tab" href="#login" role="tab" aria-controls="login" aria-selected="true">Iniciar Sesión</a>
                                        </li>
                                        <li class="nav-item">
                                            <a class="nav-link" id="register-tab" data-toggle="tab" href="#register" role="tab" aria-controls="register" aria-selected="false">Registrarse</a>
                                        </li>
                                    </ul>
                                    
                                    <div class="tab-content" id="authTabsContent">
                                        <div class="tab-pane fade show active" id="login" role="tabpanel" aria-labelledby="login-tab">
                                            <div class="text-center">
                                                <h1 class="h4 text-gray-900 mb-4">Bienvenido de nuevo</h1>
                                            </div>
                                            <?php if (isset($_GET['error'])): ?>
                                                <?php
                                                $mensaje = '';
                                                $tipo = 'danger';
                                                switch ($_GET['error']) {
                                                    case 'correo_invalido': $mensaje = 'El formato del correo electrónico no es válido.'; break;
                                                    case 'usuario_no_existe': $mensaje = 'El usuario no existe. Verifica tu correo.'; break;
                                                    case 'contrasena_incorrecta': $mensaje = 'La contraseña es incorrecta.'; break;
                                                    case 'error_sesion': $mensaje = 'Error al guardar sesión.'; break;
                                                    case 'error_servidor': $mensaje = 'Error en el servidor.'; break;
                                                    default: $mensaje = 'Correo o contraseña incorrectos.'; break;
                                                }
                                                ?>
                                                <div class="alert alert-<?php echo $tipo; ?> text-center"><?php echo htmlspecialchars($mensaje); ?></div>
                                            <?php endif; ?>
                                            
                                            <div class="form-container">
                                                <form method="POST" action="sistema/md_autenticacion/valida.php">
                                                    <div class="form-group">
                                                        <input type="text" class="form-control form-control-user" id="email" name="email" placeholder="Correo electrónico" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <input type="password" class="form-control form-control-user" id="contrasena" name="contrasena" placeholder="Contraseña" required>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-user btn-block">Iniciar Sesión</button>
                                                </form>
                                            </div>
                                        </div>
                                        
                                        <div class="tab-pane fade" id="register" role="tabpanel" aria-labelledby="register-tab">
                                            <div class="text-center">
                                                <h1 class="h4 text-gray-900 mb-4">Crear una cuenta</h1>
                                            </div>
                                            <?php if (isset($_GET['registro'])): ?>
                                                <?php if ($_GET['registro'] == 'exito'): ?>
                                                    <div class="alert alert-success text-center">¡Registro exitoso!</div>
                                                <?php else: ?>
                                                    <div class="alert alert-danger text-center">
                                                        <?php 
                                                        switch ($_GET['mensaje'] ?? '') {
                                                            case 'contrasenas_no_coinciden': echo 'Las contraseñas no coinciden.'; break;
                                                            case 'correo_invalido': echo 'Formato de correo inválido.'; break;
                                                            case 'correo_existente': echo 'El correo ya está registrado.'; break;
                                                            case 'error_insertar': echo 'Error al registrar.'; break;
                                                            default: echo 'Error al registrar.';
                                                        }
                                                        ?>
                                                    </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <div class="form-container">
                                                <form method="POST" action="sistema/md_autenticacion/registro.php">
                                                    <div class="form-group">
                                                        <input type="text" class="form-control form-control-user" id="nombre_completo" name="nombre_completo" placeholder="Nombre completo" autocomplete="off" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <input type="email" class="form-control form-control-user" id="email_registro" name="email" placeholder="Correo electrónico" autocomplete="off" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <input type="password" class="form-control form-control-user" id="contrasena_registro" name="contrasena" placeholder="Contraseña" autocomplete="off" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <input type="password" class="form-control form-control-user" id="confirmar_contrasena" name="confirmar_contrasena" placeholder="Confirmar contraseña" autocomplete="off" required>
                                                    </div>
                                                    <div class="form-group">
                                                        <div class="custom-control custom-checkbox small">
                                                            <input type="checkbox" class="custom-control-input" id="terminos" name="acepto_terminos" value="1" required>
                                                            <label class="custom-control-label" for="terminos">Acepto los <a href="#" target="_blank" class="text-primary">Términos y Condiciones</a></label>
                                                        </div>
                                                    </div>
                                                    <button type="submit" class="btn btn-primary btn-user btn-block">Registrarse</button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require('sistema/entorno/script.php'); ?>
</body>
</html>
