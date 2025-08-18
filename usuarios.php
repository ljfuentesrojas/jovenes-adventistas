<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
  echo "Acceso denegado";
  exit;
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Usuarios</title>
  <?php include 'links-recursos.php'; ?>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
  <h2 class="text-center mb-4">Usuarios Registrados</h2>
  <div class="table-responsive">
    <table class="table table-bordered table-hover" id="tabla-usuarios">
      <thead class="table-dark text-center">
        <tr>
          <th>ID</th>
          <th>Nombre</th>
          <th>Correo</th>
          <th>Rol</th>
          <th>Activo</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $resultado = $conexion->query("SELECT id, nombre, correo, rol, clave, estado FROM usuarios");
        while ($usuario = $resultado->fetch_assoc()):
        ?>
        <tr data-id="<?= $usuario['id'] ?>">
          <td><?= $usuario['id'] ?></td>
          <td class="nombre"><?= htmlspecialchars($usuario['nombre']) ?></td>
          <td class="correo"><?= htmlspecialchars($usuario['correo']) ?></td>
          <td class="rol"><?= htmlspecialchars($usuario['rol']) ?></td>
          <td class="estado"><?= htmlspecialchars(($usuario['estado']=='activo') ? 'Si' : 'No') ?></td>
        <!--   <td>
            <span class="clave-oculta">••••••••</span>
            <button class="btn btn-sm btn-secondary mostrar-clave">👁️</button>
            <span class="clave-real d-none"><?= htmlspecialchars($usuario['clave']) ?></span>
          </td>-->
          <td> 
            <button class="btn btn-sm btn-primary btn-editar-usuario" data-id="<?= $usuario['id'] ?>">Editar</button>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal dinámico -->
<div class="modal fade" id="modalEditarUsuario" tabindex="-1" aria-labelledby="modalEditarLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content" id="modalEditarContenido">
      <!-- Se carga vía AJAX -->
    </div>
  </div>
</div>

<!-- Modal de notificación -->
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
// document.querySelectorAll('.mostrar-clave').forEach(btn => {
//   btn.addEventListener('click', function () {
//     const td = this.closest('td');
//     td.querySelector('.clave-oculta').classList.add('d-none');
//     td.querySelector('.clave-real').classList.remove('d-none');
//     this.classList.add('d-none');
//   });
// });

document.querySelectorAll('.btn-editar-usuario').forEach(btn => {
  btn.addEventListener('click', function () {
    const usuarioId = this.dataset.id;

    fetch(`cargar_modal_usuario.php?id=${usuarioId}`)
      .then(res => res.text())
      .then(html => {
        document.getElementById('modalEditarContenido').innerHTML = html;
        const modal = new bootstrap.Modal(document.getElementById('modalEditarUsuario'));
        modal.show();

        const form = document.querySelector('.form-editar-usuario');
        form.addEventListener('submit', function(e) {
          e.preventDefault();
          const id = this.dataset.id;
          const formData = new FormData(this);
          formData.append('id', id);

          fetch('editar_usuario.php', {
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

              const u = data.usuario;
              const fila = document.querySelector(`tr[data-id="${u.id}"]`);
              fila.querySelector('.nombre').textContent = u.nombre;
              fila.querySelector('.correo').textContent = u.correo;
              fila.querySelector('.rol').textContent = u.rol;
              fila.querySelector('.estado').textContent = u.estado;

              const tdClave = fila.querySelector('td:nth-child(6)');
              // tdClave.querySelector('.clave-oculta').classList.remove('d-none');
              // tdClave.querySelector('.clave-real').classList.add('d-none');
              // tdClave.querySelector('.mostrar-clave').classList.remove('d-none');

              const modalEditar = bootstrap.Modal.getInstance(document.getElementById('modalEditarUsuario'));
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
        alert('No se pudo cargar la información del usuario.');
      });
  });
});
</script>
</body>
</html>