<?php
include 'conexion.php';

$cedula = $_GET['cedula'] ?? '';
$id = $_GET['id'] ?? '';

$respuesta = ['existe' => false];

if ($cedula !== '') {

    $sql="SELECT COUNT(*) FROM empleados WHERE cedula = ?";

if($id !== ''){

   $sql .= " AND id <> $id";

}


  $stmt = $conexion->prepare($sql);
  $stmt->bind_param("s", $cedula);
  $stmt->execute();
  $stmt->bind_result($count);
  $stmt->fetch();
  $respuesta['existe'] = $count > 0;
  $stmt->close();
}

header('Content-Type: application/json');
echo json_encode($respuesta);
?>