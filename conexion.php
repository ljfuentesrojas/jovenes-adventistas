<?php


function esLocalOInterno($ip) {
  return $ip === '127.0.0.1' ||
         $ip === '::1' || // IPv6 localhost
         preg_match('/^10\./', $ip) ||
         preg_match('/^192\.168\./', $ip) ||
         preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip);
}

if ($_SERVER['SERVER_NAME'] === 'localhost' || esLocalOInterno($_SERVER['REMOTE_ADDR'])) {
  // Estás en localhost o red interna
      // Estás en localhost
 $conexion = new mysqli("localhost", "root", "", "club");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
} else {
  // Estás en un servidor remoto
$conexion = new mysqli("sql305.ezyro.com", "ezyro_39709008", "1774727f", "ezyro_39709008_club");
if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}
}
$conexion->set_charset("utf8mb4");
?>

