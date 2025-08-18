<?php
session_start();
include 'conexion.php';

// Verificar si el usuario es admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
              header("Location: index.php");
              exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
              <meta charset="UTF-8">
              <meta name="viewport" content="width=device-width, initial-scale=1">
              <title>Gestión de Acampantes</title>
              <?php include 'links-recursos.php'; ?>
              
            
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
              <h2 class="text-center mb-4">Eliminar Acampantes</h2>
              <div class="table-responsive">
                            <table class="table table-bordered table-hover" id="tabla-empleados">
                                          <thead class="table-dark text-center">
                                                        <tr>
                                                                      <th>Cédula</th>
                                                                      <th>Nombre</th>
                                                                      <th>Iglesia</th>
                                                                      <th>Referencia</th>
                                                                      <th>Monto</th>
                                                                      <th>Registrado por</th>
                                                                      <th>Acción</th>
                                                        </tr>
                                          </thead>
                                          <tbody>
                                                        <?php
                                                        // Se une la tabla 'pre_contabilidad' para obtener los datos de la transacción
                                                        $res = $conexion->query("
                                                                      SELECT      
                                                                                    e.id,      
                                                                                    e.cedula,      
                                                                                    e.nombre,      
                                                                                    d.nombre AS iglesia,      
                                                                                    u.nombre AS creador,
                                                                                    pc.transaccion_codigo,
                                                                                    pc.transaccion_monto
                                                                      FROM empleados e
                                                                      JOIN departamentos d ON e.departamento_id = d.id
                                                                      JOIN usuarios u ON e.creado_por = u.id
                                                                      LEFT JOIN pre_contabilidad pc ON e.pre_contabilidad_id = pc.id
                                                        ");
                                                        while ($row = $res->fetch_assoc()):
                                                        ?>
                                                        <tr data-id="<?= $row['id'] ?>">
                                                                      <td><?= htmlspecialchars($row['cedula']) ?></td>
                                                                      <td><?= htmlspecialchars($row['nombre']) ?></td>
                                                                      <td><?= htmlspecialchars($row['iglesia']) ?></td>
                                                                      <td><?= htmlspecialchars($row['transaccion_codigo'] ?? 'N/A') ?></td>
                                                                      <td><?= htmlspecialchars($row['transaccion_monto'] ?? 'N/A') ?></td>
                                                                      <td><?= htmlspecialchars($row['creador']) ?></td>
                                                                      <td class="text-center">
                                                                                    <button class="btn btn-sm btn-danger btn-eliminar-empleado" style="min-width: 80px;" data-id="<?= $row['id'] ?>">Eliminar</button>
                                                                      </td>
                                                        </tr>
                                                        <?php endwhile; ?>
                                          </tbody>
                            </table>
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

<div class="modal fade" id="confirmacionModal" tabindex="-1" aria-labelledby="confirmacionLabel" aria-hidden="true">
              <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                          <div class="modal-header bg-danger text-white">
                                                        <h5 class="modal-title" id="confirmacionLabel">Confirmar eliminación</h5>
                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                          </div>
                                          <div class="modal-body">
                                                        ¿Estás seguro de que deseas eliminar este empleado?
                                          </div>
                                          <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="button" class="btn btn-danger" id="btnConfirmarEliminar">Eliminar</button>
                                          </div>
                            </div>
              </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
              let empleadoIdSeleccionado = null;

              const modalConfirmacion = new bootstrap.Modal(document.getElementById('confirmacionModal'));
              const modalNotif = new bootstrap.Modal(document.getElementById('notificacionModal'));
              const mensaje = document.getElementById('notificacionMensaje');

              document.querySelectorAll('.btn-eliminar-empleado').forEach(function (btn) {
                            btn.addEventListener('click', function () {
                                          empleadoIdSeleccionado = this.getAttribute('data-id');
                                          modalConfirmacion.show();
                            });
              });

              document.getElementById('btnConfirmarEliminar').addEventListener('click', function () {
                            if (!empleadoIdSeleccionado) return;

                            fetch('eliminar_empleado.php', {
                                          method: 'POST',
                                          headers: {
                                                        'Content-Type': 'application/x-www-form-urlencoded'
                                          },
                                          body: 'id=' + encodeURIComponent(empleadoIdSeleccionado)
                            })
                            .then(res => res.json())
                            .then(data => {
                                          if (data.success) {
                                                        mensaje.innerHTML = `<div class="text-success fw-bold">✅ ${data.message}</div>`;
                                                        const fila = document.querySelector(`tr[data-id="${empleadoIdSeleccionado}"]`);
                                                        if (fila) fila.remove();
                                          } else {
                                                        mensaje.innerHTML = `<div class="text-danger fw-bold">❌ ${data.message}</div>`;
                                          }
                                          modalConfirmacion.hide();
                                          modalNotif.show();
                            })
                            .catch(err => {
                                          console.error(err);
                                          mensaje.innerHTML = `<div class="text-danger fw-bold">❌ Error al eliminar el empleado.</div>`;
                                          modalConfirmacion.hide();
                                          modalNotif.show();
                            });
              });
});
</script>
</body>
</html>