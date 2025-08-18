<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
    echo "Acceso denegado";
    exit;
}

// Mapeo de tablas y sus títulos para la interfaz
$tablas_permitidas = [
    'cargos' => 'Tipos de Acampante',
    'departamentos' => 'Iglesias',
    'tiendas' => 'Distritos',
    'regiones' => 'Zonas',
    'cuota_acampante' => 'Cuota de Acampante'
];

// Lógica del servidor para manejar las peticiones AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    $accion = $_POST['accion'] ?? '';
    $tabla = $_POST['tabla'] ?? '';
    $response = ['success' => false, 'message' => ''];

    // Validar que la tabla sea una de las permitidas
    if (!array_key_exists($tabla, $tablas_permitidas)) {
        $response['message'] = 'Tabla no válida.';
        echo json_encode($response);
        exit;
    }

    try {
        switch ($accion) {
            case 'listar':
                if ($tabla === 'tiendas') { // Distritos
                    $sql = "SELECT t.id, t.nombre, r.nombre AS zona_nombre, r.id AS zona_id FROM tiendas t JOIN regiones r ON t.regiones_id = r.id ORDER BY t.nombre ASC";
                } elseif ($tabla === 'departamentos') { // Iglesias
                    $sql = "SELECT d.id, d.nombre, t.nombre AS distrito_nombre, t.id AS distrito_id FROM departamentos d JOIN tiendas t ON d.tiendas_id = t.id ORDER BY d.nombre ASC";
                } else {
                    $sql = "SELECT * FROM " . $conexion->real_escape_string($tabla) . " ORDER BY id DESC";
                }
                
                $result = $conexion->query($sql);
                if ($result) {
                    $datos = [];
                    while ($fila = $result->fetch_assoc()) {
                        $datos[] = $fila;
                    }
                    $response['success'] = true;
                    $response['data'] = $datos;
                    $response['titulo'] = $tablas_permitidas[$tabla];
                } else {
                    $response['message'] = 'Error al obtener los datos: ' . $conexion->error;
                }
                break;

            case 'obtener_opciones':
                $opciones = [];
                if ($tabla === 'regiones') {
                    $sql = "SELECT id, nombre FROM regiones ORDER BY nombre ASC";
                } elseif ($tabla === 'tiendas') {
                    $sql = "SELECT id, nombre FROM tiendas ORDER BY nombre ASC";
                } else {
                    $response['success'] = true;
                    $response['data'] = [];
                    echo json_encode($response);
                    exit;
                }
                $result = $conexion->query($sql);
                while ($fila = $result->fetch_assoc()) {
                    $opciones[] = $fila;
                }
                $response['success'] = true;
                $response['data'] = $opciones;
                break;

            case 'agregar':
            case 'editar':
                $nombre = $_POST['nombre'] ?? '';
                $id = $_POST['id'] ?? 0;
                $parent_id = $_POST['parent_id'] ?? null;
                
                $sql = '';
                $bind_params = [];
                $bind_types = '';

                if ($tabla === 'cuota_acampante') {
                    $valor_cuota = $_POST['valor_cuota'] ?? null;
                    $moneda = $_POST['moneda'] ?? 'USD';
                    $fecha_establecida = $_POST['fecha_establecida'] ?? date('Y-m-d');
                    if ($accion === 'agregar') {
                        $sql = "INSERT INTO $tabla (valor_cuota, moneda, fecha_establecida) VALUES (?, ?, ?)";
                        $bind_params = [$valor_cuota, $moneda, $fecha_establecida];
                        $bind_types = 'dss';
                    } else {
                        $sql = "UPDATE $tabla SET valor_cuota = ?, moneda = ?, fecha_establecida = ? WHERE id = ?";
                        $bind_params = [$valor_cuota, $moneda, $fecha_establecida, $id];
                        $bind_types = 'dssi';
                    }
                } elseif ($tabla === 'tiendas') { // Distritos
                    if ($accion === 'agregar') {
                        $sql = "INSERT INTO $tabla (nombre, regiones_id) VALUES (?, ?)";
                        $bind_params = [$nombre, $parent_id];
                        $bind_types = 'si';
                    } else {
                        $sql = "UPDATE $tabla SET nombre = ?, regiones_id = ? WHERE id = ?";
                        $bind_params = [$nombre, $parent_id, $id];
                        $bind_types = 'sii';
                    }
                } elseif ($tabla === 'departamentos') { // Iglesias
                    if ($accion === 'agregar') {
                        $sql = "INSERT INTO $tabla (nombre, tiendas_id) VALUES (?, ?)";
                        $bind_params = [$nombre, $parent_id];
                        $bind_types = 'si';
                    } else {
                        $sql = "UPDATE $tabla SET nombre = ?, tiendas_id = ? WHERE id = ?";
                        $bind_params = [$nombre, $parent_id, $id];
                        $bind_types = 'sii';
                    }
                } else {
                    if ($accion === 'agregar') {
                        $sql = "INSERT INTO $tabla (nombre) VALUES (?)";
                        $bind_params = [$nombre];
                        $bind_types = 's';
                    } else {
                        $sql = "UPDATE $tabla SET nombre = ? WHERE id = ?";
                        $bind_params = [$nombre, $id];
                        $bind_types = 'si';
                    }
                }

                $stmt = $conexion->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param($bind_types, ...$bind_params);
                    if ($stmt->execute()) {
                        $response['success'] = true;
                        $response['message'] = 'Registro ' . ($accion === 'agregar' ? 'agregado' : 'editado') . ' correctamente.';
                    } else {
                        $response['message'] = 'Error al ' . ($accion === 'agregar' ? 'agregar' : 'editar') . ' el registro: ' . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $response['message'] = 'Error en la preparación de la consulta: ' . $conexion->error;
                }
                break;

            case 'borrar':
                $id = $_POST['id'] ?? 0;
                $columna_dependencia = '';
                $tabla_dependencia = '';

                switch ($tabla) {
                    case 'regiones':
                        $tabla_dependencia = 'tiendas';
                        $columna_dependencia = 'regiones_id';
                        break;
                    case 'tiendas':
                        $tabla_dependencia = 'departamentos';
                        $columna_dependencia = 'tiendas_id';
                        break;
                    case 'departamentos':
                        $tabla_dependencia = 'acampantes'; // Asumiendo que esta es la tabla real
                        $columna_dependencia = 'departamento_id';
                        break;
                    case 'cargos':
                        $tabla_dependencia = 'acampantes';
                        $columna_dependencia = 'cargo_id';
                        break;
                }

                if (!empty($tabla_dependencia)) {
                    $sql_check = "SELECT COUNT(*) FROM $tabla_dependencia WHERE $columna_dependencia = ?";
                    $stmt_check = $conexion->prepare($sql_check);
                    $stmt_check->bind_param("i", $id);
                    $stmt_check->execute();
                    $stmt_check->bind_result($count);
                    $stmt_check->fetch();
                    $stmt_check->close();

                    if ($count > 0) {
                        $response['message'] = 'No se puede eliminar este registro porque está vinculado a ' . $count . ' registros en la tabla ' . $tabla_dependencia . '.';
                        echo json_encode($response);
                        exit;
                    }
                }

                $sql = "DELETE FROM $tabla WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("i", $id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Registro eliminado correctamente.';
                } else {
                    $response['message'] = 'Error al eliminar el registro: ' . $stmt->error;
                }
                $stmt->close();
                break;
        }

    } catch (Exception $e) {
        $response['message'] = 'Error en el servidor: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit;
}
?>