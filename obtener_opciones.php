<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include 'conexion.php';
header('Content-Type: application/json');

$response = ['success' => false, 'data' => []];
$tipo = $_GET['tipo'] ?? '';
$parentId = $_GET['parent_id'] ?? null;

if (!$parentId || !$tipo) {
    echo json_encode($response);
    exit;
}

$query = '';
switch ($tipo) {
    case 'tiendas': // Para obtener los Distritos (tabla 'tiendas') según la Zona
        $query = "SELECT id, nombre FROM tiendas WHERE regiones_id = ? ORDER BY nombre ASC";
        break;
    case 'departamentos': // Para obtener las Iglesias (tabla 'departamentos') según el Distrito
        $query = "SELECT id, nombre FROM departamentos WHERE tiendas_id = ? ORDER BY nombre ASC";
        break;
    case 'clubes': // Para obtener los Clubes (tabla 'clubes') según la Iglesia
        $query = "SELECT id, nombre_club as nombre FROM clubes WHERE id_departamentos = ? ORDER BY nombre_club ASC";
        break;
    default:
        echo json_encode($response);
        exit;
}

$stmt = $conexion->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $parentId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        // En el caso de clubes, el nombre de la columna es 'nombre_club', no 'nombre'
        // Es importante ajustar esto para que el frontend lo reciba correctamente
        if ($tipo === 'clubes') {
            $response['data'][] = ['id' => $row['id'], 'nombre' => $row['nombre']];
        } else {
            $response['data'][] = $row;
        }
    }
    $response['success'] = true;
    $stmt->close();
}

echo json_encode($response);
?>