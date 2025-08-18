<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'pago_info' => [], 'acampantes' => []];

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit;
}

$registro_pago_id = $_POST['registro_pago_id'] ?? null;

if (empty($registro_pago_id)) {
    $response['message'] = 'ID del registro de pago no proporcionado.';
    echo json_encode($response);
    exit;
}

try {
    // Consulta principal para obtener los acampantes asociados al pago y la información del pago
    $sql_acampantes = "
        SELECT
            e.id,
            e.nombre,
            e.cedula,
            d.nombre AS iglesia,
            cl.nombre_club AS club,
            c.nombre AS cargo,
            pc.transaccion_monto AS monto_aplicado,
            rp.metodo_pago AS metodo_pago_id,
            pc.tasa_bcv,
            u.nombre AS registrado_por

        FROM empleados e
        JOIN pre_contabilidad pc ON e.id = pc.acampante_id
        JOIN registro_pagos rp ON rp.id = pc.id_registro_pago
        LEFT JOIN usuarios u ON rp.creado_por = u.id
        LEFT JOIN departamentos d ON e.departamento_id = d.id
        LEFT JOIN clubes cl ON e.club_id = cl.id
        LEFT JOIN cargos c ON e.cargo_id = c.id
        WHERE pc.id_registro_pago = ?";

    $stmt_acampantes = $conexion->prepare($sql_acampantes);
    if (!$stmt_acampantes) {
        throw new Exception('Error en la preparación de la consulta de acampantes: ' . $conexion->error);
    }
    $stmt_acampantes->bind_param("i", $registro_pago_id);
    $stmt_acampantes->execute();
    $resultado_acampantes = $stmt_acampantes->get_result();
    
    $acampantes = [];
    $total_aplicado = 0;
    $metodo_pago_id = null;

    while ($fila = $resultado_acampantes->fetch_assoc()) {
        $monto_a_mostrar = number_format($fila['monto_aplicado'], 2, ',', '.');
        $simbolo = ($fila['metodo_pago_id'] == 4) ? '$' : 'Bs';
        $fila['monto_display'] = "{$simbolo} {$monto_a_mostrar}";

        if ($fila['metodo_pago_id'] == 4) {
            $monto_usd_display = number_format($fila['monto_aplicado'], 2, ',', '.');
        } else {
            $monto_usd = $fila['monto_aplicado'] / $fila['tasa_bcv'];
            $monto_usd_display = number_format($monto_usd, 2, ',', '.');
        }
        $fila['monto_usd_display'] = "$ {$monto_usd_display}";

        // Lógica para mostrar el club o la iglesia, y el cargo
        $ubicacion = !empty($fila['club']) ? $fila['club'] : $fila['iglesia'];
        $fila['ubicacion_y_cargo'] = $ubicacion . " (" . $fila['cargo'] . ")";
        
        $acampantes[] = $fila;
        $total_aplicado += $fila['monto_aplicado'];
        $metodo_pago_id = $fila['metodo_pago_id'];
    }
    $stmt_acampantes->close();

    // Consulta para obtener la información general del pago, incluyendo el monto original
    $sql_pago_info = "
        SELECT
            rp.transaccion_monto,
            rp.transaccion_codigo,
            rp.transaccion_fecha,
            rp.tasa_bcv,
            rp.aprobar_pago,
            mp.metodo_p AS metodo_pago_nombre,
            rp.metodo_pago AS metodo_pago_id,
            u.nombre AS registrado_por
        FROM registro_pagos rp
        LEFT JOIN usuarios u ON rp.creado_por = u.id
        LEFT JOIN metodo_pago mp ON rp.metodo_pago = mp.id
        WHERE rp.id = ?";
    
    $stmt_pago_info = $conexion->prepare($sql_pago_info);
    if (!$stmt_pago_info) {
        throw new Exception('Error en la preparación de la consulta de pago: ' . $conexion->error);
    }
    $stmt_pago_info->bind_param("i", $registro_pago_id);
    $stmt_pago_info->execute();
    $pago_info = $stmt_pago_info->get_result()->fetch_assoc();
    $stmt_pago_info->close();

    // Calcular el saldo a favor
    $saldo_a_favor = $pago_info['transaccion_monto'] - $total_aplicado;
    $monto_a_mostrar = number_format($pago_info['transaccion_monto'], 2, ',', '.');
    $simbolo = ($pago_info['metodo_pago_id'] == 4) ? '$' : 'Bs';
    $pago_info['monto_display'] = "{$simbolo} {$monto_a_mostrar}";
    $pago_info['saldo_a_favor_display'] = "{$simbolo} " . number_format($saldo_a_favor, 2, ',', '.');
    $pago_info['total_aplicado_display'] = "{$simbolo} " . number_format($total_aplicado, 2, ',', '.');
    
    // Formatear la tasa BCV para la visualización
    $pago_info['tasa_bcv_display'] = number_format($pago_info['tasa_bcv'], 2, ',', '.');

    $response['success'] = true;
    $response['pago_info'] = $pago_info;
    $response['acampantes'] = $acampantes;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>