<?php
include 'conexion.php';

$mensaje = "";
$tipo_alerta = "";

$token = $_GET['token'] ?? null;

if (!$token) {
    $mensaje = "Token de activación no proporcionado.";
    $tipo_alerta = "alert-danger";
} else {
    // 1. Buscar el token en la base de datos
    $stmt = $conexion->prepare("SELECT usuario_id FROM activacion_tokens WHERE token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $fila = $resultado->fetch_assoc();
        $usuario_id = $fila['usuario_id'];

        // 2. Activar el usuario
        $stmt_activar = $conexion->prepare("UPDATE usuarios SET estado = 'activo' WHERE id = ?");
        $stmt_activar->bind_param("i", $usuario_id);
        $stmt_activar->execute();
        $stmt_activar->close();

        // 3. Eliminar el token para que no pueda ser usado de nuevo
        $stmt_borrar_token = $conexion->prepare("DELETE FROM activacion_tokens WHERE token = ?");
        $stmt_borrar_token->bind_param("s", $token);
        $stmt_borrar_token->execute();
        $stmt_borrar_token->close();

        $mensaje = "¡Tu cuenta ha sido activada exitosamente! Ya puedes iniciar sesión.";
        $tipo_alerta = "alert-success";

    } else {
        $mensaje = "El enlace de activación es inválido o ya ha sido utilizado.";
        $tipo_alerta = "alert-danger";
    }
    $stmt->close();
}

$conexion->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activación de Cuenta</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            /* Estilos para el fondo de pantalla */
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://tripates.com/media/canada-alberta-lake-louise-best-campgrounds-rv-camping-area-bridge.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            color: #ffffff; /* Color del texto blanco para mayor contraste */
        }
        .card {
            background-color: rgba(0, 0, 0, 0.6); /* Fondo semi-transparente para las tarjetas */
            border: none;
        }
    </style>
</head>
<body class="bg-light">
    <div class="container-fluid vh-100 d-flex justify-content-center align-items-center">
        <div class="col-12 col-sm-8 col-md-6 col-lg-5">
            <div class="card shadow rounded-4 p-4">
                <div class="card-body">
                    <h3 class="text-center mb-4" style="color: white;">Activación de Cuenta</h3>
                    <?php if ($mensaje): ?>
                        <div class="alert <?= $tipo_alerta ?> text-center">
                            <?= $mensaje ?>
                        </div>
                    <?php endif; ?>
                    <div class="d-grid mt-4">
                        <a href="login.php" class="btn btn-primary">Ir a Iniciar Sesión</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>