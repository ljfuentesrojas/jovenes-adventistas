<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

include 'conexion.php';

// Determinar el rol del usuario
$es_admin = ($_SESSION['rol'] === 'admin');

if ($es_admin) {
    // Si es admin, obtener el conteo de TODOS los acampantes
    $total_acampantes = $conexion->query("SELECT COUNT(id) FROM empleados")->fetch_row()[0];
    $acampantes_pendientes = $conexion->query("SELECT COUNT(id) FROM empleados WHERE inscrito = 0")->fetch_row()[0];
    $acampantes_aprobados = $conexion->query("SELECT COUNT(id) FROM empleados WHERE inscrito = 1")->fetch_row()[0];
} else {
    // Si no es admin, obtener el conteo de los acampantes registrados por este usuario
    $usuario_id = $_SESSION['usuario_id'];
    $total_acampantes = $conexion->query("SELECT COUNT(id) FROM empleados WHERE creado_por = '$usuario_id'")->fetch_row()[0];
    $acampantes_pendientes = $conexion->query("SELECT COUNT(id) FROM empleados WHERE creado_por = '$usuario_id' AND inscrito = 0")->fetch_row()[0];
    $acampantes_aprobados = $conexion->query("SELECT COUNT(id) FROM empleados WHERE creado_por = '$usuario_id' AND inscrito = 1")->fetch_row()[0];
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inicio - Sistema de Acampantes</title>
    <?php include 'links-recursos.php'; ?>
    <style>
        /* Estilos CSS personalizados para mejorar el diseño en móviles */
        @media (max-width: 767.98px) {
            .card-text.fs-1 {
                font-size: 2.5rem !important; /* Ajusta el tamaño de la fuente en móviles */
            }
            .btn-lg {
                width: 100%; /* El botón ocupa todo el ancho en pantallas pequeñas */
            }
        }
        /* Color de fondo para el body */
        body {
            background-color: #f8f9fa; /* o el color que prefieras */
        }
    </style>
</head>
<body style="padding-top: 70px;">
    <?php include 'dashboard.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center mb-5">
            <div class="col-12 col-md-10 col-lg-8 text-center" style="color: white; font-weight: bold;">
                <h1 class="display-5">Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre']) ?></h1>
                <p class="lead">Administra tus acampantes de forma rápida y sencilla.</p>
                <a href="alta_acampante.php" class="btn btn-primary btn-lg mt-3">Registrar nuevo acampante</a>
            </div>
        </div>

        <h3 class="text-center mb-4">Resumen de Acampantes</h3>
        <div class="row text-center">
            <div class="col-12 col-md-4 mb-3">
                <div class="card text-white bg-primary">
                    <a href="mis_acampantes.php" class="btn btn-primary btn-lg">
                        <div class="card-body">
                            <h5 class="card-title">Total de Acampantes</h5>
                            <p class="card-text fs-1"><?= $total_acampantes ?></p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <div class="card text-white bg-warning">
                    <a href="aprobar_acampantes.php" class="btn btn-warning btn-lg">
                        <div class="card-body">
                            <h5 class="card-title text-white">Pendientes de Aprobación</h5>
                            <p class="card-text fs-1 text-white"><?= $acampantes_pendientes ?></p>
                        </div>
                    </a>
                </div>
            </div>
            <div class="col-12 col-md-4 mb-3">
                <div class="card text-white bg-success">
                    <a href="acampantes_aprobados.php" class="btn btn-success btn-lg">
                        <div class="card-body">
                            <h5 class="card-title text-white">Acampantes Aprobados</h5>
                            <p class="card-text fs-1 text-white"><?= $acampantes_aprobados ?></p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>