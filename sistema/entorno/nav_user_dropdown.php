<li class="nav-item dropdown no-arrow">
    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-user fa-fw mr-2 text-gray-600"></i>
        <span class="mr-2 d-none d-lg-inline text-gray-600 small">
            <?php
            // Validar que el índice "usuario_nombre" existe en la sesión
            $usuario = isset($_SESSION['usuario_nombre']) ? $_SESSION['usuario_nombre'] : 'Invitado';
            echo $usuario;
            ?>
        </span>
    </a>
    <!-- Dropdown - Información de usuario -->
    <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="userDropdown">
        <!--
		<a class="dropdown-item" href="#">
            <i class="fas fa-user fa-sm fa-fw mr-2 text-gray-400"></i>
            Perfil
        </a>
        <a class="dropdown-item" href="#">
            <i class="fas fa-cogs fa-sm fa-fw mr-2 text-gray-400"></i>
            Configuración
        </a>
        <a class="dropdown-item" href="#">
            <i class="fas fa-list fa-sm fa-fw mr-2 text-gray-400"></i>
            Registro de Actividades
        </a>
        <a class="dropdown-item" href="/factheo/sistema/md_registros/pagina_sesiones.php">
            <i class="fas fa-clock fa-sm fa-fw mr-2 text-gray-400"></i>
            Registro de Sesiones
        </a>
		-->
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="/factheo/sistema/md_autenticacion/logout.php">
            <i class="fas fa-sign-out-alt fa-sm fa-fw mr-2 text-gray-400"></i>
            Cerrar sesión
        </a>
    </div>
</li>
