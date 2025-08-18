<?php
session_start();
include 'conexion.php';

// Verificar que el usuario sea administrador
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
  exit;
}

// Verificar que se haya enviado el ID
if (!isset($_POST['id'])) {
  echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
  exit;
}

$id = intval($_POST['id']);

// Actualizar el estado de inscripción
$resultado = $conexion->query("UPDATE empleados SET inscrito = 1 WHERE id = $id");

if ($resultado) {
  echo json_encode(['success' => true, 'message' => 'Acampante aprobado correctamente']);
} else {
  echo json_encode(['success' => false, 'message' => 'Error al aprobar el Acampante']);
}