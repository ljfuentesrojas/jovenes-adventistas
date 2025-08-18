<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    $response['message'] = 'Usuario no autenticado.';
    echo json_encode($response);
    exit;
}

// Obtener el saldo a favor en Bs
$sql_bs = "SELECT SUM(pc.transaccion_monto) AS saldo_total FROM pre_contabilidad AS pc JOIN registro_pagos AS rp ON rp.id = pc.id_registro_pago WHERE rp.creado_por = ? AND pc.tipo_pago = 'saldo a favor' AND pc.saldo_favor_activo = 1 AND pc.metodo_pago <> 4";
$stmt_bs = $conexion->prepare($sql_bs);

// Verificamos si la preparación de la consulta fue exitosa
if ($stmt_bs) {
    $stmt_bs->bind_param("i", $usuario_id);
    $stmt_bs->execute();
    $result_bs = $stmt_bs->get_result();
    $saldo_a_favor_usuario_bs = $result_bs->fetch_assoc()['saldo_total'] ?? 0;
    $stmt_bs->close();
} else {
    // Si la preparación falla, registramos el error
    $response['message'] = 'Error en la preparación de la consulta de saldo en Bs: ' . $conexion->error;
    echo json_encode($response);
    exit;
}

// Obtener el saldo a favor en USD
$sql_usd = "SELECT SUM(pc.transaccion_monto) AS saldo_total FROM pre_contabilidad AS pc JOIN registro_pagos AS rp ON rp.id = pc.id_registro_pago WHERE rp.creado_por = ? AND pc.tipo_pago = 'saldo a favor' AND pc.saldo_favor_activo = 1 AND pc.metodo_pago = 4";
$stmt_usd = $conexion->prepare($sql_usd);

// Verificamos si la preparación de la consulta fue exitosa
if ($stmt_usd) {
    $stmt_usd->bind_param("i", $usuario_id);
    $stmt_usd->execute();
    $result_usd = $stmt_usd->get_result();
    $saldo_a_favor_usuario_usd = $result_usd->fetch_assoc()['saldo_total'] ?? 0;
    $stmt_usd->close();
} else {
    // Si la preparación falla, registramos el error
    $response['message'] = 'Error en la preparación de la consulta de saldo en USD: ' . $conexion->error;
    echo json_encode($response);
    exit;
}

$response['success'] = true;
$response['saldo_bs'] = $saldo_a_favor_usuario_bs;
$response['saldo_usd'] = $saldo_a_favor_usuario_usd;

echo json_encode($response);