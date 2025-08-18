<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit;
}

$empleado_id = $_POST['empleado_id'] ?? null;

if (empty($empleado_id)) {
    $response['message'] = 'ID de acampante no proporcionado.';
    echo json_encode($response);
    exit;
}

$conexion->begin_transaction();

try {
    $sql = "UPDATE empleados SET inscrito = 1 WHERE id = ?";
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error en la preparación de la consulta: ' . $conexion->error);
    }
    
    $stmt->bind_param("i", $empleado_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Error al aprobar acampante: ' . $stmt->error);
    }
    
    $conexion->commit();
    $response['success'] = true;
    $response['message'] = 'Acampante aprobado con éxito.';

    $stmt->close();

} catch (Exception $e) {
    $conexion->rollback();
    $response['message'] = $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>