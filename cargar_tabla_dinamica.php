<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    echo "Acceso denegado";
    exit;
}

$tabla = $_GET['tabla'] ?? '';

// Mapeo de tablas, sus títulos, columnas y relaciones
$tablas_permitidas = [
    'cargos' => ['titulo' => 'Tipos de Acampante', 'columnas' => ['id', 'nombre'], 'relacion' => false],
    'departamentos' => ['titulo' => 'Iglesias', 'columnas' => ['id', 'nombre', 'distrito_nombre'], 'relacion' => 'tiendas'],
    'tiendas' => ['titulo' => 'Distritos', 'columnas' => ['id', 'nombre', 'zona_nombre'], 'relacion' => 'regiones'],
    'regiones' => ['titulo' => 'Zonas', 'columnas' => ['id', 'nombre'], 'relacion' => false],
    'clubes' => ['titulo' => 'Clubes', 'columnas' => ['id', 'nombre_club', 'iglesia_nombre'], 'relacion' => 'departamentos'],
    'cuota_acampante' => ['titulo' => 'Cuota de Acampante', 'columnas' => ['id', 'valor_cuota', 'moneda', 'fecha_establecida'], 'relacion' => false],
    'historico_tasa_bcv' => ['titulo' => 'Histórico Tasa BCV', 'columnas' => ['id', 'fecha', 'tasa_bcv'], 'relacion' => false],
    'sexo' => ['titulo' => 'Sexos', 'columnas' => ['id', 'sexo'], 'relacion' => false]
];

if (!array_key_exists($tabla, $tablas_permitidas)) {
    echo "Tabla no válida.";
    exit;
}

$info_tabla = $tablas_permitidas[$tabla];
$columnas = $info_tabla['columnas'];

// Traducir los nombres de las columnas para el encabezado de la tabla
$encabezados = [
    'id' => 'ID',
    'nombre' => 'Nombre',
    'distrito_nombre' => 'Distrito',
    'zona_nombre' => 'Zona',
    'nombre_club' => 'Nombre del Club',
    'iglesia_nombre' => 'Iglesia',
    'valor_cuota' => 'Valor Cuota',
    'moneda' => 'Moneda',
    'fecha_establecida' => 'Fecha Establecida',
    'fecha' => 'Fecha',
    'tasa_bcv' => 'Tasa BCV',
    'sexo' => 'Sexos'
];

?>
<h3 class="text-center mb-4">Tabla: <?= htmlspecialchars($info_tabla['titulo']) ?></h3>
<div class="d-flex justify-content-end mb-3">
    <button class="btn btn-success" id="btn-agregar-dinamico" data-tabla="<?= htmlspecialchars($tabla) ?>">Agregar Nuevo</button>
</div>
<div class="table-responsive">
    <table class="table table-bordered table-hover" id="tabla-dinamica">
        <thead class="table-dark text-center">
            <tr>
                <?php foreach ($columnas as $columna): ?>
                    <th><?= htmlspecialchars($encabezados[$columna]) ?></th>
                <?php endforeach; ?>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $query = "";
            switch ($tabla) {
                case 'tiendas':
                    // Consulta con JOIN para mostrar la Zona a la que pertenece cada Distrito
                    $query = "SELECT t.id, t.nombre, r.nombre AS zona_nombre, r.id AS regiones_id FROM tiendas t JOIN regiones r ON t.regiones_id = r.id ORDER BY t.nombre ASC";
                    break;
                case 'departamentos':
                    // Consulta con JOIN para mostrar el Distrito al que pertenece cada Iglesia
                    $query = "SELECT d.id, d.nombre, t.nombre AS distrito_nombre, t.id AS tiendas_id FROM departamentos d JOIN tiendas t ON d.tiendas_id = t.id ORDER BY d.nombre ASC";
                    break;
                case 'clubes':
                    // Consulta con JOIN para mostrar la Iglesia a la que pertenece cada Club
                    $query = "SELECT cl.id, cl.nombre_club, d.nombre AS iglesia_nombre, d.id AS id_departamento FROM clubes cl JOIN departamentos d ON cl.id_departamentos = d.id ORDER BY cl.nombre_club ASC";
                    break;
                case 'historico_tasa_bcv':
                    $query = "SELECT id, fecha, tasa_bcv FROM historico_tasa_bcv ORDER BY fecha DESC";
                    break;
                default:
                    // Consulta genérica para las tablas sin relaciones a mostrar
                    $select_columns = implode(', ', $columnas);
                    $query = "SELECT $select_columns FROM " . $conexion->real_escape_string($tabla) . " ORDER BY id DESC";
                    break;
            }

            $resultado = $conexion->query($query);
            if ($resultado) {
                while ($fila = $resultado->fetch_assoc()):
            ?>
            <tr data-id="<?= $fila['id'] ?>" data-tabla="<?= htmlspecialchars($tabla) ?>">
                <?php foreach ($columnas as $columna): ?>
                    <td class="<?= $columna ?>"><?= htmlspecialchars($fila[$columna]) ?></td>
                <?php endforeach; ?>
                <td>
                    <button class="btn btn-sm btn-primary btn-editar-dinamico" data-id="<?= $fila['id'] ?>" 
                            data-nombre="<?= htmlspecialchars($fila['nombre'] ?? ($fila['nombre_club'] ?? '')) ?>"
                            <?php if ($tabla === 'tiendas'): ?>
                                data-parent-id="<?= $fila['regiones_id'] ?>"
                            <?php elseif ($tabla === 'departamentos'): ?>
                                data-parent-id="<?= $fila['tiendas_id'] ?>"
                            <?php elseif ($tabla === 'clubes'): ?>
                                data-parent-id="<?= $fila['id_departamento'] ?>"
                            <?php elseif ($tabla === 'cuota_acampante'): ?>
                                data-valor-cuota="<?= htmlspecialchars($fila['valor_cuota']) ?>"
                                data-moneda="<?= htmlspecialchars($fila['moneda']) ?>"
                                data-fecha-establecida="<?= htmlspecialchars($fila['fecha_establecida']) ?>"
                            <?php elseif ($tabla === 'historico_tasa_bcv'): ?>
                                data-fecha="<?= htmlspecialchars($fila['fecha']) ?>"
                                data-tasa-bcv="<?= htmlspecialchars($fila['tasa_bcv']) ?>"
                            <?php endif; ?>>
                        Editar
                    </button>
                    <button class="btn btn-sm btn-danger btn-borrar-dinamico" data-id="<?= $fila['id'] ?>">Borrar</button>
                </td>
            </tr>
            <?php
                endwhile;
            } else {
                echo '<tr><td colspan="' . (count($columnas) + 1) . '">Error al cargar los datos.</td></tr>';
            }
            ?>
        </tbody>
    </table>
</div>