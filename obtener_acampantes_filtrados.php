<?php
include 'conexion.php';
session_start();

$usuario_id = $_SESSION['usuario_id'] ?? null;
$usuario_rol = $_SESSION['usuario']['rol'] ?? null;

if (!$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

header('Content-Type: application/json');

$departamento_id = $_GET['departamento_id'] ?? null;
$club_id = $_GET['club_id'] ?? null;

$query = "
    SELECT
    a.id,
    a.nombre,
    a.cedula,
    a.club_id,
    a.inscrito,
    d.nombre AS iglesia,
    c.nombre_club AS club,
    ca.nombre AS tipo_acampante,
    COALESCE(SUM(
        CASE
            WHEN rp.metodo_pago = 4 THEN pc.transaccion_monto
            ELSE pc.transaccion_monto / pc.tasa_bcv
        END
    ), 0) AS monto_abonado
    FROM empleados a
    LEFT JOIN departamentos d ON a.departamento_id = d.id
    LEFT JOIN clubes c ON a.club_id = c.id
    LEFT JOIN cargos ca ON a.cargo_id = ca.id
    LEFT JOIN usuarios u ON a.creado_por = u.id
    LEFT JOIN pre_contabilidad pc ON a.id = pc.acampante_id
    LEFT JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
    WHERE 1=1";

$params = [];
$types = '';

if ($usuario_rol !== 'admin') {
    $query .= " AND a.creado_por = ?";
    $params[] = $usuario_id;
    $types .= 'i';
}

if ($departamento_id) {
    $query .= " AND a.departamento_id = ?";
    $params[] = $departamento_id;
    $types .= 'i';
}

if ($club_id) {
    $query .= " AND a.club_id = ?";
    $params[] = $club_id;
    $types .= 'i';
}

$query .= " GROUP BY a.id, a.nombre, a.cedula, d.nombre, c.nombre_club, a.inscrito, ca.nombre ORDER BY a.id ASC";

$stmt = $conexion->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error en la preparación de la consulta: ' . $conexion->error]);
    exit;
}

if ($params) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$res = $stmt->get_result();

$acampantes = [];
$query_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY id DESC LIMIT 1";
$res_cuota = $conexion->query($query_cuota);
$row_cuota = $res_cuota->fetch_assoc();
$precio_total_acampante = (float)$row_cuota['valor_cuota'];

while ($row = $res->fetch_assoc()) {
    $monto_abonado = round($row['monto_abonado'],2);
    $row['monto_abonado'] = $monto_abonado;
    $row['monto_restante'] = $precio_total_acampante - $monto_abonado;
    $acampantes[] = $row;
}

echo json_encode(['success' => true, 'acampantes' => $acampantes]);
$stmt->close();
$conexion->close();
?>