<?php include 'conexion.php';
$id = $_GET['id'];
$res = $conexion->query("SELECT * FROM empleados WHERE id = $id");
$emp = $res->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Editar Empleado</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include 'dashboard.php'; ?>
<div class="container mt-5">
  <h2>Editar Empleado</h2>
  <form action="actualizar_empleado.php" method="POST" enctype="multipart/form-data" class="row g-3">
    <input type="hidden" name="id" value="<?= $emp['id'] ?>">
    <div class="col-md-6">
      <label class="form-label">Nombre</label>
      <input type="text" name="nombre" class="form-control" value="<?= $emp['nombre'] ?>" required>
    </div>
    <div class="col-md-6">
      <label class="form-label">Teléfono</label>
      <input type="text" name="telefono" class="form-control" value="<?= $emp['telefono'] ?>" required>
    </div>
    <div class="col-12">
      <label class="form-label">Dirección</label>
      <textarea name="direccion" class="form-control" required><?= $emp['direccion'] ?></textarea>
    </div>
    <div class="col-md-6">
      <label class="form-label">Foto actual</label><br>
      <img src="fotos/<?= $emp['foto'] ?>" width="80" class="rounded">
      <input type="file" name="foto" class="form-control mt-2">
    </div>
    <div class="col-md-6">
      <label class="form-label">Cargo</label>
      <select name="cargo_id" class="form-select" required>
        <?php
        $res = $conexion->query("SELECT id, nombre FROM cargos");
        while ($row = $res->fetch_assoc()) {
          $selected = $row['id'] == $emp['cargo_id'] ? 'selected' : '';
          echo "<option value='{$row['id']}' $selected>{$row['nombre']}</option>";
        }
        ?>
      </select>
    </div>
    <div class="col-12">
      <button type="submit" class="btn btn-success">Actualizar</button>
    </div>
  </form>
</div>
</body>
</html>