<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
  echo "<div class='p-3 text-danger'>Acceso denegado</div>";
  exit;
}

$id = intval($_GET['id'] ?? 0);
$res = $conexion->query("SELECT id, nombre, correo, rol, estado FROM usuarios WHERE id = $id");
$usuario = $res->fetch_assoc();

$roles = $conexion->query("SELECT DISTINCT rol FROM roles");
$activo = $conexion->query("SELECT DISTINCT estado FROM estado_usuario");
?>

<div class="modal-header bg-primary text-white">
  <h5 class="modal-title">Editar Usuario</h5>
  <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
  <form class="form-editar-usuario" data-id="<?= $usuario['id'] ?>">
    <div class="mb-3">
      <label class="form-label">Nombre</label>
      <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Correo</label>
      <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
    </div>
    <div class="mb-3">
      <label class="form-label">Rol</label>
      <select name="rol" class="form-select" required>
        <?php while ($r = $roles->fetch_assoc()): ?>
          <option value="<?= $r['rol'] ?>" <?= $r['rol'] === $usuario['rol'] ? 'selected' : '' ?>><?= ucfirst($r['rol']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Estado Activo</label>
      <select name="estado" class="form-select" required>
        <?php while ($e = $activo->fetch_assoc()): ?>
          <option value="<?= $e['estado'] ?>" <?= $e['estado'] === $usuario['estado'] ? 'selected' : '' ?>><?= ucfirst($e['estado']) ?></option>
        <?php endwhile; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Nueva Clave (opcional)</label>
      <input type="text" name="clave_nueva" class="form-control">
    </div>
    <div class="text-end">
      <button type="submit" class="btn btn-success">Guardar cambios</button>
    </div>
  </form>
</div>