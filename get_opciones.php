<?php
include 'conexion.php';

$tabla = $_GET['tabla'];
$permitidas = ['departamentos', 'tiendas', 'regiones', 'cargos'];

if (in_array($tabla, $permitidas)) {
    $stmt = $conn->query("SELECT id, nombre FROM $tabla");
    $datos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($datos);
}