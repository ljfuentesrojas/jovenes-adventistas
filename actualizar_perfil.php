<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario_id'])) {
  echo json_encode(['success' => false, 'message' => 'No autorizado']);
  exit;
}

$usuario_id = $_SESSION['usuario_id'];
$nombre = $_POST['nombre'] ?? '';
$correo = $_POST['correo'] ?? '';
$clave_actual = $_POST['clave_actual'] ?? '';
$clave_nueva = $_POST['clave_nueva'] ?? '';

$stmt = $conexion->prepare("SELECT clave FROM usuarios WHERE id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$resultado = $stmt->get_result();
$usuario = $resultado->fetch_assoc();

if (!$usuario || !password_verify($clave_actual, $usuario['clave'])) {
  echo json_encode(['success' => false, 'message' => 'La clave actual es incorrecta']);
  exit;
}

$sql = "UPDATE usuarios SET nombre = ?, correo = ?";
$params = [$nombre, $correo];
$types = "ss";

if (!empty($clave_nueva)) {
  $clave_hash = password_hash($clave_nueva, PASSWORD_DEFAULT);
  $sql .= ", clave = ?";
  $params[] = $clave_hash;
  $types .= "s";
}

$sql .= " WHERE id = ?";
$params[] = $usuario_id;
$types .= "i";

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();

$_SESSION['usuario_nombre'] = $nombre;
$_SESSION['usuario']['correo'] = $correo;

echo json_encode(['success' => true, 'message' => 'Perfil actualizado correctamente']);
?>