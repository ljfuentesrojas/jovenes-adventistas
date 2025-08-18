<?php
session_start();
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'];
    $clave = $_POST['clave'];

    // Se agrega la columna 'estado' a la consulta SQL para verificarla
    $stmt = $conexion->prepare("SELECT id, nombre, clave, rol, correo, estado FROM usuarios WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        // Verifica la clave hasheada
        if (password_verify($clave, $usuario['clave'])) {
            // NUEVA VERIFICACIÓN: Revisa si el estado del usuario es 'activo'
            if ($usuario['estado'] === 'activo') {
                $_SESSION['usuario'] = $usuario;
                $_SESSION['usuario_id']  = $usuario['id'];
                $_SESSION['usuario_nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                header("Location: index.php");
                exit;
            } else {
                // Mensaje para usuarios que no han activado su cuenta
                $error = "Por favor, activa tu cuenta haciendo clic en el enlace de tu correo electrónico.";
            }
        } else {
            $error = "Clave incorrecta.";
        }
    } else {
        $error = "Correo no registrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            /* Estilos para el fondo de pantalla */
            background-image: linear-gradient(rgba(0, 0, 0, 0.5), rgba(0, 0, 0, 0.5)), url('https://tripates.com/media/canada-alberta-lake-louise-best-campgrounds-rv-camping-area-bridge.jpg');
            background-size: cover;
            background-position: center center;
            background-attachment: fixed;
            color: #f8f9fa; /* Color del texto blanco para mayor contraste */
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
        <div class="card shadow rounded-4 bg-white p-4">
            <div class="card-body">
                <h3 class="text-center mb-4">Iniciar Sesión</h3>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= $error ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Clave</label>
                        <input type="password" name="clave" class="form-control" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Entrar</button>
                    </div>
                </form>
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-link text-decoration-none" data-bs-toggle="modal" data-bs-target="#registroModal">
                        ¿No tienes cuenta? Regístrate aquí
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="registroModal" tabindex="-1" aria-labelledby="registroModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="color: black;">
        <div class="modal-content">
            <form id="formRegistro">
                <div class="modal-header">
                    <h5 class="modal-title" id="registroModalLabel">Registro de Usuario</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div id="mensajeRegistro"></div>
                    <div class="mb-3">
                        <label class="form-label">Nombre</label>
                        <input type="text" name="nombre" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Correo</label>
                        <input type="email" name="correo_confirmar" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Clave</label>
                        <input type="password" name="clave" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Clave</label>
                        <input type="password" name="clave_confirmar" class="form-control" required>
                    </div>
                    <input type="hidden" name="rol" value="usuario">
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-success">Registrarse</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.getElementById('formRegistro').addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const datos = new FormData(form);
        const mensaje = document.getElementById('mensajeRegistro');

        // Validaciones
        if (datos.get('correo') !== datos.get('correo_confirmar')) {
            mensaje.innerHTML = `<div class="alert alert-danger">Los correos no coinciden.</div>`;
            return;
        }

        if (datos.get('clave') !== datos.get('clave_confirmar')) {
            mensaje.innerHTML = `<div class="alert alert-danger">Las claves no coinciden.</div>`;
            return;
        }

        fetch('registro_ajax.php', {
            method: 'POST',
            body: datos
        })
        .then(res => res.json())
        .then(data => {
            if (data.exito) {
                mensaje.innerHTML = `<div class="alert alert-success">${data.mensaje}</div>`;
                form.reset();
            } else {
                mensaje.innerHTML = `<div class="alert alert-danger">${data.mensaje}</div>`;
            }
        })
        .catch(error => {
            mensaje.innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`;
            console.error('Error:', error);
        });
    });
</script>
</body>
</html>