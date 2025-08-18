<?php
session_start();
include 'conexion.php';

// Verificar si el usuario es admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Consulta para obtener todos los registros de pagos y su estado
$sql_pagos = "
    SELECT
        rp.id,
        rp.transaccion_codigo,
        rp.transaccion_fecha,
        rp.aprobar_pago,
        rp.creado_por,
        rp.comprobante as ruta_imagen_pago,  /* Añadido: columna para la ruta de la imagen */
        rp.observacion as observacion_pago,  /* Añadido: columna para la observacion */
        pc.tasa_bcv,
        u.nombre AS registrado_por,
        mp.metodo_p AS metodo_pago,
        mp.id AS metodo_pago_id,
        rp.transaccion_monto AS monto_total,
        COUNT(pc.acampante_id) AS numero_acampantes
    FROM registro_pagos rp
    LEFT JOIN pre_contabilidad pc ON rp.id = pc.id_registro_pago
    LEFT JOIN usuarios u ON rp.creado_por = u.id
    LEFT JOIN metodo_pago mp ON rp.metodo_pago = mp.id
    GROUP BY rp.id
    ORDER BY rp.transaccion_fecha DESC
";

$res_pagos = $conexion->query($sql_pagos);
if (!$res_pagos) {
    die("Error en la consulta: " . $conexion->error);
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-Contabilidad</title>
    <?php include 'links-recursos.php'; ?>
    
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.7/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/datatables.min.css"/>
    <style>
        .dt-buttons {
            margin-bottom: 10px;
        }

        /* Estilos para los filtros por columna */
        #tabla-pagos thead th input, 
        #tabla-pagos thead th select {
            font-size: 0.8rem;
            padding: 2px;
            width: 100%;
            box-sizing: border-box;
        }
        
        /* Ocultar el filtro para la columna de acciones */
        #tabla-pagos thead th.no-filter input, 
        #tabla-pagos thead th.no-filter select {
            display: none;
        }
        
        /* Reducir el tamaño de la fuente en toda la tabla */
        #tabla-pagos th, 
        #tabla-pagos td {
            font-size: 0.85rem;
            padding: 0.5rem;
        }

        /* Ajuste de estilo para que la tabla y columnas se ajusten al contenido */
        #tabla-pagos {
            width: auto !important;
        }
        #tabla-pagos td {
            white-space: nowrap; /* Evita que el texto se rompa */
        }
        
        /* Estilos para el modal */
        .modal-body .table th,
        .modal-body .table td {
            font-size: 0.8rem;
            padding: 0.4rem;
        }

        /* Estilos para el overlay de carga usando las variables de backdrop de Bootstrap */
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
        
        /* Estilos para el spinner de Bootstrap */
        .loader-spinner {
            color: #fff; /* Color blanco para el spinner sobre el fondo oscuro */
            width: 4rem;
            height: 4rem;
        }

        /* Estilos para hacer la tabla más responsive en móviles */
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
        }
    </style>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Registro de Pagos</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-pagos" style="width:100%">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cód. Transacción</th>
                    <th>Fecha</th>
                    <th>Método de Pago</th>
                    <th>Monto Total</th>
                    <th>Tasa BCV</th>
                    <th>Registrado por</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
                <tr>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Cód." /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Fecha" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Método" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Monto" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar Tasa" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar por" /></th>
                    <th class="filter-select">
                        <select>
                            <option value="">Estado...</option>
                            <option value="Aprobado">Aprobado</option>
                            <option value="Pendiente">Pendiente</option>
                        </select>
                    </th>
                    <th class="no-filter"></th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $res_pagos->fetch_assoc()): ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td><?= htmlspecialchars($row['transaccion_codigo']) ?></td>
                    <td><?= htmlspecialchars($row['transaccion_fecha']) ?></td>
                    <td><?= htmlspecialchars($row['metodo_pago']) ?></td>
                    <td>
                        <?php 
                        $monto_a_mostrar = $row['monto_total'];
                        $simbolo = 'Bs';
                        
                        if ($row['metodo_pago_id'] == 4) {
                            $simbolo = '$';
                        }
                        
                        echo htmlspecialchars($simbolo . ' ' . number_format($monto_a_mostrar, 2));
                        ?>
                    </td>
                    <td><?= htmlspecialchars($row['tasa_bcv']) ?></td>
                    <td><?= htmlspecialchars($row['registrado_por']) ?></td>
                    <td>
                        <?php if ($row['aprobar_pago'] == 1): ?>
                            <span class="badge bg-success">Aprobado</span>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white btn-ver-acampantes" style="min-width: 90px;" data-id="<?= $row['id'] ?>">Ver Acampantes</button>
                        
                        <?php if ($row['metodo_pago_id'] == 1 || $row['metodo_pago_id'] == 2): ?>
                            <?php if (!empty($row['ruta_imagen_pago'])): ?>
                                <button class="btn btn-sm btn-primary btn-ver-comprobante" data-bs-toggle="modal" data-bs-target="#modalVerComprobante" data-imagen="<?= htmlspecialchars($row['ruta_imagen_pago']) ?>">
                                    Ver Comprobante
                                </button>
                            <?php endif; ?>
                        <?php elseif ($row['metodo_pago_id'] == 3 || $row['metodo_pago_id'] == 4): ?>
                            <?php if (!empty($row['observacion_pago'])): ?>
                                <button class="btn btn-sm btn-secondary btn-ver-observacion" data-bs-toggle="modal" data-bs-target="#modalVerObservacion" data-observacion="<?= htmlspecialchars($row['observacion_pago']) ?>">
                                    Ver Observación
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalVerAcampantes" tabindex="-1" aria-labelledby="modalVerAcampantesLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVerAcampantesLabel">Detalles del Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <h6 id="pago-monto-original" class="fw-bold">Monto Original:</h6>
                        <h6 id="pago-monto-aplicado">Monto Aplicado:</h6>
                        <h6 id="pago-saldo-favor" class="text-success">Saldo a Favor:</h6>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 id="pago-codigo">Cód. Transacción:</h6>
                        <h6 id="pago-fecha">Fecha:</h6>
                        <h6 id="pago-estado">Estado:</h6>
                    </div>
                </div>
                <hr>
                <h6>Acampantes Asociados a este Pago:</h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Cédula</th>
                                <th>Nombre</th>
                                <th>Ubicación y Cargo</th>
                                <th>Monto Aplicado</th>
                                <th>Monto Aplicado ($)</th>
                                <th>Tasa BCV</th>
                            </tr>
                        </thead>
                        <tbody id="acampantes-lista">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button id="btn-aprobar-pago" class="btn btn-success d-none">Aprobar Pago</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVerComprobante" tabindex="-1" aria-labelledby="modalVerComprobanteLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVerComprobanteLabel">Comprobante de Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagen-comprobante" src="" class="img-fluid" alt="Comprobante de Pago">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalVerObservacion" tabindex="-1" aria-labelledby="modalVerObservacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVerObservacionLabel">Observación del Pago</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p id="observacion-texto" class="fs-6"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
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

<div class="loader-overlay" id="loader-overlay" style="z-index: 2000;">
    <div class="spinner-border loader-spinner" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/pdfmake.min.js"></script>
<script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.36/vfs_fonts.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/jszip-2.5.0/dt-1.13.7/b-2.4.2/b-html5-2.4.2/b-print-2.4.2/datatables.min.js"></script>

<script>
$(document).ready(function() {
    let loaderTimeout;
    const MIN_LOADER_TIME = 2000; // 2 segundos

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

    const tabla = $('#tabla-pagos').DataTable({
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
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'csvHtml5',
                text: 'CSV',
                titleAttr: 'Exportar a CSV',
                className: 'btn btn-info',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            },
            {
                extend: 'print',
                text: 'Imprimir',
                titleAttr: 'Imprimir tabla',
                className: 'btn btn-secondary',
                exportOptions: {
                    columns: ':not(:last-child)'
                }
            }
        ],
        language: {
            url: "//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json"
        },
        autoWidth: true
    });

    $('#tabla-pagos thead tr:eq(1) th').each(function(i) {
        if ($(this).hasClass('filter-input')) {
            $('input', this).on('keyup change', function() {
                if (tabla.column(i).search() !== this.value) {
                    tabla.column(i).search(this.value).draw();
                }
            });
        }
        if ($(this).hasClass('filter-select')) {
            $('select', this).on('change', function() {
                let val = $.fn.dataTable.util.escapeRegex($(this).val());
                tabla.column(i).search(val ? '^' + val + '$' : '', true, false).draw();
            });
        }
    });

    const modalVerAcampantes = new bootstrap.Modal(document.getElementById('modalVerAcampantes'));
    const acampantesLista = document.getElementById('acampantes-lista');
    const pagoMontoOriginal = document.getElementById('pago-monto-original');
    const pagoMontoAplicado = document.getElementById('pago-monto-aplicado');
    const pagoSaldoFavor = document.getElementById('pago-saldo-favor');
    const pagoCodigo = document.getElementById('pago-codigo');
    const pagoFecha = document.getElementById('pago-fecha');
    const pagoEstado = document.getElementById('pago-estado');
    const btnAprobarPago = document.getElementById('btn-aprobar-pago');
    const notificacionModal = new bootstrap.Modal(document.getElementById('notificacionModal'));
    const notificacionMensaje = document.getElementById('notificacionMensaje');
    const modalVerComprobante = new bootstrap.Modal(document.getElementById('modalVerComprobante'));
    const imagenComprobante = document.getElementById('imagen-comprobante');
    const modalVerObservacion = new bootstrap.Modal(document.getElementById('modalVerObservacion'));
    const observacionTexto = document.getElementById('observacion-texto');

    $('#tabla-pagos tbody').on('click', '.btn-ver-acampantes', function(e) {
        const registroPagoId = $(this).attr('data-id');
        
        // Limpiar y mostrar estados de carga
        pagoMontoOriginal.textContent = 'Monto Original: Cargando...';
        pagoMontoAplicado.textContent = 'Monto Aplicado: Cargando...';
        pagoSaldoFavor.textContent = 'Saldo a Favor: Cargando...';
        pagoCodigo.textContent = 'Cód. Transacción: Cargando...';
        pagoFecha.textContent = 'Fecha: Cargando...';
        pagoEstado.textContent = 'Estado: Cargando...';
        acampantesLista.innerHTML = '<tr><td colspan="5" class="text-center">Cargando acampantes...</td></tr>';
        btnAprobarPago.classList.add('d-none');
        
        showLoader();

        fetch('get_acampantes_por_pago.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `registro_pago_id=${registroPagoId}`
        })
        .then(response => response.json())
        .then(data => {
            hideLoader(() => {
                if (data.success) {
                    pagoMontoOriginal.textContent = `Monto Original: ${data.pago_info.monto_display}`;
                    pagoMontoAplicado.textContent = `Monto Aplicado: ${data.pago_info.total_aplicado_display}`;
                    pagoSaldoFavor.textContent = `Saldo a Favor: ${data.pago_info.saldo_a_favor_display}`;
                    pagoCodigo.textContent = `Cód. Transacción: ${data.pago_info.transaccion_codigo}`;
                    pagoFecha.textContent = `Fecha: ${data.pago_info.transaccion_fecha}`;

                    if (data.pago_info.aprobar_pago == 0) {
                        pagoEstado.textContent = 'Estado: Pendiente';
                        btnAprobarPago.classList.remove('d-none');
                        btnAprobarPago.setAttribute('data-id', registroPagoId);
                    } else {
                        pagoEstado.textContent = 'Estado: Aprobado';
                        btnAprobarPago.classList.add('d-none');
                    }

                    acampantesLista.innerHTML = '';
                    if (data.acampantes.length > 0) {
                        const tableHeader = acampantesLista.closest('table').querySelector('thead tr');
                        tableHeader.innerHTML = `
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Ubicación y Cargo</th>
                            <th>Monto Aplicado</th>
                            <th>Monto Aplicado ($)</th>
                            <th>Tasa BCV</th>
                        `;

                        data.acampantes.forEach(acampante => {
                            const row = `
                                <tr>
                                    <td>${acampante.cedula}</td>
                                    <td>${acampante.nombre}</td>
                                    <td>${acampante.ubicacion_y_cargo}</td>
                                    <td>${acampante.monto_display}</td>
                                    <td>${acampante.monto_usd_display}</td>
                                    <td>${acampante.tasa_bcv}</td>
                                </tr>
                            `;
                            acampantesLista.innerHTML += row;
                        });
                    } else {
                        const tableHeader = acampantesLista.closest('table').querySelector('thead tr');
                        tableHeader.innerHTML = `
                            <th>Cédula</th>
                            <th>Nombre</th>
                            <th>Ubicación y Cargo</th>
                            <th>Monto Aplicado</th>
                        `;
                        acampantesLista.innerHTML = '<tr><td colspan="4" class="text-center">No hay acampantes asociados a este pago.</td></tr>';
                    }
                } else {
                    acampantesLista.innerHTML = `<tr><td colspan="5" class="text-center text-danger">${data.message}</td></tr>`;
                }

                modalVerAcampantes.show();
            });
        })
        .catch(error => {
            console.error('Error al cargar acampantes:', error);
            hideLoader(() => {
                acampantesLista.innerHTML = `<tr><td colspan="5" class="text-center text-danger">Error al cargar la información.</td></tr>`;
                modalVerAcampantes.show();
            });
        });
    });

    // Evento para el botón de Aprobar Pago
    btnAprobarPago.addEventListener('click', function() {
        const registroPagoId = this.getAttribute('data-id');
        
        showLoader();

        fetch('aprobar_pago_simple.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `registro_pago_id=${registroPagoId}`
        })
        .then(res => res.json())
        .then(data => {
            hideLoader(() => {
                modalVerAcampantes.hide();
                if (data.success) {
                    const fila = $(`#tabla-pagos tr[data-id="${registroPagoId}"]`);
                    
                    if (fila.length) {
                        const estadoCell = fila.find('td:eq(6)');
                        const nuevaClase = 'badge bg-success';
                        const nuevoTexto = 'Aprobado';
                        
                        estadoCell.html(`<span class="${nuevaClase}">${nuevoTexto}</span>`);
                        
                        tabla.row(fila).data()[5] = nuevoTexto;
                        tabla.row(fila).invalidate().draw();
                    }
                    
                    notificacionMensaje.innerHTML = `<div class="text-success fw-bold">✅ ${data.message}</div>`;
                } else {
                    notificacionMensaje.innerHTML = `<div class="text-danger fw-bold">❌ ${data.message}</div>`;
                }
                notificacionModal.show();
            });
        })
        .catch(err => {
            console.error(err);
            hideLoader(() => {
                modalVerAcampantes.hide();
                notificacionMensaje.innerHTML = `<div class="text-danger fw-bold">❌ Error al aprobar el pago.</div>`;
                notificacionModal.show();
            });
        });
    });

    // Nuevo evento para los botones de Ver Comprobante
    $('#tabla-pagos tbody').on('click', '.btn-ver-comprobante', function(e) {
        const rutaImagen = $(this).data('imagen');
        imagenComprobante.src = `/comprobantes/${rutaImagen}`; // Asegúrate de cambiar esta ruta
    });

    // Nuevo evento para los botones de Ver Observación
    $('#tabla-pagos tbody').on('click', '.btn-ver-observacion', function(e) {
        const observacion = $(this).data('observacion');
        observacionTexto.textContent = observacion;
    });
});
</script>
</body>
</html>