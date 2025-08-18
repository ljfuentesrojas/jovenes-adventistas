<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    echo "Acceso denegado";
    exit;
}

$action = $_GET['action'] ?? 'add';
$tabla = $_GET['tabla'] ?? '';
$id = $_GET['id'] ?? null;

$titulo_modal = ($action === 'add') ? 'Agregar Nuevo Registro' : 'Editar Registro';

$datos = [];
$parent_options = [];

// Obtener los datos del registro si la acción es editar
if ($action === 'edit' && $id) {
    $stmt = $conexion->prepare("SELECT * FROM " . $conexion->real_escape_string($tabla) . " WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $datos = $resultado->fetch_assoc();
    $stmt->close();
}

// Obtener las opciones para los dropdowns si la tabla tiene una relación
if ($tabla === 'tiendas') { // Distritos dependen de Zonas (regiones)
    $stmt = $conexion->prepare("SELECT id, nombre FROM regiones ORDER BY nombre ASC");
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($fila = $resultado->fetch_assoc()) {
        $parent_options[] = $fila;
    }
    $stmt->close();
} elseif ($tabla === 'departamentos') { // Iglesias dependen de Distritos (tiendas)
    $stmt = $conexion->prepare("SELECT id, nombre FROM tiendas ORDER BY nombre ASC");
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($fila = $resultado->fetch_assoc()) {
        $parent_options[] = $fila;
    }
    $stmt->close();
} elseif ($tabla === 'clubes') { // Clubes dependen de Iglesias (departamentos)
    $stmt = $conexion->prepare("SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
    $stmt->execute();
    $resultado = $stmt->get_result();
    while ($fila = $resultado->fetch_assoc()) {
        $parent_options[] = $fila;
    }
    $stmt->close();
}

?>

<div class="modal-header bg-dark text-white">
    <h5 class="modal-title" id="modalFormularioLabel"><?= $titulo_modal ?></h5>
    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
</div>
<form class="form-dinamico" data-tabla="<?= htmlspecialchars($tabla) ?>" data-action="<?= htmlspecialchars($action) ?>" data-id="<?= htmlspecialchars($id) ?>">
    <div class="modal-body">
        <?php if ($tabla === 'cuota_acampante'): ?>
            <div class="mb-3">
                <label for="valor_cuota" class="form-label">Valor Cuota</label>
                <input type="number" step="0.01" class="form-control" id="valor_cuota" name="valor_cuota" value="<?= htmlspecialchars($datos['valor_cuota'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label for="moneda" class="form-label">Moneda</label>
                <input type="text" class="form-control" id="moneda" name="moneda" value="<?= htmlspecialchars($datos['moneda'] ?? 'USD') ?>" required>
            </div>
            <div class="mb-3">
                <label for="fecha_establecida" class="form-label">Fecha Establecida</label>
                <input type="date" class="form-control" id="fecha_establecida" name="fecha_establecida" value="<?= htmlspecialchars($datos['fecha_establecida'] ?? date('Y-m-d')) ?>" required>
            </div>
        <?php elseif ($tabla === 'historico_tasa_bcv'): ?>
            <div class="mb-3">
                <label for="fecha" class="form-label">Fecha</label>
                <input type="date" class="form-control" id="fecha" name="fecha" value="<?= htmlspecialchars($datos['fecha'] ?? date('Y-m-d')) ?>" required>
            </div>
            <div class="mb-3">
                <label for="tasa_bcv" class="form-label">Tasa BCV</label>
                <input type="number" step="0.0001" class="form-control" id="tasa_bcv" name="tasa_bcv" value="<?= htmlspecialchars($datos['tasa_bcv'] ?? '') ?>" required>
            </div>

    <?php elseif ($tabla === 'sexo'): ?>
            <div class="mb-3">
                <label for="fecha" class="form-label">Tipo Sexo</label>
                <input type="text" class="form-control" id="sexo" name="sexo" value="<?= htmlspecialchars($datos['sexo'] ?? '') ?>" required>
            </div>
            
        <?php else: ?>
            <?php if ($tabla === 'tiendas'): ?>
                <div class="mb-3">
                    <label for="parent_id" class="form-label">Zona</label>
                    <select class="form-select" id="parent_id" name="regiones_id" required>
                        <option value="">-- Seleccione una Zona --</option>
                        <?php foreach ($parent_options as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['id']) ?>" <?= (isset($datos['regiones_id']) && $datos['regiones_id'] == $opcion['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($tabla === 'departamentos'): ?>
                <div class="mb-3">
                    <label for="parent_id" class="form-label">Distrito</label>
                    <select class="form-select" id="parent_id" name="tiendas_id" required>
                        <option value="">-- Seleccione un Distrito --</option>
                        <?php foreach ($parent_options as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['id']) ?>" <?= (isset($datos['tiendas_id']) && $datos['tiendas_id'] == $opcion['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php elseif ($tabla === 'clubes'): ?>
                <div class="mb-3">
                    <label for="parent_id" class="form-label">Iglesia</label>
                    <select class="form-select" id="parent_id" name="id_departamento" required>
                        <option value="">-- Seleccione una Iglesia --</option>
                        <?php foreach ($parent_options as $opcion): ?>
                            <option value="<?= htmlspecialchars($opcion['id']) ?>" <?= (isset($datos['id_departamentos']) && $datos['id_departamentos'] == $opcion['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($opcion['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <?php
                    $nombre_campo = ($tabla === 'clubes') ? 'nombre_club' : 'nombre';
                    $etiqueta_campo = ($tabla === 'clubes') ? 'Nombre del Club' : 'Nombre';
                ?>
                <label for="<?= htmlspecialchars($nombre_campo) ?>" class="form-label"><?= htmlspecialchars($etiqueta_campo) ?></label>
                <input type="text" class="form-control" id="<?= htmlspecialchars($nombre_campo) ?>" name="<?= htmlspecialchars($nombre_campo) ?>" value="<?= htmlspecialchars($datos[$nombre_campo] ?? '') ?>" required>
            </div>
        <?php endif; ?>
    </div>
    <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </div>
</form>