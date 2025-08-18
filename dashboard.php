<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$empleadosActivos = ['acampantes_aprobados.php', 'aprobar_acampantes.php', 'eliminar_empleado.php','pre_contabilidad.php'];
$acampantesActivos = ['mis_acampantes.php', 'registro_de_pagos.php'];
$gestionActivo = ['usuarios.php', 'gestion_de_tablas.php'];
$esDropdownAcampantesActivo = in_array($currentPage, $acampantesActivos);
$esDropdownEmpleadosActivo = in_array($currentPage, $empleadosActivos);
$esDropdownGestionActivo = in_array($currentPage, $gestionActivo);
?>

<style>
    /* Estilos para la barra de navegación */
    .navbar-custom {
        background-color: #6c757d; /* Gris medio */
    }

    /* Estilo para los enlaces de la barra de navegación */
    .navbar-dark .navbar-nav .nav-link,
    .navbar-dark .navbar-nav .dropdown-item {
        color: #f9f5f5;
        padding-left: 0.5em;
    }

    /* Estilo para el enlace activo */
    .navbar-dark .navbar-nav .nav-item .active,
    .navbar-dark .navbar-nav .nav-link:hover,
    .navbar-dark .navbar-nav .dropdown-item:hover,
    .navbar-dark .navbar-nav .dropdown-item.active {
        background-color: black !important;
        color: white !important;
    }

    /* Estilo para los elementos del menú desplegable */
    .dropdown-menu {
        background-color: #a8adb1;
        border-color: #a8adb1;
        padding-top: 0;
        padding-bottom: 0;
        margin-top: 0;
    }

    .dropdown-item {
        border-radius: 5px;
    }

    /* Estilo para mostrar el menú desplegable al pasar el mouse */
    .nav-item.dropdown:hover .dropdown-menu {
        display: block;
    }
</style>

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom fixed-top" style="padding-top: 0; padding-bottom: 0;">
    <div class="container-fluid" style="padding-left: 0; padding-right: 0;">
        <a class="navbar-brand" href="index.php" style="margin-left: 1.0em;">Sistema</a>

        <div class="nav-item dropdown d-lg-none ms-auto me-2">
            <a class="nav-link dropdown-toggle text-white" href="#" id="usuarioDropdownMobile" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Invitado') ?>
            </a>
            <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="usuarioDropdownMobile" style="background-color: #a8adb1; padding: 0;">
                <li><a class="dropdown-item" href="editar_perfil.php">Editar perfil</a></li>
                <li><hr class="dropdown-divider" style="margin:0"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
            </ul>
        </div>

        <button class="navbar-toggler" style="margin: 5px;" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContenido" aria-controls="navbarContenido" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContenido">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'alta_acampante.php' ? 'active' : '' ?>" href="alta_acampante.php">Inscribir Acampante</a>
                </li>
                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $esDropdownAcampantesActivo ? 'active' : '' ?>" href="#" id="acampantesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        Mis Acampantes
                    </a>
                    <ul class="dropdown-menu" aria-labelledby="acampantesDropdown">
                        <li><a class="dropdown-item <?= $currentPage === 'mis_acampantes.php' ? 'active' : '' ?>" href="mis_acampantes.php">Ver Acampantes</a></li>
                        <li><a class="dropdown-item <?= $currentPage === 'registro_de_pagos.php' ? 'active' : '' ?>" href="registro_de_pagos.php">Registro de Pagos</a></li>
                    </ul>
                </li>
                
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $esDropdownEmpleadosActivo ? 'active' : '' ?>" href="#" id="empleadosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Gestión de Acampantes
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="empleadosDropdown">
                            <li><a class="dropdown-item <?= $currentPage === 'acampantes_aprobados.php' ? 'active' : '' ?>" href="acampantes_aprobados.php">Aprobados</a></li>
                            <li><a class="dropdown-item <?= $currentPage === 'aprobar_acampantes.php' ? 'active' : '' ?>" href="aprobar_acampantes.php">Por Aprobar</a></li>
                            <li><a class="dropdown-item <?= $currentPage === 'elimina_empleado.php' ? 'active' : '' ?>" href="elimina_empleado.php">Eliminar</a></li>
                            <li><a class="dropdown-item <?= $currentPage === 'pre_contabilidad.php' ? 'active' : '' ?>" href="pre_contabilidad.php">Contabilidad</a></li>
                        </ul>
                    </li>

                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle <?= $esDropdownGestionActivo ? 'active' : '' ?>" href="#" id="gestionDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            Gestion
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="gestionDropdown">
                            <li><a class="dropdown-item <?= $currentPage === 'usuarios.php' ? 'active' : '' ?>" href="usuarios.php">Gestión de Usuarios</a></li>
                            <li><a class="dropdown-item <?= $currentPage === 'gestion_de_tablas.php' ? 'active' : '' ?>" href="gestion_de_tablas.php">Gestión de Tablas</a></li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="d-none d-lg-block" style="margin-right: 10px;">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-white" href="#" id="usuarioDropdownDesktop" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Invitado') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="usuarioDropdownDesktop" style="background-color: #a8adb1; padding: 0;">
                        <li><a class="dropdown-item" href="editar_perfil.php">Editar perfil</a></li>
                        <li><hr class="dropdown-divider" style="margin:0"></li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav>