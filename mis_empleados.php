<?php
include 'conexion.php';
session_start();

$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_rol = $_SESSION['usuario']['rol'] ?? null;

if (!$usuario_id) {
    header('Location: login.php');
    exit;
}

// 1. Obtener el valor de la cuota del acampante desde la base de datos
$query_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY id DESC LIMIT 1";
$res_cuota = $conexion->query($query_cuota);

if ($res_cuota && $res_cuota->num_rows > 0) {
    $row_cuota = $res_cuota->fetch_assoc();
    $precio_total_acampante = (float)$row_cuota['valor_cuota'];
} else {
    die("Error: No se ha definido el valor de la cuota en la tabla 'cuota_acampante'.");
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mis Acampantes</title>
    <?php include 'links-recursos.php'; ?>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Mis Acampantes Inscritos</h2>
    <div class="alert alert-info text-center" role="alert">
        El costo total por acampante es de <b><?= number_format($precio_total_acampante, 2) ?> USD</b>.
    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-hover" id="tabla-acampantes">
            <thead class="table-dark text-center">
                <tr>
                    <th>Cédula</th>
                    <th>Nombre</th>
                    <th>Iglesia</th>
                    <th>Monto Abonado ($)</th>
                    <th>Monto Restante ($)</th>
                    <th>Pagado Total</th>
                    <th>Aprobado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Modificación de la consulta para sumar el monto y aplicar la tasa BCV
                $query = "
                        SELECT 
                        a.id, 
                        a.nombre, 
                        a.cedula, 
                        d.nombre AS iglesia, 
                        COALESCE(SUM(
                            CASE 
                                WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto 
                                ELSE pc.transaccion_monto / rp.tasa_bcv 
                            END
                        ), 0) AS monto_abonado,
                         a.inscrito
                    FROM empleados a
                    JOIN departamentos d ON a.departamento_id = d.id
                    JOIN usuarios u ON a.creado_por = u.id
                    LEFT JOIN pre_contabilidad pc ON a.id = pc.acampante_id
                    LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id";

                // Lógica condicional para administradores
                if ($usuario_rol !== 'admin') {
                    $query .= " WHERE a.creado_por = ?";
                }
                
                $query .= " GROUP BY a.id, a.nombre, a.cedula, d.nombre, a.inscrito";

                $stmt = $conexion->prepare($query);
                
                if (!$stmt) {
                    die('Error en la preparación de la consulta: ' . $conexion->error);
                }

                if ($usuario_rol !== 'admin') {
                    $stmt->bind_param("i", $usuario_id);
                }
                
                $stmt->execute();
                $res = $stmt->get_result();

                while ($row = $res->fetch_assoc()):
                    $monto_abonado = $row['monto_abonado'];
                    $monto_restante = $precio_total_acampante - $monto_abonado;
                    $pagado_total = ($monto_abonado >= $precio_total_acampante) ? 'Sí' : 'No';
                    $v = $row['inscrito'] == 0 ? 'No' : 'Sí';
                ?>
                <tr data-id="<?= $row['id'] ?>">
                    <td id="cedula-<?= $row['id'] ?>"><?= htmlspecialchars($row['cedula']) ?></td>
                    <td id="nombre-<?= $row['id'] ?>"><?= htmlspecialchars($row['nombre']) ?></td>
                    <td id="iglesia-<?= $row['id'] ?>"><?= htmlspecialchars($row['iglesia']) ?></td>
                    <td id="monto-abonado-<?= $row['id'] ?>"><?= htmlspecialchars(number_format($monto_abonado, 2)) ?></td>
                    <td id="monto-restante-<?= $row['id'] ?>"><?= htmlspecialchars(number_format($monto_restante, 2)) ?></td>
                    <td id="pagado-total-<?= $row['id'] ?>"><?= htmlspecialchars($pagado_total) ?></td>
                    <td id="aprobado-<?= $row['id'] ?>"><?= htmlspecialchars($v) ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary btn-editar-acampante" data-id="<?= $row['id'] ?>">Editar</button>
                    </td>
                </tr>
                <?php endwhile; ?>
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
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="notificacionMensaje"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.btn-editar-acampante').forEach(btn => {
        btn.addEventListener('click', function () {
            const acampanteId = this.dataset.id;

            fetch(`cargar_modal_acampante.php?id=${acampanteId}`)
                .then(res => res.text())
                .then(html => {
                    document.getElementById('modalEditarContenido').innerHTML = html;
                    const modal = new bootstrap.Modal(document.getElementById('modalEditarAcampante'));
                    modal.show();

                    const form = document.querySelector('.form-editar-acampante');
                    form.addEventListener('submit', function(e) {
                        e.preventDefault();
                        const id = this.dataset.id;
                        const formData = new FormData(this);
                        formData.append('id', id);

                        fetch('actualizar_acampante.php', {
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.json())
                        .then(data => {
                            const mensaje = document.getElementById('notificacionMensaje');
                            const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));

                            if (data.success) {
                                mensaje.innerHTML = `<div class="text-success fw-bold">✅ ${data.message}</div>`;
                                modalNotif.show();

                                // --- LÓGICA PARA ACTUALIZAR LA FILA DE LA TABLA ---
                                const acampante = data.acampante;
                                const fila = document.querySelector(`tr[data-id="${acampante.id}"]`);

                                if (fila) {
                                    // Actualizar las celdas con los nuevos datos
                                    document.getElementById(`cedula-${acampante.id}`).textContent = acampante.cedula;
                                    document.getElementById(`nombre-${acampante.id}`).textContent = acampante.nombre;
                                    document.getElementById(`iglesia-${acampante.id}`).textContent = acampante.iglesia;
                                    document.getElementById(`aprobado-${acampante.id}`).textContent = acampante.inscrito;
                                }

                                const modalEditar = bootstrap.Modal.getInstance(document.getElementById('modalEditarAcampante'));
                                modalEditar.hide();
                            } else {
                                mensaje.innerHTML = `<div class="text-danger fw-bold">❌ ${data.message}</div>`;
                                modalNotif.show();
                            }
                        })
                        .catch(err => {
                            console.error(err);
                            const mensaje = document.getElementById('notificacionMensaje');
                            mensaje.innerHTML = `<div class="text-danger fw-bold">❌ Error al enviar los datos.</div>`;
                            const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));
                            modalNotif.show();
                        });
                    });
                })
                .catch(err => {
                    console.error('Error al cargar el modal:', err);
                    alert('No se pudo cargar la información del acampante.');
                });
        });
    });
});
</script>
</body>
</html>