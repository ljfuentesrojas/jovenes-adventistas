<?php
session_start();
include 'conexion.php';

// Verificar si el usuario es admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header("Location: index.php");
    exit;
}

// Obtener el valor de la cuota del acampante desde la base de datos
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
    <title>Aprobar Acampantes</title>
    <?php include 'links-recursos.php'; ?>
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/v/bs5/dt-1.13.7/datatables.min.css"/>
    <style>
        /* Regla para reducir el tamaño del texto de las columnas */
        #tabla-acampantes thead th,
        #tabla-acampantes tbody td {
            font-size: 0.8rem;
        }

        #tabla-acampantes thead th input,
        #tabla-acampantes thead th select {
            font-size: 0.7rem;
            padding: 0.2rem;
            box-sizing: border-box;
            width: 100%;
        }

        #tabla-acampantes thead th.no-filter input,
        #tabla-acampantes thead th.no-filter select {
            display: none;
        }
        
        #tabla-acampantes tbody .btn {
            min-width: 90px;
        }

        /* Estilos para el overlay de carga */
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
    <h2 class="text-center mb-4">Acampantes Inscritos y Pendientes de Aprobación</h2>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-acampantes">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Tipo de Acampante</th>
                    <th>Club</th>
                    <th>Iglesia</th>
                    <th>Monto Abonado ($)</th>
                    <th>Monto Restante ($)</th>
                    <th>Registrado por</th>
                    <th>Acción</th>
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
                    <th class="filter-input"><input type="text" placeholder="Filtrar Monto" /></th>
                    <th class="filter-input"><input type="text" placeholder="Filtrar por" /></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php
// ... Tu código inicial de conexión y verificación de sesión ...

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
    left JOIN departamentos d ON e.departamento_id = d.id
    left JOIN usuarios u ON e.creado_por = u.id
    LEFT JOIN pre_contabilidad pc ON e.id = pc.acampante_id
    LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
    WHERE e.inscrito = 0";

// Agregar la condición si el usuario no es un administrador
if (!$es_admin) {
    $sql .= " AND u.id = ?";
}

$sql .= " GROUP BY e.id, e.nombre, e.cedula, d.nombre, u.nombre, c.nombre, cl.nombre_club";

// Preparar la consulta
$stmt = $conexion->prepare($sql);

if (!$es_admin) {
    $stmt->bind_param("i", $usuario_id);
}

$stmt->execute();
$res = $stmt->get_result();



                // $res = $conexion->query("
                //     SELECT
                //         e.id,
                //         e.nombre,
                //         e.cedula,
                //         e.inscrito,
                //         c.nombre AS tipo_acampante,
                //         cl.nombre_club AS club,
                //         d.nombre AS iglesia,
                //         u.nombre AS creador,
                //         COALESCE(SUM(
                //             CASE
                //                 WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto
                //                 ELSE pc.transaccion_monto / pc.tasa_bcv
                //             END
                //         ), 0) AS monto_abonado
                //     FROM empleados e
                //     LEFT JOIN cargos c ON e.cargo_id = c.id
                //     LEFT JOIN clubes cl ON e.club_id = cl.id
                //     JOIN departamentos d ON e.departamento_id = d.id
                //     JOIN usuarios u ON e.creado_por = u.id
                //     LEFT JOIN pre_contabilidad pc ON e.id = pc.acampante_id
                //     LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
                //     WHERE e.inscrito = 0 
                //     GROUP BY e.id, e.nombre, e.cedula, d.nombre, u.nombre, c.nombre, cl.nombre_club
                // ");
                
                while ($row = $res->fetch_assoc()):
                    $monto_abonado = $row['monto_abonado'];
                    $monto_restante = $precio_total_acampante - $monto_abonado;
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td id="cedula-<?= $row['id'] ?>"><?= htmlspecialchars($row['cedula']) ?></td>
                    <td id="nombre-<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></td>
                    <td id="tipo-acampante-<?= $row['id'] ?>"><?= htmlspecialchars($row['tipo_acampante']) ?></td>
                    <td id="club-<?= $row['id'] ?>"><?= htmlspecialchars($row['club']) ?></td>
                    <td id="iglesia-<?= $row['id'] ?>"><?= htmlspecialchars($row['iglesia']) ?></td>
                    <td id="monto-abonado-<?= $row['id'] ?>"><?= htmlspecialchars('$' . number_format($monto_abonado, 2)) ?></td>
                    <td id="monto-restante-<?= $row['id'] ?>"><?= htmlspecialchars('$' . number_format($monto_restante, 2)) ?></td>
                    <td id="creador-<?= $row['id'] ?>"><?= htmlspecialchars($row['creador']) ?></td>
                    <td class="text-center">
                        <button class="btn btn-sm btn-info text-white btn-ver-pagos" style="min-width: 90px;" data-id="<?= $row['id'] ?>">Ver Pagos</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="modalVerPagos" tabindex="-1" aria-labelledby="modalVerPagosLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalVerPagosLabel">Pagos del Acampante</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <h6 id="acampante-nombre"></h6>
                <p class="mb-3">Monto de la cuota: **$<?= htmlspecialchars(number_format($precio_total_acampante, 2)) ?>**</p>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered">
                        <thead class="table-light">
                            <tr>
                                <th>Cód. Transacción</th>
                                <th>Monto</th>
                                <th>Tasa BCV</th>  
                                <th>Fecha</th>
                                <th>Método de Pago</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody id="pagos-lista">
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between align-items-center mt-3 p-2 border-top">
                    <div class="fw-bold">
                        Monto Abonado: <span id="monto-abonado"></span><br>
                        Monto Restante: <span id="monto-restante"></span>
                    </div>
                    <button id="btn-aprobar-modal" class="btn btn-success" disabled>Aprobar Acampante</button>
                </div>
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

<div class="loader-overlay" id="loader-overlay">
    <div class="spinner-border loader-spinner" role="status">
        <span class="visually-hidden">Cargando...</span>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/v/bs5/dt-1.13.7/datatables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).ready(function() {
    // Lógica del loader
    let loaderTimeout;
    const MIN_LOADER_TIME = 500;

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
    const tabla = $('#tabla-acampantes').DataTable({
        orderCellsTop: true,
        fixedHeader: true,
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
            { targets: 7, width: '120px' },
            { targets: 8, width: '120px' }
        ]
    });

    $('#tabla-acampantes thead tr:eq(1) th').each(function(i) {
        if ($(this).hasClass('filter-input')) {
            $('input', this).on('keyup change', function() {
                if (tabla.column(i).search() !== this.value) {
                    tabla.column(i).search(this.value).draw();
                }
            });
        } else {
            $('select', this).on('change', function() {
                if (tabla.column(i).search() !== this.value) {
                    tabla.column(i).search(this.value).draw();
                }
            });
        }
    });

    const modalVerPagos = new bootstrap.Modal(document.getElementById('modalVerPagos'));
    const pagosLista = document.getElementById('pagos-lista');
    const acampanteNombre = document.getElementById('acampante-nombre');
    const montoAbonadoSpan = document.getElementById('monto-abonado');
    const montoRestanteSpan = document.getElementById('monto-restante');
    const btnAprobarModal = document.getElementById('btn-aprobar-modal');
    const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));

    // Evento para el botón "Ver Pagos"
    $('#tabla-acampantes tbody').on('click', '.btn-ver-pagos', function() {
        const acampanteId = $(this).attr('data-id');
        const fila = $(this).closest('tr');
        const nombre = fila.find(`#nombre-${acampanteId}`).text();

        acampanteNombre.textContent = `Acampante: ${nombre}`;
        btnAprobarModal.setAttribute('data-id', acampanteId);

        // Limpiar datos previos y mostrar loader
        pagosLista.innerHTML = '';
        montoAbonadoSpan.textContent = '...';
        montoRestanteSpan.textContent = '...';
        btnAprobarModal.disabled = true;
        showLoader();

        fetch('get_pagos_por_acampante.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `acampante_id=${acampanteId}`
        })
        .then(response => response.json())
        .then(data => {
            hideLoader(() => {
                if (data.success) {
                    if (data.pagos.length > 0) {
                        pagosLista.innerHTML = data.pagos.map(pago => `
                            <tr>
                                <td>${pago.transaccion_codigo || 'N/A'}</td>
                                <td>${pago.monto_display}</td>
                                <td>${pago.tasa_bcv}</td>
                                <td>${pago.transaccion_fecha}</td>
                                <td>${pago.metodo_pago_nombre}</td>
                                <td>${pago.aprobar_pago}</td>
                            </tr>
                        `).join('');
                    } else {
                        pagosLista.innerHTML = '<tr><td colspan="6" class="text-center">No se han registrado pagos.</td></tr>';
                    }
                    
                    montoAbonadoSpan.textContent = `$${data.monto_abonado_total}`;
                    montoRestanteSpan.textContent = `$${data.monto_restante}`;
                    btnAprobarModal.disabled = !data.listo_para_aprobar;
                    
                } else {
                    pagosLista.innerHTML = `<tr><td colspan="6" class="text-center text-danger">${data.message}</td></tr>`;
                }
                modalVerPagos.show();
            });
        })
        .catch(error => {
            hideLoader(() => {
                console.error('Error al cargar pagos:', error);
                pagosLista.innerHTML = `<tr><td colspan="6" class="text-center text-danger">Error al cargar los pagos.</td></tr>`;
                modalVerPagos.show();
            });
        });
    });

    // Evento para el botón "Aprobar Acampante" dentro del modal
    btnAprobarModal.addEventListener('click', function() {
        const acampanteId = this.getAttribute('data-id');
        const mensaje = document.getElementById('notificacionMensaje');
        showLoader();
        
        fetch('aprobar_acampante.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${acampanteId}`
        })
        .then(res => res.json())
        .then(data => {
            hideLoader(() => {
                if (data.success) {
                    mensaje.innerHTML = `<div class="text-success fw-bold">✅ ${data.message}</div>`;
                    modalVerPagos.hide();
                    modalNotif.show();

                    // Elimina la fila de la tabla sin recargar
                    const fila = document.querySelector(`tr[data-id="${acampanteId}"]`);
                    if (fila) {
                        tabla.row(fila).remove().draw();
                    }
                } else {
                    mensaje.innerHTML = `<div class="text-danger fw-bold">❌ ${data.message}</div>`;
                    modalNotif.show();
                }
            });
        })
        .catch(err => {
            hideLoader(() => {
                console.error(err);
                mensaje.innerHTML = `<div class="text-danger fw-bold">❌ Error al aprobar el acampante.</div>`;
                modalNotif.show();
            });
        });
    });
});
</script>

</body>
</html>