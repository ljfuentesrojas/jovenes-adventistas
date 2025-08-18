<?php
session_start();
if (!isset($_SESSION['usuario_id'])) {
  header("Location: login.php");
  exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Editar Perfil</title>
  <?php include 'links-recursos.php'; ?>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8 col-lg-6">
      <div class="card shadow rounded-4">
        <div class="card-body">
          <h3 class="text-center mb-4">Editar Perfil</h3>
          <div id="mensajePerfil"></div>
          <form id="formPerfil" class="row g-3">
            <div class="col-12">
              <label class="form-label">Nombre</label>
              <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($_SESSION['usuario_nombre']) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Correo</label>
              <input type="email" name="correo" readonly class="form-control" value="<?= htmlspecialchars($_SESSION['usuario']['correo']) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label">Clave actual</label>
              <input type="password" name="clave_actual" class="form-control" required>
            </div>
            <div class="col-12">
              <label class="form-label">Nueva clave (opcional)</label>
              <input type="password" name="clave_nueva" class="form-control">
            </div>
            <div class="col-12 text-center">
              <button type="submit" class="btn btn-primary px-5">Guardar cambios</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('formPerfil').addEventListener('submit', function(e) {
  e.preventDefault();
  const form = e.target;
  const datos = new FormData(form);
  const mensaje = document.getElementById('mensajePerfil');

  fetch('actualizar_perfil.php', {
    method: 'POST',
    body: datos
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      mensaje.innerHTML = `<div class="alert alert-success">${data.message}</div>`;
      form.reset();
    } else {
      mensaje.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    mensaje.innerHTML = `<div class="alert alert-danger">Error de conexión.</div>`;
  });
});
</script>
</body>
</html>