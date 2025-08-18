<?php
include 'conexion.php'; // Asegúrate de que este archivo conecta correctamente a tu base de datos

$id = intval($_GET['id']);
$row = $conexion->query("SELECT * FROM empleados WHERE id = $id")->fetch_assoc();

if (!$row) {
  echo "<div class='alert alert-danger'>Empleado no encontrado.</div>";
  exit;
}
?>

<div class="modal fade" id="modal<?= $row['id'] ?>" tabindex="-1" aria-labelledby="modalLabel<?= $row['id'] ?>" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <form class="form-editar-empleado" data-id="<?= $row['id'] ?>" enctype="multipart/form-data" method="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="modalLabel<?= $row['id'] ?>">Editar empleado</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Nombre</label>
              <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($row['nombre']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Teléfono</label>
              <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($row['telefono']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Cargo</label>
              <select name="cargo_id" class="form-select">
                <?php
                $cargos = $conexion->query("SELECT id, nombre FROM cargos");
                while ($cargo = $cargos->fetch_assoc()):
                ?>
                  <option value="<?= $cargo['id'] ?>" <?= $cargo['id'] == $row['cargo_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cargo['nombre']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Tienda</label>
              <select name="tienda_id" class="form-select">
                <?php
                $tiendas = $conexion->query("SELECT id, nombre FROM tiendas");
                while ($tienda = $tiendas->fetch_assoc()):
                ?>
                  <option value="<?= $tienda['id'] ?>" <?= $tienda['id'] == $row['tienda_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($tienda['nombre']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Departamento</label>
              <select name="departamento_id" class="form-select">
                <?php
                $departamentos = $conexion->query("SELECT id, nombre FROM departamentos");
                while ($dep = $departamentos->fetch_assoc()):
                ?>
                  <option value="<?= $dep['id'] ?>" <?= $dep['id'] == $row['departamento_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($dep['nombre']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Región</label>
              <select name="region_id" class="form-select">
                <?php
                $regiones = $conexion->query("SELECT id, nombre FROM regiones");
                while ($reg = $regiones->fetch_assoc()):
                ?>
                  <option value="<?= $reg['id'] ?>" <?= $reg['id'] == $row['region_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($reg['nombre']) ?>
                  </option>
                <?php endwhile; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Código Transacción</label>
              <input type="text" name="codigo_transaccion" class="form-control" value="<?= htmlspecialchars($row['transaccion_codigo']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Monto Transacción</label>
              <input type="number" step="0.01" name="monto_transaccion" class="form-control" value="<?= htmlspecialchars($row['transaccion_monto']) ?>">
            </div>
            <div class="col-md-6">
              <label class="form-label">Estado Inscripción</label>
              <?php
              $estado = $row['inscrito'] == 1 ? 'Aprobado' : 'Pendiente';
              ?>
              <input type="text" class="form-control" value="<?= $estado ?>" readonly>
            </div>
            <div class="col-md-6">
              <label class="form-label">Foto</label>
              <input type="file" name="foto" class="form-control">
              <?php if ($row['foto']): ?>
                <img src="fotos/<?= $row['foto'] ?>" width="60" height="60" class="mt-2 rounded-circle">
              <?php else: ?>
                <p class="text-muted mt-2">Sin foto</p>
              <?php endif; ?>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button onclick="guardarEmpleadoAjax(event, document.querySelector('.form-editar-empleado'))" class="btn btn-success">
  Guardar cambios
</button>
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>