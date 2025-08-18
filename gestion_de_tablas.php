<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
    echo "Acceso denegado";
    exit;
}

// Mapeo de tablas y sus títulos
$tablas_permitidas = [
    'cargos' => 'Tipos de Acampante',
    'departamentos' => 'Iglesias',
    'tiendas' => 'Distritos',
    'regiones' => 'Zonas',
    'clubes' => 'Clubes', // Nueva entrada para la tabla de clubes
    'cuota_acampante' => 'Cuota de Acampante',
    'historico_tasa_bcv' => 'Histórico Tasa BCV',
    'sexo' => 'Sexos'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gestión de Tablas</title>
    <?php include 'links-recursos.php'; ?>
</head>
<body style="padding-top: 70px;">
<?php include 'dashboard.php'; ?>

<div class="container mt-5">
    <h2 class="text-center mb-4">Gestión de Tablas Dinámicas</h2>
    
    <div class="row mb-4">
        <div class="col-md-6 offset-md-3">
            <label for="selector-tabla" class="form-label fw-bold">Seleccionar Tabla:</label>
            <div class="input-group">
                <select id="selector-tabla" class="form-select">
                    <option value="" disabled selected>-- Elige una tabla --</option>
                    <?php foreach ($tablas_permitidas as $nombre_tabla => $titulo): ?>
                        <option value="<?= htmlspecialchars($nombre_tabla) ?>"><?= htmlspecialchars($titulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
    
    <div id="contenedor-tabla">
        <p class="text-center">Selecciona una tabla para comenzar.</p>
    </div>

</div>

<div class="modal fade" id="modalFormularioDinamico" tabindex="-1" aria-labelledby="modalFormularioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" id="modalFormularioContenido">
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

<script src="js/gestion_de_tablas.js"></script>
</body>
</html>