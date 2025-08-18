<?php
include 'conexion.php';
session_start();
$usuario_id = $_SESSION['usuario_id'] ?? null;
if (!$usuario_id) {
    header('Location: login.php');
    exit;
}

$query_cargos = "SELECT id, nombre FROM cargos ORDER BY nombre";
$query_regiones = "SELECT id, nombre FROM regiones ORDER BY nombre";
$query_sexo = "SELECT id, sexo FROM sexo ORDER BY sexo";

$cargos = $conexion->query($query_cargos);
$regiones = $conexion->query($query_regiones);
$sexos = $conexion->query($query_sexo);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inscripción de Acampante</title>
    <?php include 'links-recursos.php'; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
                            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
                            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" required>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="edad" class="form-label">Edad</label>
                            <input type="number" name="edad" id="edad" class="form-control" readonly>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="cedula" class="form-label">Cédula</label>
                            <input type="text" name="cedula" id="cedula" class="form-control" required pattern="\d+">
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="sexo" class="form-label">Sexo</label>
                            <select name="sexo_id" id="sexo" class="form-select" required>
                                <option value="" selected>----Seleccione----</option>
                                <?php while ($row = $sexos->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['sexo']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="foto" class="form-label">Foto del Acampante</label>
                            <input type="file" name="foto" id="foto" class="form-control" >
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="cargo" class="form-label">Tipo de Acampante</label>
                            <select name="cargo_id" id="cargo" class="form-select" required>
                                <option value="" selected>----Seleccione----</option>
                                <?php $cargos->data_seek(0); ?>
                                <?php while ($row = $cargos->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="region" class="form-label">Zona</label>
                            <select name="region_id" id="region" class="form-select" required>
                                <option value="" selected>----Seleccione----</option>
                                <?php while ($row = $regiones->fetch_assoc()): ?>
                                    <option value="<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="tienda" class="form-label">Distrito</label>
                            <select name="tienda_id" id="tienda" class="form-select" required disabled>
                                <option value="" selected>----Seleccione----</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="departamento" class="form-label">Iglesia</label>
                            <select name="departamento_id" id="departamento" class="form-select" required disabled>
                                <option value="" selected>----Seleccione----</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="club" class="form-label">Club</label>
                            <select name="club_id" id="club" class="form-select" disabled>
                                <option value="" selected>----Seleccione----</option>
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
    <div class="modal-dialog modal-dialog-centered">
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
    <div class="modal-dialog modal-dialog-centered">
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
$(document).ready(function() {
    const form = $('#form-acampante');
    const myModal = new bootstrap.Modal(document.getElementById('validationModal'));
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    const modalMessage = $('#modal-message');
    const loaderOverlay = $('#loader-overlay');
    const tiempoMinimo = 2000; // 2 segundos

    let loaderTimeout;
    let fotoCapturada = null;

    function showLoader() {
        loaderOverlay.css('display', 'flex');
        loaderTimeout = Date.now();
    }

    function hideLoader(callback) {
        const timeElapsed = Date.now() - loaderTimeout;
        const delay = Math.max(0, tiempoMinimo - timeElapsed);

        setTimeout(() => {
            loaderOverlay.css('display', 'none');
            if (callback) {
                callback();
            }
        }, delay);
    }

    function showModalAndHighlight(message, fieldToFocus) {
        modalMessage.text(message);
        myModal.show();
        if (fieldToFocus) {
            fieldToFocus.focus();
            fieldToFocus.addClass('is-invalid');
        }
    }

    // Calcular edad y habilitar/deshabilitar Cédula
    $('#fecha_nacimiento').on('change', function() {
        const fechaNacimiento = new Date($(this).val());
        const hoy = new Date();
        let edad = hoy.getFullYear() - fechaNacimiento.getFullYear();
        const m = hoy.getMonth() - fechaNacimiento.getMonth();
        if (m < 0 || (m === 0 && hoy.getDate() < fechaNacimiento.getDate())) {
            edad--;
        }
        $('#edad').val(edad);
        
        if (edad >= 10) {
            $('#cedula').prop('required', true).prop('disabled', false);
        } else {
            $('#cedula').prop('required', false).prop('disabled', true).val('');
        }
    });
    
    // Procesa la imagen, la rota y la guarda para el envío
    $('#foto').on('change', function(e) {
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

    // Funcionalidad dinámica para los selectores: Región -> Distrito -> Iglesia -> Club
    function cargarOpciones(tipo, parentId, selectElement) {
        selectElement.prop('disabled', true).html('<option value="">Cargando...</option>');
        
        if (!parentId) {
            selectElement.html('<option value="">----Seleccione----</option>');
            return;
        }

        $.ajax({
            url: 'obtener_opciones.php',
            type: 'GET',
            data: { tipo: tipo, parent_id: parentId },
            dataType: 'json',
            success: function(response) {
                selectElement.prop('disabled', false).html('<option value="">----Seleccione----</option>');
                if (response.success && response.data.length > 0) {
                    $.each(response.data, function(index, item) {
                        const nombreKey = tipo == 'clubes' ? 'nombre' : 'nombre';
                        selectElement.append(`<option value="${item.id}">${item[nombreKey]}</option>`);
                    });
                }
            }
        });
    }

    $('#region').on('change', function() {
        const regionId = $(this).val();
        cargarOpciones('tiendas', regionId, $('#tienda'));
        $('#departamento').prop('disabled', true).html('<option value="">----Seleccione----</option>');
        $('#club').prop('disabled', true).html('<option value="">----Seleccione----</option>');
    });

    $('#tienda').on('change', function() {
        const tiendaId = $(this).val();
        cargarOpciones('departamentos', tiendaId, $('#departamento'));
        $('#club').prop('disabled', true).html('<option value="">----Seleccione----</option>');
    });

    $('#departamento').on('change', function() {
        const departamentoId = $(this).val();
        cargarOpciones('clubes', departamentoId, $('#club'));
    });
    
    // Envío del formulario
    form.on('submit', function (e) {
        e.preventDefault();
        
        form.find('.form-control, .form-select').removeClass('is-invalid');

        const requiredFields = [
            { id: 'nombre', message: 'El campo "Nombre" es obligatorio.' },
            { id: 'telefono', message: 'El campo "Teléfono" es obligatorio.' },
            { id: 'fecha_nacimiento', message: 'El campo "Fecha de Nacimiento" es obligatorio.' },
            { id: 'sexo', message: 'El campo "Sexo" es obligatorio.' },
            { id: 'cargo', message: 'El campo "Tipo de Acampante" es obligatorio.' },
            { id: 'region', message: 'El campo "Zona" es obligatorio.' },
            { id: 'tienda', message: 'El campo "Distrito" es obligatorio.' },
            { id: 'departamento', message: 'El campo "Iglesia" es obligatorio.' }
        ];

        for (const field of requiredFields) {
            const input = $('#' + field.id);
            if (!input.val().trim()) {
                showModalAndHighlight(field.message, input);
                return;
            }
        }
        
        const edad = parseInt($('#edad').val());
        const cedulaInput = $('#cedula');

        if (edad >= 10) {
            if (!cedulaInput.val().trim()) {
                showModalAndHighlight('Para mayores de 10 años, el campo "Cédula" es obligatorio.', cedulaInput);
                return;
            }
            if (!cedulaInput.val().trim().match(/^\d+$/)) {
                showModalAndHighlight('La cédula debe contener solo números.', cedulaInput);
                return;
            }
        }
        
        const submitBtn = form.find('button[type="submit"]');
        submitBtn.prop('disabled', true);
        
        showLoader();

        // Verificar cédula
        fetch(`verificar_cedula.php?cedula=${cedulaInput.val().trim()}`)
            .then(response => response.json())
            .then(result => {
                if (result.existe && edad >= 10) {
                    hideLoader(() => {
                        showModalAndHighlight('Esta cédula ya está registrada.', cedulaInput);
                        submitBtn.prop('disabled', false);
                    });
                } else {
                    const formData = new FormData(form[0]);
                    
                    if (fotoCapturada) {
                        formData.delete('foto');
                        formData.append('foto', fotoCapturada, fotoCapturada.name);
                    }

                    // Enviar formulario si la cédula es válida
                    fetch(form.attr('action'), {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(result => {
                        hideLoader(() => {
                            if (result.success) {
                                successModal.show();
                                form[0].reset();
                                $('#tienda').prop('disabled', true).html('<option value="">----Seleccione----</option>');
                                $('#departamento').prop('disabled', true).html('<option value="">----Seleccione----</option>');
                                $('#club').prop('disabled', true).html('<option value="">----Seleccione----</option>');
                            } else {
                                showModalAndHighlight(result.message || 'Ocurrió un error al guardar el registro.', null);
                            }
                        });
                    })
                    .catch(error => {
                        hideLoader(() => {
                            showModalAndHighlight('Error de conexión con el servidor. Inténtelo de nuevo.', null);
                            console.error('AJAX error:', error);
                        });
                    })
                    .finally(() => {
                        submitBtn.prop('disabled', false);
                    });
                }
            })
            .catch(error => {
                hideLoader(() => {
                    showModalAndHighlight('Error al verificar la cédula. Inténtelo de nuevo.', null);
                    console.error('Verificar cédula error:', error);
                    submitBtn.prop('disabled', false);
                });
            });
    });
});
</script>
</body>
</html>