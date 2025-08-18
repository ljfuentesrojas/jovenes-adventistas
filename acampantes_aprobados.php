<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

$query_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY id DESC LIMIT 1";
$res_cuota = $conexion->query($query_cuota);

if ($res_cuota && $res_cuota->num_rows > 0) {
    $row_cuota = $res_cuota->fetch_assoc();
    $precio_total_acampante = (float)$row_cuota['valor_cuota'];
} else {
    die("Error: No se ha definido el valor de la cuota en la tabla 'cuota_acampante'.");
}

// Obtener la lista de iglesias y clubes para los filtros
$res_iglesias = $conexion->query("SELECT nombre FROM departamentos GROUP BY nombre ORDER BY nombre");
$iglesias = [];
while ($row = $res_iglesias->fetch_assoc()) {
    $iglesias[] = $row['nombre'];
}

$res_clubes = $conexion->query("SELECT nombre_club FROM clubes GROUP BY nombre_club ORDER BY nombre_club");
$clubes = [];
while ($row = $res_clubes->fetch_assoc()) {
    $clubes[] = $row['nombre_club'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acampantes Aprobados</title>
    <?php include 'links-recursos.php'; ?>

    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.7/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/datatables.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/blueimp-load-image@5.16.0/js/load-image.all.min.js"></script>

    <style>
        .dt-buttons {
            margin-bottom: 10px;
        }
        
        #tabla-acampantes-aprobados thead th,
        #tabla-acampantes-aprobados tbody td {
            font-size: 0.8rem;
        }

        #tabla-acampantes-aprobados thead th input,
        #tabla-acampantes-aprobados thead th select {
            font-size: 0.7rem;
            padding: 0.2rem;
            box-sizing: border-box;
            width: 100%;
        }
        #tabla-acampantes-aprobados thead th.no-filter input,
        #tabla-acampantes-aprobados thead th.no-filter select {
            display: none;
        }
        #tabla-acampantes-aprobados tbody td {
            padding: 0.5rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        #tabla-acampantes-aprobados tbody .btn {
            min-width: 90px;
            margin-bottom: 0.2rem;
            display: inline-block;
            box-sizing: border-box;
            text-align: center;
        }
        
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

        @media (max-width: 768px) {
            .dt-buttons {
                text-align: center;
            }
            .dataTables_filter {
                width: 100%;
            }
            .dataTables_filter label {
                width: 100%;
            }
            .dataTables_filter input {
                width: 100%;
                box-sizing: border-box;
            }
            #tabla-acampantes-aprobados tbody td {
                white-space: normal;
            }
            #tabla-acampantes-aprobados tbody .btn {
                width: 100%;
            }
        }
    </style>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Acampantes Inscritos y Aprobados</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-acampantes-aprobados" style="width:100%">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Tipo de Acampante</th>
                    <th>Club</th>
                    <th>Iglesia</th>
                    <th>Monto Abonado ($)</th>
                    <th>Registrado por</th>
                    <th class="no-filter">Acción</th>
                </tr>
                <tr>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Cédula" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Nombre" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Tipo" /></th>
                    <th>
                        <select class="form-select">
                            <option value="">Clubes</option>
                            <?php foreach ($clubes as $club): ?>
                                <option value="<?= htmlspecialchars($club) ?>"><?= htmlspecialchars($club) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </th>
                    <th>
                        <select class="form-select">
                            <option value="">Iglesias</option>
                            <?php foreach ($iglesias as $iglesia): ?>
                                <option value="<?= htmlspecialchars($iglesia) ?>"><?= htmlspecialchars($iglesia) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Monto" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar por" /></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $usuario_id = $_SESSION['usuario_id'];
                $rol_usuario = $_SESSION['rol'];
                $es_admin = ($rol_usuario === 'admin');

                // Lógica de la consulta con sentencia preparada
                $sql = "
                    SELECT
                        e.id,
                        e.nombre,
                        e.cedula,
                        e.inscrito,
                        c.nombre AS tipo_acampante,
                        cl.nombre_club AS club,
                        d.nombre AS iglesia,
                        u.nombre AS creador,
                        COALESCE(SUM(
                            CASE
                                WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto
                                ELSE pc.transaccion_monto / pc.tasa_bcv
                            END
                        ), 0) AS monto_abonado
                    FROM empleados e
                    LEFT JOIN cargos c ON e.cargo_id = c.id
                    LEFT JOIN clubes cl ON e.club_id = cl.id
                    JOIN departamentos d ON e.departamento_id = d.id
                    JOIN usuarios u ON e.creado_por = u.id
                    LEFT JOIN pre_contabilidad pc ON e.id = pc.acampante_id
                    LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
                    WHERE e.inscrito = 1 AND rp.aprobar_pago = 1";

                // Agregar la condición si el usuario no es un administrador
                if (!$es_admin) {
                    $sql .= " AND u.id = ?";
                }

                $sql .= " GROUP BY e.id, e.nombre, e.cedula, d.nombre, u.nombre, c.nombre, cl.nombre_club ORDER BY e.id";

                // Preparar la consulta
                $stmt = $conexion->prepare($sql);

                if (!$es_admin) {
                    $stmt->bind_param("i", $usuario_id);
                }

                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()):
                    $monto_abonado = $row['monto_abonado'];
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td><?= htmlspecialchars($row['cedula']) ?></td>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= htmlspecialchars($row['tipo_acampante']) ?></td>
                    <td><?= htmlspecialchars($row['club']) ?></td>
                    <td><?= htmlspecialchars($row['iglesia']) ?></td>
                    <td><?= htmlspecialchars('$' . number_format($monto_abonado, 2)) ?></td>
                    <td><?= htmlspecialchars($row['creador']) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white btn-ver-pagos-aprobados mb-1" data-id="<?= $row['id'] ?>">Ver Pagos</button>
                        <button class="btn btn-sm btn-primary btn-editar-empleado mb-1" data-id="<?= $row['id'] ?>">Editar</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalVerPagosAprobados" tabindex="-1" aria-labelledby="modalVerPagosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" id="modalPagosAprobadosContenido">
        </div>
    </div>
</div>

<div class="modal fade" id="modalEditarEmpleado" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="notificacionMensaje"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
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

<div class="loader-overlay" id="loader-overlay" style="z-index: 2000;">
    <div class="spinner-border loader-spinner" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.7/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    $(document).ready(function() {
        let loaderTimeout;
        const MIN_LOADER_TIME = 1500;

        function showLoader() {
            $('#loader-overlay').css('display', 'flex');
            loaderTimeout = Date.now();
        }

        function hideLoader(callback) {
            const timeElapsed = Date.now() - loaderTimeout;
            const delay = Math.max(0, MIN_LOADER_TIME - timeElapsed);
            
            setTimeout(() => {
                $('#loader-overlay').css('display', 'none');
                if (callback) {
                    callback();
                }
            }, delay);
        }

        const precioTotalAcampante = parseFloat(<?= json_encode($precio_total_acampante) ?>);
        const tabla = $('#tabla-acampantes-aprobados').DataTable({
            orderCellsTop: true,
            fixedHeader: true,
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: 'Excel',
                    titleAttr: 'Exportar a Excel',
                    className: 'btn btn-success',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6]
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: 'CSV',
                    titleAttr: 'Exportar a CSV',
                    className: 'btn btn-info',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6]
                    }
                },
                {
                    extend: 'print',
                    text: 'Imprimir',
                    titleAttr: 'Imprimir tabla',
                    className: 'btn btn-secondary',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6]
                    }
                }
            ],
            language: {
                url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
            },
            autoWidth: false,
            columnDefs: [
                { targets: 0, width: '80px' },
                { targets: 1, width: '120px' },
                { targets: 2, width: '120px' },
                { targets: 3, width: '120px' },
                { targets: 4, width: '120px' },
                { targets: 5, width: '120px' },
                { targets: 6, width: '120px' },
                { targets: 7, width: '200px' }
            ]
        });

        $('#tabla-acampantes-aprobados thead tr:eq(1) th').each(function(i) {
            const select = $('select', this);
            const input = $('input', this);

            if (select.length > 0) {
                select.on('change', function() {
                    tabla.column(i).search(this.value).draw();
                });
            } else if (input.length > 0) {
                input.on('keyup change', function() {
                    tabla.column(i).search(this.value).draw();
                });
            }
        });

        const modalVerPagosAprobados = new bootstrap.Modal(document.getElementById('modalVerPagosAprobados'));
        const modalEditarEmpleado = new bootstrap.Modal(document.getElementById('modalEditarEmpleado'));
        const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));

        $('#tabla-acampantes-aprobados tbody').on('click', '.btn-ver-pagos-aprobados', function() {
            const acampanteId = $(this).attr('data-id');
            showLoader();
            $('#modalPagosAprobadosContenido').load('get_pagos_acampante_aprobados.php?id=' + acampanteId, function() {
                hideLoader(() => {
                    modalVerPagosAprobados.show();
                });
            });
        });
        
        let fotoCapturada = null;

        $('#tabla-acampantes-aprobados tbody').on('click', '.btn-editar-empleado', function() {
            const empleadoId = $(this).attr('data-id');
            showLoader();
            $('#modalEditarContenido').load('cargar_modal_acampante.php?id=' + empleadoId, function() {
                hideLoader(() => {
                    modalEditarEmpleado.show();

                    const form = document.getElementById('form-editar-acampante');
                    const myModal = new bootstrap.Modal(document.getElementById('validationModal'));
                    const modalMessage = document.getElementById('modal-message');
                    const fechaNacimientoInput = document.getElementById('fecha_nacimiento');
                    const edadInput = document.getElementById('edad');
                    const regionSelect = document.getElementById('region');
                    const tiendaSelect = document.getElementById('tienda');
                    const departamentoSelect = document.getElementById('departamento');
                    const clubSelect = document.getElementById('club');

                    const inputFile = document.getElementById('foto');
                    const nombreArchivoSpan = document.getElementById('nombre-archivo');

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
                                        orientation: true,
                                        canvas: true
                                    }
                                );
                            } else {
                                nombreArchivoSpan.textContent = 'No se ha seleccionado ningún archivo.';
                                fotoCapturada = null;
                            }
                        });
                    }
                    
                    function showModalAndHighlight(message, fieldToFocus) {
                        modalMessage.innerHTML = message;
                        myModal.show();
                        if (fieldToFocus) {
                            $(fieldToFocus).focus().addClass('is-invalid');
                        }
                    }

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
                                        return;
                                    });
                                } else {
                                    const id = form.dataset.id;
                                    const formData = new FormData(form);
                                    
                                    if (fotoCapturada) {
                                        formData.delete('foto');
                                        formData.append('foto', fotoCapturada, fotoCapturada.name);
                                    }

                                    // Esta línea asegura que el ID del acampante se envíe correctamente
                                    formData.append('id', id);

                                    showLoader();

                                    $.ajax({
                                        url: 'actualizar_acampante.php',
                                        method: 'POST',
                                        data: formData,
                                        processData: false,
                                        contentType: false,
                                        dataType: 'json',
                                        success: function(data) {
                                            hideLoader(() => {
                                                const mensaje = $('#notificacionMensaje');
                                                if (data.success) {
                                                    const acampante = data.acampante;
                                                    const rowNode = tabla.row(`[data-id="${id}"]`).node();
                                                    if (rowNode) {
                                                        tabla.cell(rowNode, 0).data(acampante.cedula);
                                                        tabla.cell(rowNode, 1).data(acampante.nombre);
                                                        tabla.cell(rowNode, 2).data(acampante.tipo_acampante);
                                                        tabla.cell(rowNode, 3).data(acampante.club);
                                                        tabla.cell(rowNode, 4).data(acampante.iglesia);
                                                        tabla.cell(rowNode, 6).data(acampante.creador);
                                                        tabla.row(rowNode).invalidate().draw();
                                                    }
                                                    mensaje.html(`<div class="text-success fw-bold">✅ ${data.message}</div>`);
                                                    modalNotif.show();
                                                    modalEditarEmpleado.hide();
                                                } else {
                                                    mensaje.html(`<div class="text-danger fw-bold">❌ ${data.message}</div>`);
                                                    modalNotif.show();
                                                }
                                            });
                                        },
                                        error: function(xhr, status, error) {
                                            hideLoader(() => {
                                                console.error(xhr.responseText);
                                                $('#notificacionMensaje').html(`<div class="text-danger fw-bold">❌ Error al enviar los datos.</div>`);
                                                modalNotif.show();
                                            });
                                        }
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
                });
            });
        });
    });
</script>
</body>
</html>