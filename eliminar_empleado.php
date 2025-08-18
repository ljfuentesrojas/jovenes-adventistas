<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Acceso no autorizado']);
  exit;
}

if (!isset($_POST['id'])) {
  echo json_encode(['success' => false, 'message' => 'ID no proporcionado']);
  exit;
}

$id = intval($_POST['id']);

// Obtener nombre de la foto antes de eliminar
$consulta = $conexion->query("SELECT foto FROM empleados WHERE id = $id");
if ($consulta && $consulta->num_rows > 0) {
  $empleado = $consulta->fetch_assoc();
  $foto = $empleado['foto'];

  // Eliminar registro de la base de datos
  $resultado = $conexion->query("DELETE FROM empleados WHERE id = $id");

  if ($resultado) {
    // Eliminar archivo de foto si existe
    $rutaFoto = __DIR__ . "/fotos/" . $foto;
    if (file_exists($rutaFoto)) {
      unlink($rutaFoto);
    }

    echo json_encode(['success' => true, 'message' => 'Empleado y foto eliminados correctamente']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Error al eliminar el empleado']);
  }
} else {
  echo json_encode(['success' => false, 'message' => 'Empleado no encontrado']);
}