<?php
include 'conexion.php';
session_start();

header('Content-Type: application/json');
$response = ['success' => false, 'message' => '', 'pagos' => [], 'total_pagado' => false, 'todos_pagos_aprobados' => false, 'listo_para_aprobar' => false];

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    $response['message'] = 'Acceso denegado.';
    echo json_encode($response);
    exit;
}

$acampante_id = $_POST['acampante_id'] ?? null;

if (empty($acampante_id)) {
    $response['message'] = 'ID de acampante no proporcionado.';
    echo json_encode($response);
    exit;
}

try {
    // 1. Obtener la cuota de inscripción actual
    $sql_cuota = "SELECT valor_cuota FROM cuota_acampante ORDER BY fecha_establecida DESC LIMIT 1";
    $result_cuota = $conexion->query($sql_cuota);
    $cuota_acampante_usd = $result_cuota->fetch_assoc()['valor_cuota'] ?? 0;

    // 2. Obtener la lista de pagos del acampante para la tabla del modal
    $sql_pagos = "
        SELECT
            pc.id,
            pc.transaccion_monto,
            pc.tipo_pago,
            rp.transaccion_codigo,
            rp.transaccion_fecha,
            pc.tasa_bcv,
            rp.aprobar_pago,
            mp.metodo_p AS metodo_pago_nombre,
            rp.metodo_pago AS metodo_pago_id
        FROM pre_contabilidad pc
        JOIN registro_pagos rp ON pc.id_registro_pago = rp.id
        LEFT JOIN metodo_pago mp ON rp.metodo_pago = mp.id
        WHERE pc.acampante_id = ?
        ORDER BY rp.transaccion_fecha DESC
    ";
    
    $stmt_pagos = $conexion->prepare($sql_pagos);
    if (!$stmt_pagos) {
        throw new Exception('Error en la preparación de la consulta de pagos: ' . $conexion->error);
    }
    $stmt_pagos->bind_param("i", $acampante_id);
    $stmt_pagos->execute();
    $resultado_pagos = $stmt_pagos->get_result();

    $pagos = [];
    $monto_abonado_total_usd = 0;
    $todos_pagos_aprobados = true;
    while ($fila = $resultado_pagos->fetch_assoc()) {
        $fila['aprobar_pago'] = $fila['aprobar_pago'] ? 'Aprobado' : 'Pendiente de aprobación';

        // Lógica para mostrar el monto en Bs o $ según el ID del método de pago
        $monto_formateado = number_format($fila['transaccion_monto'], 2, ',', '.');
        if ($fila['metodo_pago_id'] == 4) {
            $fila['monto_display'] = "$ {$monto_formateado}";
            // Sumar directamente si el pago es en USD
            $monto_abonado_total_usd += $fila['transaccion_monto'];
        } else {
            $fila['monto_display'] = "Bs {$monto_formateado}";
            // Convertir y sumar si el pago es en Bolívares
            $monto_abonado_total_usd += $fila['transaccion_monto'] / $fila['tasa_bcv'];
        }
        
        $pagos[] = $fila;
        
        // Verificar el estado de aprobación
        if ($fila['aprobar_pago'] !== 'Aprobado') {
            $todos_pagos_aprobados = false;
        }
    }
    $stmt_pagos->close();

    // 3. Lógica de validación final
    $total_pagado = ($monto_abonado_total_usd >= $cuota_acampante_usd);
    $listo_para_aprobar = $total_pagado && $todos_pagos_aprobados;
    
    $monto_restante_usd = max(0, $cuota_acampante_usd - $monto_abonado_total_usd);
    
    $response['success'] = true;
    $response['pagos'] = $pagos;
    $response['monto_abonado_total'] = number_format($monto_abonado_total_usd, 2);
    $response['monto_restante'] = number_format($monto_restante_usd, 2);
    $response['total_pagado'] = $total_pagado;
    $response['todos_pagos_aprobados'] = $todos_pagos_aprobados;
    $response['listo_para_aprobar'] = $listo_para_aprobar;

} catch (Exception $e) {
    $response['message'] = $e->getMessage();
}

$conexion->close();
echo json_encode($response);
?>