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

// Incluir la columna id_sexo en la consulta
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
// Nueva consulta para obtener los datos de la tabla de sexo
$sexos = $conexion->query("SELECT id, sexo FROM sexo ORDER BY id");

// Determina la ruta de la imagen: usa la foto del acampante si existe, de lo contrario, usa un avatar predeterminado.
$foto_src = !empty($row['foto']) ? '/fotos/' . htmlspecialchars($row['foto']) : 'fotos/avatar.png';


// Determina la clase de color para el estado de inscripción
$estado_inscripcion_clase = ($row['inscrito'] == 0) ? 'bg-warning text-dark' : 'bg-success';
$estado_inscripcion_style = ($row['inscrito'] == 0) ? '' : ' color: white;';
$estado_inscripcion_texto = ($row['inscrito'] == 0) ? 'Pendiente' : 'Aprobado';

?>

<div class="modal-header bg-primary text-white d-flex align-items-center">
    <img src="<?= $foto_src ?>" width="110" height="110" class="me-3 rounded-circle">
    <h5 class="modal-title">Editar Acampante</h5>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<div class="modal-body small-text">
    <form id="form-editar-acampante" class="form-editar-acampante row g-3" data-id="<?= $row['id'] ?>" enctype="multipart/form-data">
        <div class="col-12 col-md-3">
            <label for="nombre" class="form-label"><b>Nombre</b></label>
            <input type="text" name="nombre" id="nombre" class="form-control form-control-sm" value="<?= htmlspecialchars($row['nombre']) ?>" data-required="true">
        </div>
        <div class="col-12 col-md-3">
            <label for="telefono" class="form-label"><b>Teléfono</b></label>
            <input type="tel" name="telefono" id="telefono" class="form-control form-control-sm" value="<?= htmlspecialchars($row['telefono']) ?>" data-required="true">
        </div>
        <div class="col-12 col-md-3">
            <label for="fecha_nacimiento" class="form-label"><b>Fecha de Nacimiento</b></label>
            <input type="date" name="fecha_nacimiento" id="fecha_nacimiento" class="form-control form-control-sm" value="<?= htmlspecialchars($row['fecha_nacimiento'] ?? '') ?>" data-required="true">
        </div>
        <div class="col-12 col-md-3">
            <label for="edad" class="form-label"><b>Edad</b></label>
            <input type="number" name="edad" id="edad" class="form-control form-control-sm" readonly>
        </div>
        <div class="col-12 col-md-3">
            <label for="cedula" class="form-label"><b>Cédula</b></label>
            <input type="text" name="cedula" id="cedula" class="form-control form-control-sm" <?= $readonly; ?> value="<?= htmlspecialchars($row['cedula']) ?>" data-required="true" pattern="\d+">
        </div>
        <div class="col-12 col-md-3">
            <label for="id_sexo" class="form-label"><b>Sexo</b></label>
            <select name="id_sexo" id="id_sexo" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['id_sexo'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
                <?php while ($sexo = $sexos->fetch_assoc()): ?>
                    <option value="<?= $sexo['id'] ?>" <?= $sexo['id'] == $row['id_sexo'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($sexo['sexo']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="cargo" class="form-label"><b>Tipo de Acampante</b></label>
            <select name="cargo_id" id="cargo" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['cargo_id'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
                <?php $cargos->data_seek(0); ?>
                <?php while ($cargo = $cargos->fetch_assoc()): ?>
                    <option value="<?= $cargo['id'] ?>" <?= $cargo['id'] == $row['cargo_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cargo['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="region" class="form-label"><b>Zona</b></label>
            <select name="region_id" id="region" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['region_id'] ?? '') ?>" data-required="true">
                <option value="">----Seleccione----</option>
                <?php $regiones->data_seek(0); ?>
                <?php while ($reg = $regiones->fetch_assoc()): ?>
                    <option value="<?= $reg['id'] ?>" <?= $reg['id'] == $row['region_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($reg['nombre']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="tienda" class="form-label"><b>Distrito</b></label>
            <select name="tienda_id" id="tienda" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['tienda_id'] ?? '') ?>" data-required="true" disabled>
                <option value="">----Seleccione----</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="departamento" class="form-label"><b>Iglesia</b></label>
            <select name="departamento_id" id="departamento" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['departamento_id'] ?? '') ?>" data-required="true" disabled>
                <option value="">----Seleccione----</option>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label for="club" class="form-label"><b>Club</b></label>
            <select name="club_id" id="club" class="form-select form-select-sm" data-selected="<?= htmlspecialchars($row['club_id'] ?? '') ?>">
                <option value="">----Seleccione----</option>
                <?php $clubes->data_seek(0); ?>
                <?php while ($club = $clubes->fetch_assoc()): ?>
                    <option value="<?= $club['id'] ?>" <?= $club['id'] == $row['club_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($club['nombre_club']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
        <div class="col-12 col-md-3">
            <label class="form-label"><b>Estado Inscripción</b></label>
            <input type="text" style="<?= $estado_inscripcion_style; ?>" class="form-control form-control-sm <?= $estado_inscripcion_clase; ?>" value="<?= $row['inscrito'] == 0 ? 'Pendiente' : 'Aprobado' ?>" readonly>
        </div>
        <div class="col-12">
    <label for="foto" class="form-label"><b>Foto del acampante</b></label>
    <div class="col-12 form-control-sm">
    
    <input type="file" name="foto" id="foto" class="input-file-oculto">
    
    <label for="foto" class="btn btn-primary custom-file-button">
        Seleccionar archivo
    </label>
    
    <span id="nombre-archivo" class="ms-2">No se ha seleccionado ningún archivo.</span>
</div>
</div>
        <div class="modal-footer">
            <button type="submit" class="btn btn-success">Guardar cambios</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
    </form>
</div>