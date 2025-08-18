<?php
include 'conexion.php';
session_start();

// Validar acceso solo para admin
if (!isset($_SESSION['usuario_id']) || $_SESSION['usuario']['rol'] !== 'admin') {
  echo json_encode(['success' => false, 'message' => 'Acceso denegado']);
  exit;
}

$id = intval($_POST['id'] ?? 0);
$nombre = trim($_POST['nombre'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$rol = $_POST['rol'] ?? '';
$estado = $_POST['estado'] ?? '';
$clave_nueva = $_POST['clave_nueva'] ?? '';

if (!$id || !$nombre || !$correo || !$rol) {
  echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
  exit;
}

$sql = "UPDATE usuarios SET nombre = ?, correo = ?, rol = ?, estado=?";
$params = [$nombre, $correo, $rol, $estado];
$types = "ssss";

if (!empty($clave_nueva)) {
  $clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
  $sql .= ", clave = ?";
  $params[] = $clave_hash;
  $types .= "s";
}

$sql .= " WHERE id = ?";
$params[] = $id;
$types .= "i";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

echo json_encode([
  'success' => true,
  'message' => 'Usuario actualizado correctamente',
  'usuario' => [
    'id' => $id,
    'nombre' => $nombre,
    'correo' => $correo,
    'rol' => $rol,
    'estado' => (($estado=='activo') ? 'Si' : 'No')
  ]
]);