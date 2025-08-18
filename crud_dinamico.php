<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Acceso denegado.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$action = $_POST['action'] ?? '';
$tabla = $_POST['tabla'] ?? '';
$id = $_POST['id'] ?? null;

// Mapeo de tablas y sus columnas de enlace en la tabla 'empleados'
$mapeo_fk = [
    'cargos' => 'cargo_id',
    'departamentos' => 'departamento_id',
    'tiendas' => 'tienda_id',
    'regiones' => 'region_id',
    'clubes' => 'club_id' // Nueva entrada para los clubes
];

// Lista de tablas gestionables
$tablas_gestionables = array_merge(array_keys($mapeo_fk), ['cuota_acampante', 'historico_tasa_bcv','sexo']);

// Validar si la tabla es gestionable
if (!in_array($tabla, $tablas_gestionables)) {
    echo json_encode(['success' => false, 'message' => 'Tabla no gestionable.']);
    exit;
}

// Manejar la acción
try {
    switch ($action) {
        case 'add':
            $stmt = null;
            if ($tabla === 'cuota_acampante') {
                $valor_cuota = $_POST['valor_cuota'] ?? null;
                $moneda = $_POST['moneda'] ?? 'USD';
                $fecha_establecida = $_POST['fecha_establecida'] ?? date('Y-m-d');
                $sql = "INSERT INTO $tabla (valor_cuota, moneda, fecha_establecida) VALUES (?, ?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("dss", $valor_cuota, $moneda, $fecha_establecida);
            } elseif ($tabla === 'tiendas') {
                $nombre = $_POST['nombre'] ?? null;
                $regiones_id = $_POST['regiones_id'] ?? null;
                $sql = "INSERT INTO $tabla (nombre, regiones_id) VALUES (?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("si", $nombre, $regiones_id);
            } elseif ($tabla === 'departamentos') {
                $nombre = $_POST['nombre'] ?? null;
                $tiendas_id = $_POST['tiendas_id'] ?? null;
                $sql = "INSERT INTO $tabla (nombre, tiendas_id) VALUES (?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("si", $nombre, $tiendas_id);
            } elseif ($tabla === 'clubes') {
                $nombre_club = $_POST['nombre_club'] ?? null;
                $id_departamento = $_POST['id_departamento'] ?? null;
                $sql = "INSERT INTO $tabla (nombre_club, id_departamentos) VALUES (?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("si", $nombre_club, $id_departamento);
            } elseif ($tabla === 'historico_tasa_bcv') {
                $fecha = $_POST['fecha'] ?? date('Y-m-d');
                $tasa_bcv = $_POST['tasa_bcv'] ?? null;
                $sql = "INSERT INTO $tabla (fecha, tasa_bcv) VALUES (?, ?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sd", $fecha, $tasa_bcv);
            } elseif ($tabla === 'sexo') {
                $sexo = $_POST['sexo'] ?? null;
                $sql = "INSERT INTO $tabla (sexo) VALUES (?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("s", $sexo);
            } else {
                $nombre = $_POST['nombre'] ?? null;
                $sql = "INSERT INTO $tabla (nombre) VALUES (?)";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("s", $nombre);
            }

            if ($stmt && $stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro agregado con éxito.', 'id' => $conexion->insert_id, 'data' => $_POST]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al agregar el registro: ' . ($stmt ? $stmt->error : 'Consulta no preparada.')]);
            }
            if ($stmt) $stmt->close();
            break;

        case 'edit':
            $stmt = null;
            if ($tabla === 'cuota_acampante') {
                $valor_cuota = $_POST['valor_cuota'] ?? null;
                $moneda = $_POST['moneda'] ?? null;
                $fecha_establecida = $_POST['fecha_establecida'] ?? null;
                $sql = "UPDATE $tabla SET valor_cuota = ?, moneda = ?, fecha_establecida = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("dssi", $valor_cuota, $moneda, $fecha_establecida, $id);
            } elseif ($tabla === 'tiendas') {
                $nombre = $_POST['nombre'] ?? null;
                $regiones_id = $_POST['regiones_id'] ?? null;
                $sql = "UPDATE $tabla SET nombre = ?, regiones_id = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sii", $nombre, $regiones_id, $id);
            } elseif ($tabla === 'departamentos') {
                $nombre = $_POST['nombre'] ?? null;
                $tiendas_id = $_POST['tiendas_id'] ?? null;
                $sql = "UPDATE $tabla SET nombre = ?, tiendas_id = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sii", $nombre, $tiendas_id, $id);
            } elseif ($tabla === 'clubes') {
                $nombre_club = $_POST['nombre_club'] ?? null;
                $id_departamento = $_POST['id_departamento'] ?? null;
                $sql = "UPDATE $tabla SET nombre_club = ?, id_departamentos = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sii", $nombre_club, $id_departamento, $id);
            } elseif ($tabla === 'historico_tasa_bcv') {
                $fecha = $_POST['fecha'] ?? null;
                $tasa_bcv = $_POST['tasa_bcv'] ?? null;
                $sql = "UPDATE $tabla SET fecha = ?, tasa_bcv = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("sdi", $fecha, $tasa_bcv, $id);
            } elseif ($tabla === 'sexo') {
                $sexo = $_POST['sexo'] ?? null;
                $sql = "UPDATE $tabla SET sexo = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("si", $sexo, $id);
            }else {
                $nombre = $_POST['nombre'] ?? null;
                $sql = "UPDATE $tabla SET nombre = ? WHERE id = ?";
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param("si", $nombre, $id);
            }

            if ($stmt && $stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro actualizado con éxito.', 'data' => $_POST]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al actualizar el registro: ' . ($stmt ? $stmt->error : 'Consulta no preparada.')]);
            }
            if ($stmt) $stmt->close();
            break;

        case 'delete':
            $columna_fk = $mapeo_fk[$tabla] ?? null;

            if ($columna_fk) {
                // Verificar si hay acampantes vinculados antes de borrar
                $stmt_check = $conexion->prepare("SELECT COUNT(*) FROM empleados WHERE $columna_fk = ?");
                $stmt_check->bind_param("i", $id);
                $stmt_check->execute();
                $stmt_check->bind_result($count);
                $stmt_check->fetch();
                $stmt_check->close();

                if ($count > 0) {
                    echo json_encode(['success' => false, 'message' => 'No se puede borrar, hay acampantes vinculados a este registro.']);
                    exit;
                }
            }

            $stmt_delete = $conexion->prepare("DELETE FROM " . $tabla . " WHERE id = ?");
            $stmt_delete->bind_param("i", $id);
            if ($stmt_delete->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro eliminado con éxito.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error al eliminar el registro: ' . $stmt_delete->error]);
            }
            $stmt_delete->close();
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error en el servidor: ' . $e->getMessage()]);
}
?>