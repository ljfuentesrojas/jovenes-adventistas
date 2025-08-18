<?php
session_start();
include 'conexion.php';

$readonly = (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') ? '' : 'readonly';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    echo "ID de acampante no válido.";
    exit;
}

$id = (int)$_GET['id'];

$query = "
    SELECT
        a.*,
        t.regiones_id AS region_id,
        d.tiendas_id AS tienda_id
    FROM empleados a
    LEFT JOIN departamentos d ON a.departamento_id = d.id
    LEFT JOIN tiendas t ON d.tiendas_id = t.id
    WHERE a.id = ?
";
$stmt = $conexion->prepare($query);
if (!$stmt) {
    die('Error en la preparación de la consulta: ' . $conexion->error);
}
$stmt->bind_param("i", $id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
    http_response_code(404);
    echo "Acampante no encontrado.";
    exit;
}

$cargos = $conexion->query("SELECT id, nombre FROM cargos ORDER BY nombre");
$regiones = $conexion->query("SELECT id, nombre FROM regiones ORDER BY nombre");
$clubes = $conexion->query("SELECT id, nombre_club FROM clubes ORDER BY nombre_club");

?>

<div class="modal-header bg-primary text-white">
    <div>
        <?php if (!empty($row['foto'])): ?>
            <img style="margin: 1em;" src="fotos/<?= htmlspecialchars($row['foto']) ?>" width="60" height="60" class="mt-2 rounded-circle">
        <?php endif; ?>
    </div>
    <h5 class="modal-title">Editar Acampante</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body">
    <form id="form-editar-acampante" class="form-editar-acampante row g-3" data-id="<?= $row['id'] ?>" enctype="multipart/form-data">
        <div class="col-12 col-md-6">
            <label for="nombre" class="form-label">Nombre</label>
            <input type="text" name="nombre" id="nombre" class="form-control" value="<?= htmlspecialchars($row['nombre']) ?>" data-required="true">
        </div>
        <div class="col-12 col-md-6">
            <label for="telefono" class="form-label">Teléfono</label>
            <input type="tel" name="telefono" id="telefono" class="form-control" value="<?= htmlspecialchars($row['telefono']) ?>" data-required="true">
        </div>
        <div class="col-12 col-md-6">
            <label for="fecha_nacimiento" class="form-label">Fecha de Nacimiento</label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control" value="<?= htmlspecialchars($row['fecha_nacimiento'] ?? '') ?>" data-required="true">
        </div>
        <div class="col-12 col-md-6">
            <label for="edad" class="form-label">Edad</label>
            <input type="number" name="edad" id="edad" class="form-control" readonly>
        </div>
        <div class="col-12 col-md-6">
            <label for="cedula" class="form-label">Cédula</label>
            <input type="text" name="cedula" id="cedula" class="form-control" <?= $readonly; ?> value="<?= htmlspecialchars($row['cedula']) ?>" data-required="true" pattern="\d+">
        </div>
        <div class="col-12 col-md-6">
            <label for="foto" class="form-label">Foto</label>
            <input type="file" name="foto" id="foto" class="form-control" accept="image/*">
        </div>
        <div class="col-12 col-md-6">
            <label for="cargo_id" class="form-label">Tipo de Acampante</label>
            <select name="cargo_id" id="cargo_id" class="form-select" data-required="true" data-selected="<?= htmlspecialchars($row['cargo_id'] ?? '') ?>">
                <option value="">----Seleccione----</option>
                <?php $cargos->data_seek(0); ?>
                <?php while ($cargo = $cargos->fetch_assoc()): ?>
                    <option value="<?= $cargo['id'] ?>" <?= $cargo['id'] == $row['cargo_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cargo['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="col-12 col-md-6">
            <label for="region_id" class="form-label">Zona</label>
            <select name="region_id" id="region_id" class="form-select" data-selected="<?= htmlspecialchars($row['region_id'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
                <?php $regiones->data_seek(0); ?>
                <?php while ($reg = $regiones->fetch_assoc()): ?>
                    <option value="<?= $reg['id'] ?>" <?= $reg['id'] == $row['region_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($reg['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="tienda_id" class="form-label">Distrito</label>
            <select name="tienda_id" id="tienda_id" class="form-select" data-selected="<?= htmlspecialchars($row['tienda_id'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="departamento_id" class="form-label">Iglesia</label>
            <select name="departamento_id" id="departamento_id" class="form-select" data-selected="<?= htmlspecialchars($row['departamento_id'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
            </select>
        </div>
        <div class="col-12 col-md-6">
            <label for="club_id" class="form-label">Club</label>
            <select name="club_id" id="club_id" class="form-select" data-selected="<?= htmlspecialchars($row['club_id'] ?? '') ?>">
                <option value="">----Seleccione----</option>
                <?php $clubes->data_seek(0); ?>
                <?php while ($club = $clubes->fetch_assoc()): ?>
                    <option value="<?= $club['id'] ?>" <?= $club['id'] == $row['club_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($club['nombre_club']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <div class="col-12 col-md-6">
            <label class="form-label">Estado Inscripción</label>
            <input type="text" class="form-control" value="<?= $row['inscrito'] == 0 ? 'Pendiente' : 'Aprobado' ?>" readonly>
        </div>
    </form>
</div>
<div class="modal-footer">
    <button type="submit" form="form-editar-acampante" class="btn btn-success">Guardar cambios</button>
    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
</div>