<?php
include 'conexion.php';
session_start();
$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header('Location: login.php');
    exit;
}

$query_cargos = "SELECT id, nombre FROM cargos ORDER BY nombre";
$query_departamentos = "SELECT id, nombre FROM departamentos ORDER BY nombre";
$query_tiendas = "SELECT id, nombre FROM tiendas ORDER BY nombre";
$query_regiones = "SELECT id, nombre FROM regiones ORDER BY nombre";

$cargos = $conexion->query($query_cargos);
$departamentos = $conexion->query($query_departamentos);
$tiendas = $conexion->query($query_tiendas);
$regiones = $conexion->query($query_regiones);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscripción de Acampante</title>
    <?php include 'links-recursos.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/blueimp-load-image@5.16.0/js/load-image.all.min.js"></script>
    <style>
        .loader-overlay {
            --bs-backdrop-zindex: 1050;
            --bs-backdrop-bg: #000;
            --bs-backdrop-opacity: 0.5;
            position: fixed;
            top: 0;
            left: 0;
            z-index: var(--bs-backdrop-zindex);
            width: 100vw;
            height: 100vh;
            background-color: var(--bs-backdrop-bg);
            opacity: var(--bs-backdrop-opacity);
            display: none;
            justify-content: center;
            align-items: center;
        }
        .loader-spinner {
            color: #fff;
            width: 4rem;
            height: 4rem;
        }
    </style>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card bg-light shadow rounded-4">
                <div class="card-body">
                    <h2 class="text-center mb-4" style="color: black;">Inscribir Acampante</h2>
                    <form id="form-acampante" action="guardar_acampante.php" method="POST" enctype="multipart/form-data" class="row g-3" novalidate>
                        <div class="col-12 col-md-6">
                            <label for="nombre" class="form-label">Nombre</label>
                            <input type="text" name="nombre" id="nombre" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="telefono" class="form-label">Teléfono</label>
                            <input type="tel" name="telefono" id="telefono" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="cedula" class="form-label">Cédula</label>
                            <input type="text" name="cedula" id="cedula" class="form-control" required pattern="\d+">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="foto" class="form-label">Foto</label>
                            <input type="file" name="foto" id="foto" class="form-control">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="cargo" class="form-label">Tipo de Acampante</label>
                            <select name="cargo_id" id="cargo" class="form-select" required>
                                <option value="" selected >----Seleccione----</option>
                                <?php while ($row = $cargos->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="departamento" class="form-label">Iglesia</label>
                            <select name="departamento_id" id="departamento" class="form-select" required>
                                <option value="" selected >----Seleccione----</option>
                                <?php while ($row = $departamentos->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="tienda" class="form-label">Distrito</label>
                            <select name="tienda_id" id="tienda" class="form-select" required>
                                <option value="" selected >----Seleccione----</option>
                                <?php while ($row = $tiendas->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="region" class="form-label">Zona</label>
                            <select name="region_id" id="region" class="form-select" required>
                                <option value="" selected >----Seleccione----</option>
                                <?php while ($row = $regiones->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 text-center mt-4">
                            <button type="submit" class="btn btn-primary px-5">Registrar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="validationModal" tabindex="-1" aria-labelledby="validationModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title" id="validationModalLabel">Error de Validación</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p id="modal-message"></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title" id="successModalLabel">¡Éxito!</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>El registro se ha guardado exitosamente.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
      </div>
    </div>
  </div>
</div>

<div class="loader-overlay" id="loader-overlay">
    <div class="spinner-border loader-spinner" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form-acampante');
    const myModal = new bootstrap.Modal(document.getElementById('validationModal'));
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    const modalMessage = document.getElementById('modal-message');
    const formControls = form.querySelectorAll('.form-control, .form-select');
    const inputFile = document.getElementById('foto');
    const loaderOverlay = document.getElementById('loader-overlay');

    let fotoCapturada = null;

    function showLoader() {
        loaderOverlay.style.display = 'flex';
    }

    function hideLoader() {
        loaderOverlay.style.display = 'none';
    }

    function showModalAndHighlight(message, fieldToFocus) {
        modalMessage.textContent = message;
        myModal.show();
        if (fieldToFocus) {
            fieldToFocus.focus();
            fieldToFocus.classList.add('is-invalid');
        }
    }

    // Listener para la foto, procesa la imagen y la rota si es necesario
    inputFile.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            showLoader();
            loadImage(
                file,
                function(canvas) {
                    canvas.toBlob(function(blob) {
                        blob.name = file.name;
                        fotoCapturada = blob;
                        hideLoader();
                    }, file.type, 0.8);
                },
                {
                    orientation: true,
                    canvas: true
                }
            );
        } else {
            fotoCapturada = null;
        }
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        
        formControls.forEach(control => {
            control.classList.remove('is-invalid');
        });

        const requiredFields = [
            { id: 'nombre', message: 'El campo "Nombre" es obligatorio.' },
            { id: 'telefono', message: 'El campo "Teléfono" es obligatorio.' },
            { id: 'cedula', message: 'El campo "Cédula" es obligatorio.' },
            { id: 'cargo', message: 'El campo "Tipo de Acampante" es obligatorio.' },
            { id: 'departamento', message: 'El campo "Iglesia" es obligatorio.' },
            { id: 'tienda', message: 'El campo "Distrito" es obligatorio.' },
            { id: 'region', message: 'El campo "Zona" es obligatorio.' },
        ];

        for (const field of requiredFields) {
            const input = document.getElementById(field.id);
            if (!input.value.trim()) {
                showModalAndHighlight(field.message, input);
                return;
            }
        }
        
        const cedulaInput = document.getElementById('cedula');
        if (!cedulaInput.value.trim().match(/^\d+$/)) {
            showModalAndHighlight('La cédula debe contener solo números.', cedulaInput);
            return;
        }

        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = true;
        showLoader();

        // Validar si la cédula ya existe antes de enviar el formulario
        fetch(`verificar_cedula.php?cedula=${cedulaInput.value.trim()}`)
            .then(response => response.json())
            .then(result => {
                if (result.existe) {
                    hideLoader();
                    showModalAndHighlight('Esta cédula ya está registrada.', cedulaInput);
                    submitBtn.disabled = false;
                } else {
                    // Si la cédula no existe, proceder con el envío del formulario
                    const formData = new FormData(form);
                    if (fotoCapturada) {
                        formData.delete('foto');
                        formData.append('foto', fotoCapturada, fotoCapturada.name);
                    }

                    fetch(form.action, {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        hideLoader();
                        if (result.success) {
                            successModal.show();
                            form.reset();
                        } else {
                            showModalAndHighlight(result.message || 'Ocurrió un error al guardar el registro.', null);
                        }
                    })
                    .catch(error => {
                        hideLoader();
                        showModalAndHighlight('Error de conexión con el servidor. Inténtelo de nuevo.', null);
                        console.error('AJAX error:', error);
                    })
                    .finally(() => {
                        submitBtn.disabled = false;
                    });
                }
            })
            .catch(error => {
                hideLoader();
                showModalAndHighlight('Error al verificar la cédula. Inténtelo de nuevo.', null);
                console.error('Verificar cédula error:', error);
                submitBtn.disabled = false;
            });
    });
});
</script>
</body>
</html>