<?php
// Incluye los archivos de conexión y sesión
include 'conexion.php';
session_start();

// Verifica si el usuario ha iniciado sesión
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuario_id = $_SESSION['usuario_id'];
$usuario_rol = $_SESSION['rol'] ?? 'usuario';

// Obtener el valor de la cuota
$query_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY id DESC LIMIT 1";
$res_cuota = $conexion->query($query_cuota);

if ($res_cuota && $res_cuota->num_rows > 0) {
    $row_cuota = $res_cuota->fetch_assoc();
    $precio_total_acampante = (float)$row_cuota['valor_cuota'];
} else {
    $precio_total_acampante = 0.00;
    error_log("Error: No se ha definido el valor de la cuota en la tabla 'cuota_acampante'.");
}

// Obtener datos para los filtros de forma segura
$query_departamentos = "SELECT id, nombre FROM departamentos ORDER BY nombre";
$res_departamentos = $conexion->query($query_departamentos);
$departamentos = $res_departamentos ? $res_departamentos->fetch_all(MYSQLI_ASSOC) : [];

$query_clubes = "SELECT id, nombre_club AS nombre FROM clubes ORDER BY nombre";
$res_clubes = $conexion->query($query_clubes);
$clubes = $res_clubes ? $res_clubes->fetch_all(MYSQLI_ASSOC) : [];

// Preparar la consulta principal para cargar la tabla inicial
$query = "
    SELECT
        a.id,
        a.nombre,
        a.cedula,
        d.nombre AS iglesia,
        c.nombre_club AS club,
        ca.nombre AS tipo_acampante,
        COALESCE(SUM(
            CASE
                WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto
                ELSE pc.transaccion_monto / pc.tasa_bcv
            END
        ), 0) AS monto_abonado,
        a.inscrito
    FROM empleados a
    LEFT JOIN departamentos d ON a.departamento_id = d.id
    LEFT JOIN clubes c ON a.club_id = c.id
    LEFT JOIN cargos ca ON a.cargo_id = ca.id
    LEFT JOIN pre_contabilidad pc ON a.id = pc.acampante_id
    LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
";

// Agregar la cláusula WHERE solo si el usuario no es 'admin'
if ($usuario_rol !== 'admin') {
    $query .= " WHERE a.creado_por = ?";
}

$query .= " GROUP BY a.id, a.nombre, a.cedula, d.nombre, c.nombre_club, a.inscrito, ca.nombre ORDER BY a.id ASC";

$stmt = $conexion->prepare($query);

if (!$stmt) {
    die('Error en la preparación de la consulta: ' . $conexion->error);
}

if ($usuario_rol !== 'admin') {
    $stmt->bind_param("i", $usuario_id);
}

$stmt->execute();
$acampantes_iniciales = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Acampantes</title>
    <?php include 'links-recursos.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/blueimp-load-image@5.16.0/js/load-image.all.min.js"></script>
    <style>
        /* Estilos del loader para el modal y carga inicial */
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
            color: #fff; /* Color blanco para el spinner sobre el fondo oscuro */
            width: 4rem;
            height: 4rem;
        }
    </style>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Mis Acampantes Inscritos</h2>
    <div class="alert alert-info text-center" role="alert">
        El costo total por acampante es de <b><?= number_format($precio_total_acampante, 2) ?> USD</b>.
    </div>

    <div class="row mb-3">
        <div class="col-md-6">
            <label for="filtro-iglesia" class="form-label">Filtrar por Iglesia:</label>
            <select id="filtro-iglesia" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($departamentos as $depto): ?>
                    <option value="<?= $depto['id'] ?>"><?= htmlspecialchars($depto['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-6">
            <label for="filtro-club" class="form-label">Filtrar por Club:</label>
            <select id="filtro-club" class="form-select">
                <option value="">Todos</option>
                <?php foreach ($clubes as $club): ?>
                    <option value="<?= $club['id'] ?>"><?= htmlspecialchars($club['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-acampantes">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Tipo de Acampante</th>
                    <th>Iglesia</th>
                    <th>Club</th>
                    <th>Monto Abonado ($)</th>
                    <th>Monto Restante ($)</th>
                    <th>Pago Total</th>
                    <th>Aprobado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="cuerpo-tabla-acampantes">
                <?php
                if ($acampantes_iniciales->num_rows > 0) {
                    while ($row = $acampantes_iniciales->fetch_assoc()):
                        $monto_abonado = (float)($row['monto_abonado'] ?? 0);
                        $monto_restante = $precio_total_acampante - $monto_abonado;
                        $pagado = $monto_abonado >= $precio_total_acampante;
                        $texto_aprobado = $row['inscrito'] == 1 ? 'Aprobado' : 'Pendiente';
                        $clase_badge = $row['inscrito'] == 1 ? 'bg-success' : 'bg-warning text-dark';
                        $pagado_html = $pagado ?
                            '<i class="fas fa-check-circle text-success" title="Pago Total"></i>' :
                            '<i class="fas fa-times-circle text-danger" title="Pago Incompleto"></i>';
                ?>
                        <tr data-id="<?= htmlspecialchars($row['id']) ?>">
                            <td id="cedula-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['cedula']) ?></td>
                            <td id="nombre-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['nombre']) ?></td>
                            <td id="tipo-acampante-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['tipo_acampante'] ?? 'N/A') ?></td>
                            <td id="iglesia-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['iglesia'] ?? '') ?></td>
                            <td id="club-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars($row['club'] ?? 'N/A') ?></td>
                            <td id="monto-abonado-<?= htmlspecialchars($row['id']) ?>"><?= htmlspecialchars(number_format($monto_abonado, 2)) ?></td>
                            <td id="monto-restante-<?= htmlspecialchars($monto_restante, 2) ?>"><?= htmlspecialchars(number_format($monto_restante, 2)) ?></td>
                            <td id="pagado-total-<?= htmlspecialchars($row['id']) ?>"><?= $pagado_html ?></td>
                            <td id="aprobado-<?= htmlspecialchars($row['id']) ?>"><span class="badge <?= htmlspecialchars($clase_badge) ?>"><?= htmlspecialchars($texto_aprobado) ?></span></td>
                            <td>
                                <button class="btn btn-sm btn-primary btn-editar-acampante" data-id="<?= htmlspecialchars($row['id']) ?>">Editar</button>
                            </td>
                        </tr>
                <?php
                    endwhile;
                } else {
                    echo '<tr><td colspan="10" class="text-center">No se encontraron acampantes.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalEditarAcampante" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" id="modalEditarContenido">
        </div>
    </div>
</div>

<div class="modal fade" id="notificacionModal" tabindex="-1" aria-labelledby="notificacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="notificacionLabel">Notificación</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="notificacionMensaje"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="loader-overlay" id="loader-overlay" style="z-index: 2000;">
    <div class="spinner-border loader-spinner" role="status">
        <span class="visually-hidden">Cargando...</span>
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

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const myModal = new bootstrap.Modal(document.getElementById('validationModal'));
        const modalEditar = new bootstrap.Modal(document.getElementById('modalEditarAcampante'));
        const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));
        const loaderOverlay = document.getElementById('loader-overlay');
        const precioTotalAcampante = parseFloat(<?= json_encode($precio_total_acampante) ?>);
        const cuerpoTabla = document.getElementById('cuerpo-tabla-acampantes');
        const filtroIglesia = document.getElementById('filtro-iglesia');
        const filtroClub = document.getElementById('filtro-club');
        const modalMessage = document.getElementById('modal-message');

        let loaderTimeout;
        const tiempoMinimo = 1500;

        function showLoader() {
            loaderOverlay.style.display = 'flex';
            loaderTimeout = Date.now();
        }

        function hideLoader(callback) {
            const timeElapsed = Date.now() - loaderTimeout;
            const delay = Math.max(0, tiempoMinimo - timeElapsed);

            setTimeout(() => {
                loaderOverlay.style.display = 'none';
                if (callback) {
                    callback();
                }
            }, delay);
        }

        function showModalAndHighlight(message, fieldToFocus) {
            modalMessage.innerHTML = message;
            myModal.show();
            if (fieldToFocus) {
                fieldToFocus.focus();
                fieldToFocus.classList.add('is-invalid');
            }
        }

        function cargarAcampantes(iglesiaId = '', clubId = '') {
            cuerpoTabla.innerHTML = `
                <tr>
                    <td colspan="10" class="text-center">
                        <div class="d-flex justify-content-center align-items-center my-3">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Cargando...</span>
                            </div>
                            <p class="mb-0 ms-2">Cargando acampantes...</p>
                        </div>
                    </td>
                </tr>
            `;

            const inicioCarga = Date.now();

            let url = 'obtener_acampantes_filtrados.php';
            const params = new URLSearchParams();
            if (iglesiaId) {
                params.append('departamento_id', iglesiaId);
            }
            if (clubId) {
                params.append('club_id', clubId);
            }

            if (params.toString()) {
                url += '?' + params.toString();
            }

            fetch(url)
                .then(res => {
                    if (!res.ok) {
                        throw new Error('La respuesta de la red no fue correcta');
                    }
                    return res.json();
                })
                .then(data => {
                    const tiempoTranscurrido = Date.now() - inicioCarga;
                    const retraso = Math.max(0, tiempoMinimo - tiempoTranscurrido);

                    setTimeout(() => {
                        cuerpoTabla.innerHTML = '';
                        if (data.success && data.acampantes.length > 0) {
                            data.acampantes.forEach(acampante => {
                                const montoAbonado = parseFloat(acampante.monto_abonado);
                                const montoRestante = precioTotalAcampante - montoAbonado;
                                const pagado = (montoAbonado >= precioTotalAcampante);
                                const textoAprobado = acampante.inscrito == '1' ? 'Aprobado' : 'Pendiente';
                                const claseBadge = acampante.inscrito == '1' ? 'bg-success' : 'bg-warning text-dark';
                                const pagadoHtml = pagado ?
                                    '<i class="fas fa-check-circle text-success" title="Pago Total"></i>' :
                                    '<i class="fas fa-times-circle text-danger" title="Pago Incompleto"></i>';

                                const rowHtml = `
                                    <tr data-id="${acampante.id}">
                                        <td id="cedula-${acampante.id}">${acampante.cedula}</td>
                                        <td id="nombre-${acampante.id}">${acampante.nombre}</td>
                                        <td id="tipo-acampante-${acampante.id}">${acampante.tipo_acampante || 'N/A'}</td>
                                        <td id="iglesia-${acampante.id}">${acampante.iglesia || ''}</td>
                                        <td id="club-${acampante.id}">${acampante.club || 'N/A'}</td>
                                        <td id="monto-abonado-${acampante.id}">${montoAbonado.toFixed(2)}</td>
                                        <td id="monto-restante-${acampante.id}">${montoRestante.toFixed(2)}</td>
                                        <td id="pagado-total-${acampante.id}">${pagadoHtml}</td>
                                        <td id="aprobado-${acampante.id}"><span class="badge ${claseBadge}">${textoAprobado}</span></td>
                                        <td>
                                            <button class="btn btn-sm btn-primary btn-editar-acampante" data-id="${acampante.id}">Editar</button>
                                        </td>
                                    </tr>
                                    `;
                                cuerpoTabla.insertAdjacentHTML('beforeend', rowHtml);
                            });
                        } else {
                            cuerpoTabla.innerHTML = '<tr><td colspan="10" class="text-center">No se encontraron acampantes.</td></tr>';
                        }
                        attachEditListeners();
                    }, retraso);
                })
                .catch(err => {
                    const tiempoTranscurrido = Date.now() - inicioCarga;
                    const retraso = Math.max(0, tiempoMinimo - tiempoTranscurrido);
                    
                    setTimeout(() => {
                        console.error('Error al cargar los acampantes filtrados:', err);
                        cuerpoTabla.innerHTML = '<tr><td colspan="10" class="text-center text-danger">Error al cargar los datos.</td></tr>';
                    }, retraso);
                });
        }

        function attachEditListeners() {
            document.querySelectorAll('.btn-editar-acampante').forEach(btn => {
                btn.addEventListener('click', function () {
                    const acampanteId = this.dataset.id;
                    
                    showLoader();

                    fetch(`cargar_modal_acampante.php?id=${acampanteId}`)
                        .then(res => res.text())
                        .then(html => {
                            hideLoader(() => {
                                document.getElementById('modalEditarContenido').innerHTML = html;
                                const form = document.querySelector('.form-editar-acampante');
                                if (form) {
                                    setupFormListeners(form);
                                }
                                modalEditar.show();
                            });
                        })
                        .catch(err => {
                            hideLoader(() => {
                                console.error('Error al cargar el modal:', err);
                                document.getElementById('modalEditarContenido').innerHTML = '<div class="alert alert-danger">No se pudo cargar la información del acampante.</div>';
                                modalEditar.show();
                            });
                        });
                });
            });
        }

        let fotoCapturada = null;

        function setupFormListeners(form) {
            const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
            const edadInput = document.getElementById('edad');
            const regionSelect = document.getElementById('region');
            const tiendaSelect = document.getElementById('tienda');
            const departamentoSelect = document.getElementById('departamento');
            const clubSelect = document.getElementById('club');

            const inputFile = document.getElementById('foto');
            const nombreArchivoSpan = document.getElementById('nombre-archivo');

            // --- Lógica de rotación con la biblioteca Load-Image ---
            if (inputFile && nombreArchivoSpan) {
                inputFile.addEventListener('change', function(e) {
                    const file = e.target.files[0];
                    if (file) {
                        nombreArchivoSpan.textContent = file.name;
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
                                orientation: true, // Lee el EXIF y corrige la orientación
                                canvas: true      // Devuelve un canvas con la imagen corregida
                            }
                        );
                    } else {
                        nombreArchivoSpan.textContent = 'No se ha seleccionado ningún archivo.';
                        fotoCapturada = null;
                    }
                });
            }
            // --- Fin de la lógica con Load-Image ---

            function calcularEdad() {
                const fechaNacimiento = new Date(fechaNacimientoInput.value);
                if (fechaNacimiento instanceof Date && !isNaN(fechaNacimiento)) {
                    const hoy = new Date();
                    const edadMilisegundos = hoy - fechaNacimiento;
                    const edadAnios = Math.floor(edadMilisegundos / (1000 * 60 * 60 * 24 * 365.25));
                    edadInput.value = edadAnios;
                } else {
                    edadInput.value = '';
                }
            }

            if (fechaNacimientoInput) {
                calcularEdad();
                fechaNacimientoInput.addEventListener('change', calcularEdad);
            }

            function cargarOpciones(tipo, parentId, selectElement, selectedId) {
                selectElement.innerHTML = '<option value="">----Seleccione----</option>';
                selectElement.disabled = true;
                if (!parentId) {
                    return;
                }
                fetch(`obtener_opciones.php?tipo=${tipo}&parent_id=${parentId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            let optionsHtml = '<option value="">----Seleccione----</option>';
                            data.data.forEach(item => {
                                const isSelected = item.id == selectedId ? 'selected' : '';
                                optionsHtml += `<option value="${item.id}" ${isSelected}>${item.nombre}</option>`;
                            });
                            selectElement.innerHTML = optionsHtml;
                            selectElement.disabled = false;
                        }
                    });
            }

            if (regionSelect && tiendaSelect && departamentoSelect && clubSelect) {
                regionSelect.addEventListener('change', function() {
                    const regionId = this.value;
                    cargarOpciones('tiendas', regionId, tiendaSelect, '');
                    departamentoSelect.innerHTML = '<option value="">----Seleccione----</option>';
                    departamentoSelect.disabled = true;
                });
                tiendaSelect.addEventListener('change', function() {
                    const tiendaId = this.value;
                    cargarOpciones('departamentos', tiendaId, departamentoSelect, '');
                });

                departamentoSelect.addEventListener('change', function() {
                    const departamentoId = this.value;
                    cargarOpciones('clubes', departamentoId, clubSelect, '');
                });

                const selectedRegionId = regionSelect.dataset.selected;
                const selectedTiendaId = tiendaSelect.dataset.selected;
                const selectedDepartamentoId = departamentoSelect.dataset.selected;
                const selectedClubId = clubSelect.dataset.selected;

                if (selectedRegionId) {
                    cargarOpciones('tiendas', selectedRegionId, tiendaSelect, selectedTiendaId);
                }
                if (selectedTiendaId) {
                    cargarOpciones('departamentos', selectedTiendaId, departamentoSelect, selectedDepartamentoId);
                }
                if (selectedDepartamentoId) {
                    cargarOpciones('clubes', selectedDepartamentoId, clubSelect, selectedClubId);
                }
            }

            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const requiredFields = form.querySelectorAll('[data-required="true"]');
                let isValid = true;
                requiredFields.forEach(field => {
                    if (!field.value) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    showModalAndHighlight('❌ Por favor, complete todos los campos obligatorios.');
                    return;
                }

                const formId = form.dataset.id;
                const formData = new FormData(this);

                const edad = parseInt(document.getElementById('edad').value);
                const cedulaInput = document.getElementById('cedula');

                if (edad >= 10) {
                    if (!cedulaInput.value.trim()) {
                        showModalAndHighlight('Para mayores de 10 años, el campo "Cédula" es obligatorio.', cedulaInput);
                        return;
                    }
                    if (!cedulaInput.value.trim().match(/^\d+$/)) {
                        showModalAndHighlight('La cédula debe contener solo números.', cedulaInput);
                        return;
                    }
                }

                const submitBtn = form.querySelector('button[type="submit"]');
                submitBtn.disabled = true;
                
                showLoader();

                fetch(`verificar_cedula.php?cedula=${cedulaInput.value.trim()}&id=${form.dataset.id}`)
                    .then(response => response.json())
                    .then(result => {
                        if (result.existe && edad >= 10) {
                            hideLoader(() => {
                                cedulaInput.classList.add('is-invalid');
                                submitBtn.disabled = false;
                                showModalAndHighlight('Esta cédula ya está registrada en otro acampante.', cedulaInput);
                            });
                        } else {
                            const id = this.dataset.id;

                            // Agrega el ID del acampante al FormData
                            formData.append('id', formId);

                            if (fotoCapturada) {
                                formData.delete('foto');
                                formData.append('foto', fotoCapturada, fotoCapturada.name);
                            }

                            showLoader();
                            
                            fetch('actualizar_acampante.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(res => res.json())
                            .then(data => {
                                hideLoader(() => {
                                    const mensaje = document.getElementById('notificacionMensaje');
                                    if (data.success) {
                                        modalEditar.hide();
                                        mensaje.innerHTML = `<div class="text-success fw-bold">✅ Acampante actualizado correctamente.</div>`;
                                        modalNotif.show();
                                        cargarAcampantes(filtroIglesia.value, filtroClub.value);
                                    } else {
                                        mensaje.innerHTML = `<div class="text-danger fw-bold">❌ ${data.message}</div>`;
                                        modalNotif.show();
                                    }
                                });
                            })
                            .catch(err => {
                                hideLoader(() => {
                                    console.error('Error al enviar los datos:', err);
                                    const mensaje = document.getElementById('notificacionMensaje');
                                    mensaje.innerHTML = `<div class="text-danger fw-bold">❌ Error al enviar los datos.</div>`;
                                    modalNotif.show();
                                });
                            });
                        }
                    })
                    .catch(error => {
                        hideLoader(() => {
                            submitBtn.disabled = false;
                            showModalAndHighlight('Error al verificar la cédula. Inténtelo de nuevo.', null);
                            console.error('Verificar cédula error:', error);
                        });
                    });
            });
        }

        filtroIglesia.addEventListener('change', () => cargarAcampantes(filtroIglesia.value, filtroClub.value));
        filtroClub.addEventListener('change', () => cargarAcampantes(filtroIglesia.value, filtroClub.value));

        attachEditListeners();
    });
</script>
</body>
</html>